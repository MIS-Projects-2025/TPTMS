<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ProjectController extends Controller
{
    // -------------------------
    // Project status constants
    // -------------------------
    const PROJ_STATUS_PLANNING = 1;
    const PROJ_STATUS_TRIAGED = 2; // "Ready"
    const PROJ_STATUS_IN_PROGRESS = 3;
    const PROJ_STATUS_ON_HOLD = 4;
    const PROJ_STATUS_DEPLOYED = 5;
    const PROJ_STATUS_CANCELLED = 6;
    const PROJ_STATUS_INACTIVE = 7;

    const STATUS_NEW = 1;
    const STATUS_TRIAGED = 2;
    const STATUS_APPROVED = 3;
    const STATUS_IN_PROGRESS = 4;
    const STATUS_RESOLVED = 5;
    const STATUS_CLOSED = 6;
    const STATUS_REJECTED = 7;
    const STATUS_ON_HOLD = 8;
    const STATUS_RETURNED = 9;

    // Request Type Constants (from TicketingController)
    const REQUEST_NEW_SYSTEM = 1;
    const REQUEST_MODIFICATION = 2;
    const REQUEST_ENHANCEMENT = 3;
    const REQUEST_ADJUSTMENT = 4;
    const REQUEST_TESTING = 5;
    const REQUEST_PARALLEL_RUN = 6;

    protected $statusMapping = [
        'planning' => self::PROJ_STATUS_PLANNING,
        'ready' => self::PROJ_STATUS_TRIAGED,
        'triaged' => self::PROJ_STATUS_TRIAGED,
        'in progress' => self::PROJ_STATUS_IN_PROGRESS,
        'on hold' => self::PROJ_STATUS_ON_HOLD,
        'deployed' => self::PROJ_STATUS_DEPLOYED,
        'cancelled' => self::PROJ_STATUS_CANCELLED,
        'inactive' => self::PROJ_STATUS_INACTIVE,
    ];

    /**
     * Helper function to get project DB connection
     */
    protected function projectDB()
    {
        return DB::connection('projects');
    }

    /**
     * Get request type label
     */
    private function getRequestTypeLabel($requestType)
    {
        $labels = [
            self::REQUEST_NEW_SYSTEM => 'New System',
            self::REQUEST_MODIFICATION => 'Modification',
            self::REQUEST_ENHANCEMENT => 'Enhancement',
            self::REQUEST_ADJUSTMENT => 'Adjustment',
            self::REQUEST_TESTING => 'Testing',
            self::REQUEST_PARALLEL_RUN => 'Parallel Run',
        ];
        return $labels[$requestType] ?? 'Unknown';
    }

    public function getProjectsDataTable(Request $request)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        $encoded = $request->input('q', '');
        if ($encoded) {
            $decodedParams = json_decode(base64_decode($encoded), true);
            if (is_array($decodedParams)) {
                $request->merge($decodedParams);
            }
        }

        // Pagination & sorting
        $page = (int) $request->input('page', 1);
        $pageSize = (int) $request->input('pageSize', 10);
        $search = trim((string) $request->input('search', ''));
        $sortField = (string) $request->input('sortField', 'CREATED_AT');
        $sortOrder = (string) $request->input('sortOrder', 'desc');

        // Filters
        $status = $request->input('status', '');
        $department = $request->input('department', '');

        // Base query
        $query = $this->projectDB()->table('project_list')->whereNull('DELETED_AT');

        /** 🟦 Filter by status */
        if ($status) {
            $statusMap = $this->getProjectStatusOptions();
            $statusIds = array_keys(array_filter($statusMap, fn($label) => stripos($label, $status) !== false));
            if (!empty($statusIds)) {
                $query->whereIn('PROJ_STATUS', $statusIds);
            }
        }

        /** 🟦 Filter by department */
        if ($department) {
            $query->where('PROJ_DEPT', $department);
        }

        /** 🟦 Search (by project name or description) */
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('PROJ_NAME', 'like', "%{$search}%")
                    ->orWhere('PROJ_DESC', 'like', "%{$search}%");
            });
        }

        /** 🟦 Count total before pagination */
        $total = $query->count();

        /** 🟦 Sorting */
        $columnMap = [
            'name' => 'PROJ_NAME',
            'department' => 'PROJ_DEPT',
            'assigned' => 'ASSIGNED_PROGS',
            'status' => 'PROJ_STATUS',
            'created_at' => 'CREATED_AT',
        ];
        $query->orderBy($columnMap[$sortField] ?? 'CREATED_AT', $sortOrder);

        /** 🟦 Paginate */
        $projects = $query->forPage($page, $pageSize)->get();

        /** 🟦 Map display values */
        $statusOptions = $this->getProjectStatusOptions();

        $data = $projects->map(function ($project) use ($statusOptions) {
            // Parse assigned programmers
            $assignedTo = [];
            if (!empty($project->ASSIGNED_PROGS)) {
                $empIds = array_filter(explode(',', $project->ASSIGNED_PROGS));

                // Get employee names from masterlist
                if (!empty($empIds)) {
                    $employees = DB::connection('masterlist')
                        ->table('employee_masterlist')
                        ->whereIn('EMPLOYID', $empIds)
                        ->select('EMPLOYID', 'FIRSTNAME', 'MIDDLE_INITIAL', 'LASTNAME')
                        ->get();

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

            // Get tickets based on project status
            $activeTickets = [];

            if ($project->PROJ_STATUS != self::PROJ_STATUS_DEPLOYED) {
                // For non-deployed projects, get active tickets
                $activeTickets = DB::table('tickets')
                    ->where('PROJECT_NAME', $project->PROJ_NAME)
                    ->whereNull('DELETED_AT')
                    ->whereNull('CLOSED_AT') // active tickets only
                    ->orderBy('CREATED_AT', 'desc')
                    ->get()
                    ->map(function ($ticket) {
                        $assignmentLog = DB::table('ticket_workflow')
                            ->where('TICKET_ID', $ticket->ID)
                            ->where('ACTION_TYPE', 'ASSIGNED')
                            ->orderBy('ACTION_AT', 'asc')
                            ->first();

                        return [
                            'id' => $ticket->ID,
                            'type' => $this->getRequestTypeLabel($ticket->TYPE_OF_REQUEST),
                            'date_start' => $assignmentLog->ACTION_AT ?? null,
                            'date_end' => $ticket->CLOSED_AT ?? "Ongoing",
                        ];
                    })
                    ->toArray();
            } else {
                // For deployed projects, get only the latest ticket (active or closed)
                $latestTicket = DB::table('tickets')
                    ->where('PROJECT_NAME', $project->PROJ_NAME)
                    ->whereNull('DELETED_AT')
                    ->orderBy('CREATED_AT', 'desc')
                    ->first();

                if ($latestTicket) {
                    $assignmentLog = DB::table('ticket_workflow')
                        ->where('TICKET_ID', $latestTicket->ID)
                        ->where('ACTION_TYPE', 'ASSIGNED')
                        ->orderBy('ACTION_AT', 'asc')
                        ->first();

                    $activeTickets[] = [
                        'id' => $latestTicket->ID,
                        'type' => $this->getRequestTypeLabel($latestTicket->TYPE_OF_REQUEST),
                        'date_start' => $assignmentLog->ACTION_AT ?? null,
                        'date_end' => $latestTicket->CLOSED_AT ?? "Ongoing",
                    ];
                }
            }
            return [
                'id' => $project->PROJ_ID,
                'name' => $project->PROJ_NAME,
                'description' => $project->PROJ_DESC,
                'department' => $project->PROJ_DEPT,
                'status' => $statusOptions[$project->PROJ_STATUS] ?? 'Unknown',
                'assigned_to' => $assignedTo,
                'created_at' => $project->CREATED_AT,
                'active_tickets' => $activeTickets, // ✅ all active tickets
            ];
        });

        /** 🟦 Dropdown filters */
        $departments = $this->projectDB()->table('project_list')
            ->whereNull('DELETED_AT')
            ->distinct()
            ->pluck('PROJ_DEPT')
            ->toArray();

        $statusCounts = [
            'all' => $this->projectDB()->table('project_list')->whereNull('DELETED_AT')->count(),
            'active' => $this->projectDB()->table('project_list')->whereNull('DELETED_AT')
                ->whereIn('PROJ_STATUS', [self::PROJ_STATUS_PLANNING, self::PROJ_STATUS_TRIAGED, self::PROJ_STATUS_IN_PROGRESS])
                ->count(),
            'completed' => $this->projectDB()->table('project_list')->whereNull('DELETED_AT')
                ->where('PROJ_STATUS', self::PROJ_STATUS_DEPLOYED)
                ->count(),
            'on_hold' => $this->projectDB()->table('project_list')->whereNull('DELETED_AT')
                ->where('PROJ_STATUS', self::PROJ_STATUS_ON_HOLD)
                ->count(),
        ];

        return Inertia::render('Projects/Table', [
            'projects' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $pageSize,
                'total' => $total,
                'last_page' => (int) ceil($total / $pageSize),
            ],
            'departments' => $departments,
            'statusCounts' => $statusCounts,
            'filters' => compact('search', 'department', 'status', 'sortField', 'sortOrder'),
        ])->with('flash', ['message' => 'Projects loaded successfully']);
    }

    public function getProjectLogs($projectId)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $logs = $this->projectDB()->table('project_logs')
            ->where('PROJ_ID', $projectId)
            ->orderBy('UPDATE_AT', 'desc')
            ->paginate(10, [
                'ID',
                'PROJ_ID',
                'ACTION_TYPE',
                'DESCRIPTION',
                'PROJECT_VERSION',
                'PROJ_STATUS',
                'ASSIGNED_PROGS',
                'REQUEST_TYPE',
                'TICKET_ID',
                'ACTION_BY',
                'UPDATE_AT',
            ]);

        $statusOptions = $this->getProjectStatusOptions();

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


        return response()->json($logs);
    }

    /**
     * Helper to get project record by name
     */
    protected function getProjectByName($projectName)
    {
        return $this->projectDB()->table('project_list')
            ->where('PROJ_NAME', $projectName)
            ->first();
    }


    public function createFromTicket($projectName, $description, $department, $requestorId, $createdBy, $requestType = null, $ticketId = null)
    {
        $projId = $this->projectDB()->table('project_list')->insertGetId([
            'PROJ_NAME' => $projectName ?? 'New Project',
            'PROJ_DESC' => $description,
            'PROJ_DEPT' => $department,
            'PROJ_STATUS' => self::PROJ_STATUS_PLANNING,
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

    /**
     * Update project status to READY (2)
     */
    public function updateToReady($projectName, $approvalType, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_TRIAGED,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction(
            $project->PROJ_ID,
            $approvalType,
            'Project status updated to Ready',
            null,
            $updatedBy,
            $requestType,
            $ticketId
        );
    }

    /**
     * Update project status to IN_PROGRESS (3)
     */
    public function updateToInProgress($projectName, $assignedPrograms, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        // Get the earliest DATE_START if it exists, otherwise use now()
        $dateStart = $project->DATE_START ?? now();

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_IN_PROGRESS,
                'ASSIGNED_PROGS' => $assignedPrograms,
                'DATE_START' => $dateStart,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction(
            $project->PROJ_ID,
            'ASSIGNED',
            'Project status updated to In Progress',
            $assignedPrograms,
            $updatedBy,
            $requestType,
            $ticketId
        );
    }
    public function updateToResolve($projectName,  $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        // Get the earliest DATE_START if it exists, otherwise use now()
        $dateStart = $project->DATE_START ?? now();

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_IN_PROGRESS,
                'DATE_START' => $dateStart,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction(
            $project->PROJ_ID,
            'RESOLVED',
            'Project status updated to In Progress',
            null,
            $updatedBy,
            $requestType,
            $ticketId
        );
    }
    /**
     * Update project status to ON_HOLD (4)
     */
    public function updateToOnHold($projectName, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_ON_HOLD,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction(
            $project->PROJ_ID,
            'ON_HOLD',
            'Project status updated to On Hold',
            null,
            $updatedBy,
            $requestType,
            $ticketId
        );
    }

    /**
     * Update project status to DEPLOYED (5)
     * Only if ALL tickets for this project are CLOSED
     */
    public function updateToDeployed($projectName, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        // Check if there are any open tickets for this project
        $openTickets = DB::table('tickets')
            ->where('PROJECT_NAME', $projectName)
            ->whereNull('DELETED_AT')
            ->whereNotIn('STATUS', [self::STATUS_CLOSED, self::STATUS_REJECTED])
            ->count();

        if ($openTickets > 0) {
            throw new \Exception("Cannot deploy project. There are still {$openTickets} open ticket(s) for this project.");
        }

        // Get the latest closed ticket date as DATE_END
        $lastClosedTicket = DB::table('tickets')
            ->where('PROJECT_NAME', $projectName)
            ->where('STATUS', self::STATUS_CLOSED)
            ->whereNull('DELETED_AT')
            ->orderBy('CLOSED_AT', 'desc')
            ->first();

        $dateEnd = $lastClosedTicket ? $lastClosedTicket->CLOSED_AT : now();

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_DEPLOYED,
                'DATE_END' => $dateEnd,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction(
            $project->PROJ_ID,
            'DEPLOYED',
            'Project status updated to Deployed - All tickets closed',
            null,
            $updatedBy,
            $requestType,
            $ticketId
        );
    }

    /**
     * Update project status to CANCELLED (6)
     */
    public function updateToCancelled($projectName, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_CANCELLED,
                'DATE_END' => now(),
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction(
            $project->PROJ_ID,
            'CANCELLED',
            'Project status updated to Cancelled',
            null,
            $updatedBy,
            $requestType,
            $ticketId
        );
    }

    /**
     * Update project status to INACTIVE (7)
     */
    public function updateToInactive($projectName, $updatedBy, $requestType = null, $ticketId = null)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_INACTIVE,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction(
            $project->PROJ_ID,
            'INACTIVE',
            'Project status updated to Inactive',
            null,
            $updatedBy,
            $requestType,
            $ticketId
        );
    }

    /**
     * Log project actions in project_logs with request type and ticket ID
     */
    public function logAction($projId, $actionType, $description, $assignedProgs, $actionBy, $requestType = null, $ticketId = null)
    {
        $project = $this->projectDB()->table('project_list')
            ->select('PROJECT_VERSION', 'PROJ_STATUS')
            ->where('PROJ_ID', $projId)
            ->first();

        $this->projectDB()->table('project_logs')->insert([
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

    /**
     * Auto-update project status based on all tickets
     */
    public function updateProjectStatusFromTickets($projectName)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            return;
        }

        // Fetch all ticket statuses for the project
        $tickets = DB::table('tickets')
            ->where('PROJECT_NAME', $projectName)
            ->whereNull('DELETED_AT')
            ->get();

        if ($tickets->isEmpty()) {
            return;
        }

        $ticketStatuses = $tickets->pluck('STATUS')->toArray();

        // Check ticket status distribution
        $hasNew = in_array(self::STATUS_NEW, $ticketStatuses, true);
        $hasTriaged = in_array(self::STATUS_TRIAGED, $ticketStatuses, true);
        $hasInProgress = in_array(self::STATUS_IN_PROGRESS, $ticketStatuses, true);
        $hasOnHold = in_array(self::STATUS_ON_HOLD, $ticketStatuses, true);
        $hasResolved = in_array(self::STATUS_RESOLVED, $ticketStatuses, true);

        // Count closed and total tickets
        $closedCount = 0;
        $rejectedCount = 0;
        foreach ($ticketStatuses as $status) {
            if ($status == self::STATUS_CLOSED) $closedCount++;
            if ($status == self::STATUS_REJECTED) $rejectedCount++;
        }

        $allTicketsClosed = ($closedCount + $rejectedCount) === count($ticketStatuses);
        $allTicketsClosedOnly = $closedCount === count($ticketStatuses);

        // Determine new project status with priority logic
        if ($hasResolved || $hasInProgress) {
            $newStatus = self::PROJ_STATUS_IN_PROGRESS;
        } elseif ($hasOnHold) {
            $newStatus = self::PROJ_STATUS_ON_HOLD;
        } elseif ($allTicketsClosedOnly) {
            // All tickets are CLOSED (not rejected) - deploy project
            $newStatus = self::PROJ_STATUS_DEPLOYED;
        } elseif ($allTicketsClosed && $rejectedCount === count($ticketStatuses)) {
            // All tickets rejected
            $newStatus = self::PROJ_STATUS_CANCELLED;
        } elseif ($hasTriaged) {
            $newStatus = self::PROJ_STATUS_TRIAGED;
        } elseif ($hasNew) {
            $newStatus = self::PROJ_STATUS_PLANNING;
        } else {
            $newStatus = self::PROJ_STATUS_PLANNING;
        }

        // Only update if status changed
        if ($project->PROJ_STATUS != $newStatus) {
            // Get latest ticket info for logging
            $latestTicket = $tickets->sortByDesc('CREATED_AT')->first();

            $this->projectDB()->table('project_list')
                ->where('PROJ_ID', $project->PROJ_ID)
                ->update([
                    'PROJ_STATUS' => $newStatus,
                    'UPDATED_AT' => now(),
                ]);

            $this->logAction(
                $project->PROJ_ID,
                'AUTO_UPDATE',
                "Project status auto-updated from {$project->PROJ_STATUS} to {$newStatus} based on ticket statuses",
                null,
                'SYSTEM',
                $latestTicket->TYPE_OF_REQUEST ?? null,
                $latestTicket->TICKET_ID ?? null
            );
        }
    }

    /**
     * Project status label map
     */
    public function getProjectStatusOptions()
    {
        return [
            self::PROJ_STATUS_PLANNING => 'Planning',
            self::PROJ_STATUS_TRIAGED => 'Ready',
            self::PROJ_STATUS_IN_PROGRESS => 'In Progress',
            self::PROJ_STATUS_ON_HOLD => 'On Hold',
            self::PROJ_STATUS_DEPLOYED => 'Deployed',
            self::PROJ_STATUS_CANCELLED => 'Cancelled',
            self::PROJ_STATUS_INACTIVE => 'Inactive',
        ];
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls,csv|max:2048'
        ]);

        try {
            $empData = session('emp_data');
            $userId = $empData['emp_id'];

            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Get header row (first row)
            $headers = array_map('trim', $rows[0]);
            $headers = array_map('strtoupper', $headers); // Convert to uppercase for consistency

            // Validate required columns
            $requiredColumns = ['PROJ_NAME', 'PROJ_DEPT', 'PROJ_STATUS'];
            $missingColumns = array_diff($requiredColumns, $headers);

            if (!empty($missingColumns)) {
                return redirect()->route('project.list')
                    ->with('error', 'Missing required columns: ' . implode(', ', $missingColumns));
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            // Process data rows (skip header)
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                try {
                    // Create associative array from row data
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

            $message = "Import completed. Inserted: $imported, Updated: $updated";
            if (!empty($errors)) {
                $message .= ". Errors: " . implode('; ', array_slice($errors, 0, 5));
            }

            return redirect()->route('project.list')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('project.list')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    private function processImportRow($data, $userId)
    {
        // Clean and validate data
        $processedData = $this->cleanImportData($data);

        // Check if project exists (by PROJ_ID if provided, or by PROJ_NAME)
        $existingProject = null;

        if (!empty($processedData['PROJ_ID'])) {
            $existingProject = $this->projectDB()
                ->table('project_list')
                ->where('PROJ_ID', $processedData['PROJ_ID'])
                ->first();
        } else {
            $existingProject = $this->projectDB()
                ->table('project_list')
                ->where('PROJ_NAME', $processedData['PROJ_NAME'])
                ->first();
        }

        if ($existingProject) {
            // Update existing project
            $this->projectDB()->table('project_list')
                ->where('PROJ_ID', $existingProject->PROJ_ID)
                ->update([
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
            $this->projectDB()->table('project_list')->insert([
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

    private function convertStatusToNumeric($status)
    {
        // If already numeric, validate and return (allow project statuses 1..7)
        if (is_numeric($status)) {
            $numericStatus = (int)$status;
            if (in_array($numericStatus, [
                self::PROJ_STATUS_PLANNING,
                self::PROJ_STATUS_TRIAGED,
                self::PROJ_STATUS_IN_PROGRESS,
                self::PROJ_STATUS_ON_HOLD,
                self::PROJ_STATUS_DEPLOYED,
                self::PROJ_STATUS_CANCELLED,
                self::PROJ_STATUS_INACTIVE,
            ], true)) {
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

        throw new \Exception("Invalid status: $status. Valid options: " . implode(', ', array_keys($this->statusMapping)) . " or numeric project values 1-7");
    }

    public function downloadTemplate()
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
            ['', 'Sample Project 3', 'Third project', 'IT', 'Deployed', '1705', '2025-08-01', '2025-08-31', '2025-08-01',  ''],
        ];

        // Create CSV content
        $content = implode(',', $headers) . "\n";
        foreach ($sampleData as $row) {
            $content .= implode(',', array_map(function ($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="project_import_template.csv"');
    }
}
