<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\ProjectService;
use App\Constants\ProjectConstants;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }
    public function store(Request $request)
    {
        try {
            $empData = session('emp_data');
            if (!$empData) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'department' => 'required|string|max:100',
                'handler_ids' => 'required|array',
                'handler_ids.*' => 'string|max:20',
                'target_deadline' => 'nullable|date',
                'status' => 'required|integer',
            ]);

            // Create the project using ProjectService
            $projectId = $this->projectService->createProject(
                $validated,
                $empData['emp_id']
            );

            return redirect()->route('project.list')
                ->with('success', 'Project created successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project: ' . $e->getMessage()
            ], 500);
        }
    }
    public function getProjectsDataTable(Request $request)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        // Handle encoded parameters
        $encoded = $request->input('q', '');
        if ($encoded) {
            $decodedParams = json_decode(base64_decode($encoded), true);
            if (is_array($decodedParams)) {
                $request->merge($decodedParams);
            }
        }

        try {
            $showAllDepartments = $this->isMISOrODRole($empData);

            if (!$showAllDepartments) {
                $request->merge(['department' => $empData['emp_dept']]);
            }

            $result = $this->projectService->getProjectsDataTable($request);

            $result['showAllDepartments'] = $showAllDepartments;
            $result['canEditAssignedTo'] = $this->isMISSeniorSupervisor($empData);

            return Inertia::render('Projects/Table', $result)
                ->with('flash', ['message' => 'Projects loaded successfully']);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to load projects: ' . $e->getMessage());
        }
    }
    public function update(Request $request, $projectId)
    {
        // dd($request->all());
        try {
            $empData = session('emp_data');
            if (!$empData) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'department' => 'required|string|max:100',
                'handler_ids' => 'required|array',
                'handler_ids.*' => 'string|max:20',
                'target_deadline' => 'nullable|date',
                'status' => 'required|integer',
            ]);

            $this->projectService->updateProject(
                $projectId,
                $validated,
                $empData['emp_id']
            );

            return redirect()->route('project.list')
                ->with('success', 'Project updated successfully');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project: ' . $e->getMessage()
            ], 500);
        }
    }
    public function getProgrammers()
    {
        try {
            $programmers = $this->projectService->getProgrammers();
            return response()->json([
                'success' => true,
                'programmers' => $programmers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateAssignedTo(Request $request, $projectId)
    {
        try {
            $empData = session('emp_data');
            if (!$empData) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            if (!$this->isMISSeniorSupervisor($empData)) {
                return response()->json(['error' => 'Forbidden: Only MIS Senior Supervisor can reassign projects'], 403);
            }

            $validated = $request->validate([
                'assigned_ids' => 'required|array',
                'assigned_ids.*' => 'string|max:20',
            ]);

            $this->projectService->updateAssignedTo(
                $projectId,
                $validated['assigned_ids'],
                $empData['emp_id']
            );

            return response()->json(['success' => true, 'message' => 'Assigned programmers updated successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assigned programmers: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getHandlerOptionsByDepartment($department)
    {
        try {
            Log::info('Department received:', ['department' => $department]);

            $department = trim($department);

            // Fetch handlers for that department
            $handlers = $this->projectService->getHandlerOptions([$department]);

            return response()->json([
                'success' => true,
                'handlers' => $handlers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getAllDepartments()
    {
        try {
            $departments = $this->projectService->getAllDepartments();

            return response()->json([
                'success' => true,
                'departments' => $departments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function isProgrammer($empData)
    {
        $dept = strtoupper($empData['emp_dept'] ?? '');
        $jobTitle = strtolower($empData['emp_jobtitle'] ?? '');

        return $dept === 'MIS' &&
            (
                strpos($jobTitle, 'programmer') !== false ||
                (strpos($jobTitle, 'mis') !== false && strpos($jobTitle, 'supervisor') !== false)
            );
    }

    private function isMISSupervisor($empData)
    {
        $dept = strtoupper($empData['emp_dept'] ?? '');
        $jobTitle = strtolower($empData['emp_jobtitle'] ?? '');

        return $dept === 'MIS' && strpos($jobTitle, 'supervisor') !== false;
    }

    private function isMISSeniorSupervisor($empData)
    {
        $dept = strtoupper($empData['emp_dept'] ?? '');
        $jobTitle = strtolower($empData['emp_jobtitle'] ?? '');

        return $dept === 'MIS' &&
            strpos($jobTitle, 'senior') !== false &&
            strpos($jobTitle, 'supervisor') !== false;
    }

    private function isMisManager($empData)
    {
        $dept = strtoupper($empData['emp_dept'] ?? '');
        $position = $empData['emp_position'] ?? 0;

        return stripos($dept, 'MIS') !== false && $position == 4;
    }

    private function isODAccount($empData)
    {
        $dept = strtoupper($empData['emp_dept'] ?? '');
        $jobTitle = strtoupper($empData['emp_jobtitle'] ?? '');

        return $dept === 'OPERATIONS' || $jobTitle === 'OPERATIONS DIRECTOR';
    }
    private function isMISRole($empData)
    {
        return $this->isProgrammer($empData) || $this->isMISSupervisor($empData) || $this->isMisManager($empData);
    }
    /**
     * Returns true if the user is a MIS role (Programmer, Supervisor, Manager)
     */
    private function isMISOrODRole($empData)
    {
        return $this->isMISRole($empData) || $this->isODAccount($empData);
    }



    public function getProjectLogs($projectId)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $logs = $this->projectService->getProjectLogs($projectId);
            return response()->json($logs);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load project logs'], 500);
        }
    }

    public function createFromTicket($projectName, $description, $department, $requestorId, $createdBy, $requestType = null, $ticketId = null)
    {
        try {
            $projId = $this->projectService->createFromTicket(
                $projectName,
                $description,
                $department,
                $requestorId,
                $createdBy,
                $requestType,
                $ticketId
            );

            return response()->json(['project_id' => $projId]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getAssignedProjects($empId)
    {
        try {
            $projects = $this->projectService->getAssignedProjects($empId);
            return response()->json($projects);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to load assigned projects'], 500);
        }
    }

    public function updateToReady($projectName, $approvalType, $updatedBy, $requestType = null, $ticketId = null)
    {
        try {
            $this->projectService->updateToReady($projectName, $approvalType, $updatedBy, $requestType, $ticketId);
            return response()->json(['success' => true, 'message' => 'Project status updated to Ready']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateToInProgress($projectName, $assignedPrograms, $updatedBy, $requestType = null, $ticketId = null)
    {
        try {
            $this->projectService->updateToInProgress($projectName, $assignedPrograms, $updatedBy, $requestType, $ticketId);
            return response()->json(['success' => true, 'message' => 'Project status updated to In Progress']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateToDeployed($projectName, $updatedBy, $requestType = null, $ticketId = null)
    {
        try {
            $this->projectService->updateToDeployed($projectName, $updatedBy, $requestType, $ticketId);
            return response()->json(['success' => true, 'message' => 'Project status updated to Deployed']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
            $result = $this->projectService->processExcelImport($file, $userId);

            $message = "Import completed. Inserted: {$result['imported']}, Updated: {$result['updated']}";
            if (!empty($result['errors'])) {
                $message .= ". Errors: " . implode('; ', array_slice($result['errors'], 0, 5));
            }

            return redirect()->route('project.list')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('project.list')
                ->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        try {
            $content = $this->projectService->generateTemplateCsv();

            return response($content)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="project_import_template.csv"');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to download template: ' . $e->getMessage());
        }
    }
}
