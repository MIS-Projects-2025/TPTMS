<?php

namespace App\Services;

use App\Repositories\ProjectRepository;
use App\Constants\ProjectConstants;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProjectService
{
    protected $projectRepository;
    protected $statusMapping;
    protected $notificationService;

    public function __construct(
        ProjectRepository $projectRepository,
        NotificationService $notificationService
    ) {
        $this->projectRepository = $projectRepository;
        $this->notificationService = $notificationService;
        $this->statusMapping = ProjectConstants::getStatusMapping();
    }
    public function createProject(array $data, $createdBy)
    {
        // Check if project with same name already exists
        $existingProject = $this->projectRepository->getProjectByName($data['name']);
        if ($existingProject) {
            throw new \Exception("Project with name '{$data['name']}' already exists");
        }

        $projectData = [
            'PROJ_NAME' => $data['name'],
            'PROJ_DESC' => $data['description'],
            'PROJ_DEPT' => $data['department'],
            'PROJ_HANDLER' => !empty($data['handler_ids']) ? implode(',', $data['handler_ids']) : null,
            'PROJ_STATUS' => $data['status'],
            'TARGET_DEADLINE' => $data['target_deadline'] ?? null,
            'CREATED_BY' => $createdBy,
            'CREATED_AT' => now(),
            'UPDATED_BY' => $createdBy,
            'UPDATED_AT' => now(),
        ];

        $projectId = $this->projectRepository->createProject($projectData);

        // Log the creation action
        $this->logAction(
            $projectId,
            'CREATED',
            'Project created',
            null,
            $createdBy
        );

        return $projectId;
    }
    public function getProjectsDataTable($request)
    {
        $filters = [
            'search' => trim($request->input('search', '')),
            'department' => $request->input('department'),
            'assigned_to' => $request->input('assigned_to'),
            'status' => $request->input('status', ''),
            'sortField' => $request->input('sortField', 'created_at'),
            'sortOrder' => $request->input('sortOrder', 'desc'),
        ];


        // Convert status filter to IDs
        if ($filters['status']) {
            $statusMap = ProjectConstants::getProjectStatusMap();
            $filters['status_ids'] = array_keys(array_filter(
                $statusMap,
                fn($label) => stripos($label, $filters['status']) !== false
            ));
        }

        $pagination = [
            'page' => (int) $request->input('page', 1),
            'pageSize' => (int) $request->input('pageSize', 10),
        ];

        $result = $this->projectRepository->getProjects($filters, $pagination);

        // Format projects with additional data
        $formattedProjects = $result['data']->map(function ($project) {
            return $this->formatProject($project);
        });

        return [
            'projects' => $formattedProjects,
            'pagination' => [
                'current_page' => $pagination['page'],
                'per_page' => $pagination['pageSize'],
                'total' => $result['total'],
                'last_page' => $result['last_page'],
            ],
            'departments' => $this->projectRepository->getDepartments(),
            'statusCounts' => $this->projectRepository->getStatusCounts(),
            'filters' => $filters,
        ];
    }
    public function updateProject($projectId, array $data, $updatedBy)
    {
        $project = $this->projectRepository->getProjectById($projectId);
        if (!$project) {
            throw new \Exception("Project not found");
        }

        $updateData = [
            'PROJ_NAME' => $data['name'],
            'PROJ_DESC' => $data['description'],
            'PROJ_DEPT' => $data['department'],
            'PROJ_HANDLER' => !empty($data['handler_ids']) ? implode(',', $data['handler_ids']) : null,
            'TARGET_DEADLINE' => $data['target_deadline'] ?? null,
            'PROJ_STATUS' => $data['status'],
            'UPDATED_BY' => $updatedBy,
            'UPDATED_AT' => now(),
        ];

        $this->projectRepository->updateProject($projectId, $updateData);

        // Log the update action
        $this->logAction(
            $projectId,
            'UPDATED',
            'Project details updated',
            null,
            $updatedBy
        );

        return true;
    }
    public function getAllDepartments()
    {
        return $this->projectRepository->getAllDepartments();
    }
    public function formatProject($project)
    {
        $statusOptions = ProjectConstants::getProjectStatusMap();

        // Parse assigned programmers
        $assignedTo = [];
        if (!empty($project->ASSIGNED_PROGS)) {
            $empIds = array_filter(explode(',', $project->ASSIGNED_PROGS));

            if (!empty($empIds)) {
                $employees = $this->projectRepository->getEmployeesByIds($empIds);

                $assignedTo = $employees->map(function ($emp) {
                    $fullName = trim($emp->FIRSTNAME . ' ' . $emp->MIDDLE_INITIAL . ' ' . $emp->LASTNAME);
                    $firstInitial = !empty($emp->FIRSTNAME) ? strtoupper(substr($emp->FIRSTNAME, 0, 1)) : '';
                    $lastInitial = !empty($emp->LASTNAME) ? strtoupper(substr($emp->LASTNAME, 0, 1)) : '';
                    $tableInitial = $firstInitial . $lastInitial;

                    return [
                        'emp_id' => $emp->EMPLOYID,
                        'initials' => $tableInitial,
                        'full_name' => $fullName
                    ];
                })->toArray();
            }
        }
        // Parse assigned programmers
        $handler = [];
        if (!empty($project->PROJ_HANDLER)) {
            $empIds = array_filter(explode(',', $project->PROJ_HANDLER));

            if (!empty($empIds)) {
                $employees = $this->projectRepository->getEmployeesByIds($empIds);

                $handler = $employees->map(function ($emp) {
                    $fullName = trim($emp->FIRSTNAME . ' ' . $emp->MIDDLE_INITIAL . ' ' . $emp->LASTNAME);
                    $firstInitial = !empty($emp->FIRSTNAME) ? strtoupper(substr($emp->FIRSTNAME, 0, 1)) : '';
                    $lastInitial = !empty($emp->LASTNAME) ? strtoupper(substr($emp->LASTNAME, 0, 1)) : '';
                    $tableInitial = $firstInitial . $lastInitial;

                    return [
                        'emp_id' => $emp->EMPLOYID,
                        'initials' => $tableInitial,
                        'full_name' => $fullName
                    ];
                })->toArray();
            }
        }

        // Get tickets based on project status
        $activeTickets = $this->getProjectTickets($project);

        return [
            'id' => $project->PROJ_ID,
            'name' => $project->PROJ_NAME,
            'description' => $project->PROJ_DESC,
            'department' => $project->PROJ_DEPT,
            'status' => $statusOptions[$project->PROJ_STATUS] ?? 'Unknown',
            'assigned_to' => $assignedTo,
            'proj_handler' => $handler,
            'created_at' => $project->CREATED_AT,
            'target_deadline' => $project->TARGET_DEADLINE,
            'active_tickets' => $activeTickets,
        ];
    }

    private function getProjectTickets($project)
    {
        $isDeployed = $project->PROJ_STATUS == ProjectConstants::PROJ_STATUS_DEPLOYED;
        $tickets = $this->projectRepository->getProjectTickets($project->PROJ_NAME, $isDeployed);

        return $tickets->map(function ($ticket) {
            $assignmentLog = $this->projectRepository->getTicketAssignmentLog($ticket->ID);

            return [
                'id' => $ticket->ID,
                'type' => $this->getRequestTypeLabel($ticket->TYPE_OF_REQUEST),
                'date_start' => $assignmentLog->ACTION_AT ?? null,
                'date_end' => $ticket->CLOSED_AT ?? "Ongoing",
            ];
        })->toArray();
    }

    public function getRequestTypeLabel($requestType)
    {
        $labels = ProjectConstants::getRequestTypeLabels();
        return $labels[$requestType] ?? 'Unknown';
    }

    public function createFromTicket($projectName, $description, $department, $requestorId, $createdBy, $requestType = null, $ticketId = null)
    {
        $projId = $this->projectRepository->createProject([
            'PROJ_NAME' => $projectName ?? 'New Project',
            'PROJ_DESC' => $description,
            'PROJ_DEPT' => $department,
            'PROJ_STATUS' => ProjectConstants::PROJ_STATUS_PLANNING,
            'PROJ_REQUESTOR' => $requestorId,
            'PROJECT_VERSION' => '1.0',
            'DATE_START' => null,
            'ASSIGNED_PROGS' => null,
            'CREATED_BY' => $createdBy,
            'CREATED_AT' => now(),
            'UPDATED_BY' => $createdBy,
            'UPDATED_AT' => now(),
        ]);

        $this->logAction(
            $projId,
            'CREATED',
            'Project created from ticket',
            null,
            $createdBy,
            $requestType,
            $ticketId
        );

        return $projId;
    }

    public function updateProjectStatus($projectName, $newStatus, $updatedBy, $additionalData = [], $requestType = null, $ticketId = null)
    {
        $project = $this->projectRepository->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        // Store old status for notification
        $oldStatus = $project->PROJ_STATUS;

        $updateData = [
            'PROJ_STATUS' => $newStatus,
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $updatedBy,
        ];

        // Merge additional data (like assigned programmers, dates, etc.)
        $updateData = array_merge($updateData, $additionalData);

        $this->projectRepository->updateProject($project->PROJ_ID, $updateData);

        $actionType = $this->getActionTypeForStatus($newStatus);
        $description = $this->getStatusUpdateDescription($newStatus);

        $this->logAction(
            $project->PROJ_ID,
            $actionType,
            $description,
            $additionalData['ASSIGNED_PROGS'] ?? null,
            $updatedBy,
            $requestType,
            $ticketId
        );

        // Send notification if status actually changed
        if ($oldStatus != $newStatus) {
            try {
                $this->notificationService->notifyProjectStatusChanged(
                    $project->PROJ_ID,
                    $oldStatus,
                    $newStatus,
                    $updatedBy,
                    $projectName,
                    $project->PROJ_DEPT
                );

                Log::info("Project status change notification sent", [
                    'project_id' => $project->PROJ_ID,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send project status change notification: " . $e->getMessage(), [
                    'project_id' => $project->PROJ_ID,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    public function updateProjectStatusById($projectId, $newStatus, $updatedBy, $additionalData = [], $requestType = null, $ticketId = null)
    {
        $project = $this->projectRepository->getProjectById($projectId);
        if (!$project) {
            throw new \Exception("Project not found: $projectId");
        }

        // Store old status for notification
        $oldStatus = $project->PROJ_STATUS;

        $updateData = [
            'PROJ_STATUS' => $newStatus,
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $updatedBy,
        ];

        // Merge additional data (like assigned programmers, dates, etc.)
        $updateData = array_merge($updateData, $additionalData);

        $this->projectRepository->updateProject($project->PROJ_ID, $updateData);

        $actionType = $this->getActionTypeForStatus($newStatus);
        $description = $this->getStatusUpdateDescription($newStatus);

        $this->logAction(
            $project->PROJ_ID,
            $actionType,
            $description,
            $additionalData['ASSIGNED_PROGS'] ?? null,
            $updatedBy,
            $requestType,
            $ticketId
        );

        // Send notification if status actually changed
        if ($oldStatus != $newStatus) {
            try {
                $this->notificationService->notifyProjectStatusChanged(
                    $project->PROJ_ID,
                    $oldStatus,
                    $newStatus,
                    $updatedBy,
                    $project->PROJ_NAME,
                    $project->PROJ_DEPT
                );

                Log::info("Project status change notification sent", [
                    'project_id' => $project->PROJ_ID,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to send project status change notification: " . $e->getMessage(), [
                    'project_id' => $project->PROJ_ID,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    public function updateToReady($projectName, $approvalType, $updatedBy, $requestType = null, $ticketId = null)
    {
        $this->updateProjectStatus(
            $projectName,
            ProjectConstants::PROJ_STATUS_TRIAGED,
            $updatedBy,
            [],
            $requestType,
            $ticketId
        );
    }

    public function updateToInProgress($projectName, $assignedPrograms, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->projectRepository->getProjectByName($projectName);
        $dateStart = $project->DATE_START ?? now();

        $this->updateProjectStatus(
            $projectName,
            ProjectConstants::PROJ_STATUS_IN_PROGRESS,
            $updatedBy,
            [
                'ASSIGNED_PROGS' => $assignedPrograms,
                'DATE_START' => $dateStart,
            ],
            $requestType,
            $ticketId
        );
    }

    public function updateToDeployed($projectName, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->projectRepository->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        // Check if there are any open tickets
        $openTickets = $this->projectRepository->countOpenTickets($projectName);

        if ($openTickets > 0) {
            throw new \Exception("Cannot deploy project. There are still {$openTickets} open ticket(s) for this project.");
        }

        // Get the latest closed ticket date as DATE_END
        $lastClosedTicket = $this->projectRepository->getLatestClosedTicket($projectName);
        $dateEnd = $lastClosedTicket ? $lastClosedTicket->CLOSED_AT : now();

        $this->updateProjectStatus(
            $projectName,
            ProjectConstants::PROJ_STATUS_DEPLOYED,
            $updatedBy,
            ['DATE_END' => $dateEnd],
            $requestType,
            $ticketId
        );
    }

    public function logAction($projId, $actionType, $description, $assignedProgs, $actionBy, $requestType = null, $ticketId = null)
    {
        $project = $this->projectRepository->getProjectById($projId);

        $this->projectRepository->logAction([
            'PROJ_ID' => $projId,
            'ACTION_TYPE' => $actionType,
            'DESCRIPTION' => $description,
            'PROJECT_VERSION' => $project->PROJECT_VERSION ?? '1.0',
            'PROJ_STATUS' => $project->PROJ_STATUS ?? null,
            'ASSIGNED_PROGS' => $assignedProgs,
            'REQUEST_TYPE' => $requestType,
            'TICKET_ID' => $ticketId,
            'ACTION_BY' => $actionBy,
            'UPDATE_AT' => now(),
        ]);
    }

    public function updateProjectStatusFromTickets($projectName)
    {
        $project = $this->projectRepository->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        // Count open and closed tickets
        $openTickets = $this->projectRepository->countOpenTickets($projectName);
        $closedTickets = $this->projectRepository->countClosedTickets($projectName);

        // Determine project status
        if ($openTickets > 0) {
            $newStatus = ProjectConstants::PROJ_STATUS_IN_PROGRESS;
        } elseif ($closedTickets > 0) {
            $newStatus = ProjectConstants::PROJ_STATUS_DEPLOYED;
        } else {
            $newStatus = ProjectConstants::PROJ_STATUS_PLANNING;
        }

        // Update project status
        $this->updateProjectStatus($projectName, $newStatus, 'system');

        Log::info("Project status synced from tickets", [
            'project' => $projectName,
            'new_status' => $newStatus
        ]);
    }
    public function getHandlerOptions(array $departments)
    {
        return $this->projectRepository->getHandlerOptions($departments);
    }

    private function getActionTypeForStatus($status)
    {
        $map = [
            ProjectConstants::PROJ_STATUS_TRIAGED => 'APPROVED',
            ProjectConstants::PROJ_STATUS_IN_PROGRESS => 'ASSIGNED',
            ProjectConstants::PROJ_STATUS_ON_HOLD => 'ON_HOLD',
            ProjectConstants::PROJ_STATUS_DEPLOYED => 'DEPLOYED',
            ProjectConstants::PROJ_STATUS_CANCELLED => 'CANCELLED',
            ProjectConstants::PROJ_STATUS_INACTIVE => 'INACTIVE',
        ];

        return $map[$status] ?? 'UPDATED';
    }

    private function getStatusUpdateDescription($status)
    {
        $statusMap = ProjectConstants::getProjectStatusMap();
        $statusLabel = $statusMap[$status] ?? 'Unknown';
        return "Project status updated to {$statusLabel}";
    }

    public function getProjectLogs($projectId)
    {
        $logs = $this->projectRepository->getProjectLogs($projectId);
        $statusOptions = ProjectConstants::getProjectStatusMap();

        $logs->getCollection()->transform(function ($log) use ($statusOptions) {
            return [
                'ID' => $log->ID,
                'ACTION_TYPE' => $log->ACTION_TYPE,
                'DESCRIPTION' => $log->DESCRIPTION,
                'PROJECT_VERSION' => $log->PROJECT_VERSION,
                'PROJ_STATUS' => $statusOptions[$log->PROJ_STATUS] ?? $log->PROJ_STATUS,
                'ASSIGNED_PROGS' => $log->ASSIGNED_PROGS,
                'REQUEST_TYPE' => isset($log->REQUEST_TYPE) ? $this->getRequestTypeLabel($log->REQUEST_TYPE) : null,
                'TICKET_ID' => $log->TICKET_ID ?? null,
                'ACTION_BY' => $log->ACTION_BY,
                'UPDATE_AT' => $log->UPDATE_AT,
            ];
        });

        return $logs;
    }

    public function getAssignedProjects($empId)
    {
        return $this->projectRepository->getAssignedProjects($empId);
    }
    public function getProjectById($projectId)
    {
        return $this->projectRepository->getProjectById($projectId);
    }

    public function processExcelImport($file, $userId)
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Get header row
        $headers = array_map('trim', $rows[0]);
        $headers = array_map('strtoupper', $headers);

        // Validate required columns
        $requiredColumns = ['PROJ_NAME', 'PROJ_DEPT', 'PROJ_STATUS'];
        $missingColumns = array_diff($requiredColumns, $headers);

        if (!empty($missingColumns)) {
            throw new \Exception('Missing required columns: ' . implode(', ', $missingColumns));
        }

        $imported = 0;
        $updated = 0;
        $errors = [];

        // Process data rows
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $data = array_combine($headers, $row);
                $result = $this->processImportRow($data, $userId);

                if ($result['action'] === 'insert') {
                    $imported++;
                } elseif ($result['action'] === 'update') {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = "Row " . ($i + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        ];
    }

    /**
     * Process individual import row
     */
    private function processImportRow($data, $userId)
    {
        $processedData = $this->cleanImportData($data);

        // Check if project exists
        $existingProject = $this->projectRepository->findProjectForImport(
            $processedData['PROJ_ID'] ?? null,
            $processedData['PROJ_NAME']
        );

        if ($existingProject) {
            // Update existing project
            $this->projectRepository->updateProject($existingProject->PROJ_ID, [
                'PROJ_NAME' => $processedData['PROJ_NAME'],
                'PROJ_DESC' => $processedData['PROJ_DESC'] ?? null,
                'PROJ_DEPT' => $processedData['PROJ_DEPT'],
                'PROJ_STATUS' => $processedData['PROJ_STATUS'],
                'PROJ_REQUESTOR' => $processedData['PROJ_REQUESTOR'] ?? null,
                'DATE_START' => $processedData['DATE_START'] ?? null,
                'DATE_END' => $processedData['DATE_END'] ?? null,
                'TARGET_DEADLINE' => $processedData['TARGET_DEADLINE'] ?? null,
                'ASSIGNED_PROGS' => $processedData['ASSIGNED_PROGS'] ?? null,
                'UPDATED_BY' => $userId,
                'UPDATED_AT' => now(),
            ]);

            return ['action' => 'update', 'id' => $existingProject->PROJ_ID];
        } else {
            // Insert new project
            $this->projectRepository->createProject([
                'PROJ_NAME' => $processedData['PROJ_NAME'],
                'PROJ_DESC' => $processedData['PROJ_DESC'] ?? null,
                'PROJ_DEPT' => $processedData['PROJ_DEPT'],
                'PROJ_STATUS' => $processedData['PROJ_STATUS'],
                'PROJ_REQUESTOR' => $processedData['PROJ_REQUESTOR'] ?? null,
                'DATE_START' => $processedData['DATE_START'] ?? null,
                'DATE_END' => $processedData['DATE_END'] ?? null,
                'TARGET_DEADLINE' => $processedData['TARGET_DEADLINE'] ?? null,
                'ASSIGNED_PROGS' => $processedData['ASSIGNED_PROGS'] ?? null,
                'CREATED_BY' => $userId,
                'CREATED_AT' => now(),
                'UPDATED_AT' => now(),
            ]);

            return ['action' => 'insert'];
        }
    }

    /**
     * Clean and validate import data
     */
    private function cleanImportData($data)
    {
        $cleaned = [];

        // PROJ_ID
        if (isset($data['PROJ_ID']) && !empty($data['PROJ_ID'])) {
            $cleaned['PROJ_ID'] = (int)$data['PROJ_ID'];
        }

        // PROJ_NAME (required)
        if (empty($data['PROJ_NAME'])) {
            throw new \Exception('Project name is required');
        }
        $cleaned['PROJ_NAME'] = trim($data['PROJ_NAME']);

        // PROJ_DESC
        $cleaned['PROJ_DESC'] = isset($data['PROJ_DESC']) ? trim($data['PROJ_DESC']) : null;

        // PROJ_DEPT (required)
        if (empty($data['PROJ_DEPT'])) {
            throw new \Exception('Project department is required');
        }
        $cleaned['PROJ_DEPT'] = trim($data['PROJ_DEPT']);

        // PROJ_STATUS (required)
        if (empty($data['PROJ_STATUS'])) {
            throw new \Exception('Project status is required');
        }
        $cleaned['PROJ_STATUS'] = $this->convertStatusToNumeric($data['PROJ_STATUS']);

        // PROJ_REQUESTOR (optional)
        $cleaned['PROJ_REQUESTOR'] = isset($data['PROJ_REQUESTOR']) && !empty(trim($data['PROJ_REQUESTOR']))
            ? trim($data['PROJ_REQUESTOR'])
            : null;

        // DATE_START (optional)
        $cleaned['DATE_START'] = isset($data['DATE_START']) && !empty($data['DATE_START'])
            ? date('Y-m-d', strtotime($data['DATE_START']))
            : null;

        // DATE_END (optional)
        $cleaned['DATE_END'] = isset($data['DATE_END']) && !empty($data['DATE_END'])
            ? date('Y-m-d', strtotime($data['DATE_END']))
            : null;

        // TARGET_DEADLINE (optional)
        $cleaned['TARGET_DEADLINE'] = isset($data['TARGET_DEADLINE']) && !empty($data['TARGET_DEADLINE'])
            ? date('Y-m-d', strtotime($data['TARGET_DEADLINE']))
            : null;

        // ASSIGNED_PROGS (optional)
        $cleaned['ASSIGNED_PROGS'] = isset($data['ASSIGNED_PROGS']) && !empty($data['ASSIGNED_PROGS'])
            ? implode(',', array_map('trim', explode(',', $data['ASSIGNED_PROGS'])))
            : null;

        return $cleaned;
    }

    /**
     * Convert status text to numeric value
     */
    private function convertStatusToNumeric($status)
    {
        // If already numeric, validate and return
        if (is_numeric($status)) {
            $numericStatus = (int)$status;
            $validStatuses = [
                ProjectConstants::PROJ_STATUS_PLANNING,
                ProjectConstants::PROJ_STATUS_TRIAGED,
                ProjectConstants::PROJ_STATUS_IN_PROGRESS,
                ProjectConstants::PROJ_STATUS_ON_HOLD,
                ProjectConstants::PROJ_STATUS_DEPLOYED,
                ProjectConstants::PROJ_STATUS_CANCELLED,
                ProjectConstants::PROJ_STATUS_INACTIVE,
            ];

            if (in_array($numericStatus, $validStatuses, true)) {
                return $numericStatus;
            }
        }

        // Convert text status to numeric
        $statusLower = strtolower(trim($status));

        // Direct match first
        if (isset($this->statusMapping[$statusLower])) {
            return $this->statusMapping[$statusLower];
        }

        // Try with underscores replaced with spaces
        $statusWithSpaces = str_replace('_', ' ', $statusLower);
        if (isset($this->statusMapping[$statusWithSpaces])) {
            return $this->statusMapping[$statusWithSpaces];
        }

        // Try with spaces replaced with underscores
        $statusWithUnderscores = str_replace(' ', '_', $statusLower);
        if (isset($this->statusMapping[$statusWithUnderscores])) {
            return $this->statusMapping[$statusWithUnderscores];
        }

        // Try partial matches
        foreach ($this->statusMapping as $text => $numeric) {
            if (strpos($statusLower, $text) !== false || strpos($text, $statusLower) !== false) {
                return $numeric;
            }
        }

        throw new \Exception("Invalid status: $status. Valid options: " .
            implode(', ', array_keys($this->statusMapping)) . " or numeric project values 1-7");
    }

    /**
     * Generate CSV template for download
     */
    public function generateTemplateCsv()
    {
        $headers = [
            'PROJ_ID',
            'PROJ_NAME',
            'PROJ_DESC',
            'PROJ_DEPT',
            'PROJ_STATUS',
            'PROJ_REQUESTOR',
            'DATE_START',
            'DATE_END',
            'TARGET_DEADLINE',
            'ASSIGNED_PROGS'
        ];

        $sampleData = [
            ['', 'Sample Project 1', 'Sample description', 'MIS', 'Pending', '1390', '2025-08-18', '2025-09-18', '2025-08-01', "'1705,1706"],
            ['', 'Sample Project 2', 'Another description', 'HR', 'On Hold', '', '', '', '2025-08-01', "'1707"],
            ['', 'Sample Project 3', 'Third project', 'IT', 'Deployed', '1705', '2025-08-01', '2025-08-31', '2025-08-01', ''],
        ];

        // Create CSV content
        $content = implode(',', $headers) . "\n";
        foreach ($sampleData as $row) {
            $content .= implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return $content;
    }
}
