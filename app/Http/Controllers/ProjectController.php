<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


class ProjectController extends Controller
{
    // Project Status Constants
    const PROJ_STATUS_PLANNING = 1;
    const PROJ_STATUS_TRIAGED = 2; // clearer label for triaged
    const PROJ_STATUS_IN_PROGRESS = 3;
    const PROJ_STATUS_ON_HOLD = 4;
    const PROJ_STATUS_DEPLOYED = 5;
    const PROJ_STATUS_CANCELLED = 6;
    const PROJ_STATUS_INACTIVE = 7;
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
                        // Tooltip: Full name with middle initial (e.g., "Juan A Dela Cruz")
                        $fullName = trim($emp->FIRSTNAME . ' ' . $emp->MIDDLE_INITIAL . ' ' . $emp->LASTNAME);
                        // Get the first letter of first name
                        $firstInitial = !empty($emp->FIRSTNAME) ? strtoupper(substr($emp->FIRSTNAME, 0, 1)) : '';

                        // Get the first letter of last name
                        $lastInitial = !empty($emp->LASTNAME) ? strtoupper(substr($emp->LASTNAME, 0, 1)) : '';

                        // Combine initials
                        $tableInitial = $firstInitial . $lastInitial;

                        return [
                            'emp_id' => $emp->EMPLOYID,
                            'initials' => $tableInitial,
                            'full_name' => $fullName
                        ];
                    })->toArray();
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
            ->paginate(10, [ // ✅ use paginate for frontend pagination
                'ID',
                'PROJ_ID',
                'ACTION_TYPE',
                'DESCRIPTION',
                'PROJECT_VERSION',
                'PROJ_STATUS',
                'ASSIGNED_PROGS',
                'ACTION_BY',
                'UPDATE_AT',
            ]);

        $statusOptions = $this->getProjectStatusOptions();

        // ✅ Map each log to readable data
        $logs->getCollection()->transform(function ($log) use ($statusOptions) {
            return [
                'ID' => $log->ID,
                'ACTION_TYPE' => $log->ACTION_TYPE,
                'DESCRIPTION' => $log->DESCRIPTION,
                'PROJECT_VERSION' => $log->PROJECT_VERSION,
                'PROJ_STATUS' => $statusOptions[$log->PROJ_STATUS] ?? $log->PROJ_STATUS,
                'ASSIGNED_PROGS' => $log->ASSIGNED_PROGS,
                'ACTION_BY' => $log->ACTION_BY,
                'UPDATE_AT' => $log->UPDATE_AT,
            ];
        });

        return response()->json($logs); // ✅ Return as JSON
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

    /**
     * Create project from ticket
     * Status: PLANNING (1)
     */
    public function createFromTicket($projectName, $description, $department, $requestorId, $createdBy)
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

        $this->logAction($projId, 'CREATED', 'Project created from ticket', null, $createdBy);

        return $projId;
    }

    /**
     * Update project status to READY (2)
     */
    public function updateToReady($projectName, $approvalType, $updatedBy)
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

        $this->logAction($project->PROJ_ID, $approvalType, 'Project status updated to Ready', null, $updatedBy);
    }

    /**
     * Update project status to IN_PROGRESS (3)
     */
    public function updateToInProgress($projectName, $assignedPrograms, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_IN_PROGRESS,
                'ASSIGNED_PROGS' => $assignedPrograms,
                'DATE_START' => now(),
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, 'ASSIGNED', 'Project status updated to In Progress', $assignedPrograms, $updatedBy);
    }

    /**
     * Update project status to ON_HOLD (4)
     */
    public function updateToOnHold($projectName, $updatedBy)
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

        $this->logAction($project->PROJ_ID, 'ON_HOLD', 'Project status updated to On Hold', null, $updatedBy);
    }

    /**
     * Update project status to DEPLOYED (5)
     */
    public function updateToDeployed($projectName, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_DEPLOYED,
                'DATE_END' => now(),
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, 'DEPLOYED', 'Project status updated to Deployed', null, $updatedBy);
    }

    /**
     * Update project status to CANCELLED (6)
     */
    public function updateToCancelled($projectName, $updatedBy)
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

        $this->logAction($project->PROJ_ID, 'CANCELLED', 'Project status updated to Cancelled', null, $updatedBy);
    }

    /**
     * Update project status to INACTIVE (7)
     */
    public function updateToInactive($projectName, $updatedBy)
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

        $this->logAction($project->PROJ_ID, 'INACTIVE', 'Project status updated to Inactive', null, $updatedBy);
    }

    /**
     * Log project actions in project_logs
     */
    public function logAction($projId, $actionType, $description, $assignedProgs, $actionBy)
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
            'ACTION_BY' => $actionBy,
            'UPDATE_AT' => now(),
        ]);
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
        // Log::info('File received:', [
        //     'hasFile' => $request->hasFile('excel_file'),
        //     'file' => $request->file('excel_file'),
        // ]);

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
                    // // For debugging without stopping execution:
                    // Log::info('Import data:', ['data' => $data, 'userId' => $userId]);
                    // Process the row
                    $result = $this->processImportRow($data, $userId);
                    // dd($result);
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
        // dd($data, $userId);
        // Check if project exists (by PROJ_ID if provided, or by PROJ_NAME)
        $existingProject = null;

        if (!empty($processedData['PROJ_ID'])) {
            $existingProject = DB::connection('projects')
                ->table('project_list')
                ->where('PROJ_ID', $processedData['PROJ_ID'])
                ->first();
        } else {
            // Check by project name if no ID provided
            $existingProject = DB::connection('projects')
                ->table('project_list')
                ->where('PROJ_NAME', $processedData['PROJ_NAME'])
                ->first();
        }

        if ($existingProject) {
            // Update existing project
            DB::connection('projects')->table('project_list')
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
            DB::connection('projects')->table('project_list')->insert([
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
        // If already numeric, validate and return
        if (is_numeric($status)) {
            $numericStatus = (int)$status;
            if (in_array($numericStatus, [1, 2, 3, 4, 5, 6])) {
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

        throw new \Exception("Invalid status: $status. Valid options: " . implode(', ', array_keys($this->statusMapping)) . " or numeric values 1-6");
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
