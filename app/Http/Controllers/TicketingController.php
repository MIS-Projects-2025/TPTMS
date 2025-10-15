<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Storage;
use App\Services\DataTableService;


class TicketingController extends Controller
{
    // Status Constants
    const STATUS_NEW = 1;
    const STATUS_TRIAGED = 2;
    const STATUS_APPROVED = 3;
    const STATUS_IN_PROGRESS = 4;
    const STATUS_RESOLVED = 5;
    const STATUS_CLOSED = 6;
    const STATUS_REJECTED = 7;
    const STATUS_ON_HOLD = 8;

    // Request Type Constants
    const REQUEST_NEW_SYSTEM = 1;
    const REQUEST_MODIFICATION = 2;
    const REQUEST_ENHANCEMENT = 3;
    const REQUEST_ADJUSTMENT = 4;
    const REQUEST_TESTING = 5;
    const REQUEST_PARALLEL_RUN = 6;

    // Workflow Action Types
    const WORKFLOW_ASSESSED = 'ASSESSED';
    const WORKFLOW_DH_APPROVED = 'DH_APPROVED';
    const WORKFLOW_DH_REJECTED = 'DH_REJECTED';
    const WORKFLOW_OD_APPROVED = 'OD_APPROVED';
    const WORKFLOW_OD_REJECTED = 'OD_REJECTED';
    const WORKFLOW_ASSIGNED = 'ASSIGNED';
    const WORKFLOW_ACKNOWLEDGED = 'ACKNOWLEDGED';
    const WORKFLOW_RESOLVED = 'RESOLVED';
    const WORKFLOW_CLOSED = 'CLOSED';
    const WORKFLOW_RETURNED = 'RETURNED';
    const WORKFLOW_PUT_ON_HOLD = 'PUT_ON_HOLD';
    const WORKFLOW_RESUMED = 'RESUMED';
    public function showTicketForm(): Response
    {
        $empData = session('emp_data');

        // --- Request Types using constants ---
        $requestTypes = [
            ['value' => self::REQUEST_NEW_SYSTEM, 'label' => 'New System'],
            ['value' => self::REQUEST_MODIFICATION, 'label' => 'Modification'],
            ['value' => self::REQUEST_ENHANCEMENT, 'label' => 'Enhancement'],
            ['value' => self::REQUEST_ADJUSTMENT, 'label' => 'Adjustment'],
            ['value' => self::REQUEST_TESTING, 'label' => 'Testing'],
            ['value' => self::REQUEST_PARALLEL_RUN, 'label' => 'Parallel Run'],
        ];

        // --- Parent Ticket Options ---
        $ticketOptions = DB::select('
        SELECT 
            TICKET_ID as value,
            CONCAT(TICKET_ID, " - ", PROJECT_NAME) as label,
            PROJECT_NAME as project_name
        FROM tickets 
        WHERE DELETED_AT IS NULL
        AND TICKET_LEVEL = "parent"
        ORDER BY CREATED_AT DESC
    ');

        // Map ticket_id => project_name
        $ticketProjects = DB::select('
        SELECT 
            TICKET_ID,
            PROJECT_NAME
        FROM tickets 
        WHERE DELETED_AT IS NULL
    ');
        $ticketProjectMap = [];
        foreach ($ticketProjects as $ticket) {
            $ticketProjectMap[$ticket->TICKET_ID] = $ticket->PROJECT_NAME;
        }

        // Employee Options
        $employeeOptions = DB::connection('masterlist')->select("
        SELECT 
            EMPLOYID as value,
            CONCAT(EMPLOYID, ' - ', EMPNAME) as label
        FROM employee_masterlist 
        WHERE ACCSTATUS = 1 
        AND EMPLOYID != 0
        ORDER BY EMPNAME ASC
    ");

        // Project Options
        $projectOptions = DB::connection('projects')->select("
        SELECT
            PROJ_NAME as value,
            PROJ_NAME as label
        FROM project_list
    ");

        return Inertia::render('Ticketing/Create', [
            'requestTypes' => $requestTypes,          // Pass request types here
            'ticketOptions' => $ticketOptions,
            'ticketProjects' => $ticketProjectMap,
            'employeeOptions' => $employeeOptions,
            'projectOptions' => $projectOptions,
            'userAccountType' => $this->getUserAccountType($empData)
        ]);
    }
    public function viewTicket($hash): Response
    {
        $decodedData = base64_decode($hash);
        $parts = explode(':', $decodedData);

        if (count($parts) === 2) {
            [$ticketId, $action] = $parts;
        } elseif (count($parts) === 3) {
            [$ticketId, $action, $userAccountType] = $parts;
        } else {
            abort(400, 'Invalid hash format');
        }

        $ticket = DB::selectOne('
        SELECT * FROM tickets 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
    ', [$ticketId]);

        if (!$ticket) {
            abort(404, 'Ticket not found');
        }

        $attachments = DB::select('
        SELECT * FROM ticket_attachments 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
        ORDER BY UPLOADED_AT DESC
    ', [$ticket->ID]);

        // Get current user data
        $empData = session('emp_data');
        $userRoles = $this->getUserAccountType($empData);

        // Map request type constants to labels
        $requestTypes = [
            1 => 'New System',
            2 => 'Modification',
            3 => 'Enhancement',
            4 => 'Adjustment',
            5 => 'Testing',
            6 => 'Parallel Run',
        ];

        // Map status constants
        $statusTypes = [
            1 => 'New',
            2 => 'Triaged',
            3 => 'Approved',
            4 => 'In Progress',
            5 => 'Resolved',
            6 => 'Closed',
            7 => 'Rejected',
            8 => 'On Hold',
        ];

        // Determine available actions for current user
        $availableActions = [];
        $possibleActions = ['ASSESS', 'RETURN', 'DH_APPROVE', 'OD_APPROVE', 'ASSIGN', 'RESOLVE', 'CLOSE', 'TEST'];

        foreach ($possibleActions as $possibleAction) {
            if ($this->canPerformAction($ticket, $possibleAction, $empData, $userRoles)) {
                $availableActions[] = $possibleAction;
            }
        }

        // Get workflow stage information
        $workflowStage = $this->getCurrentWorkflowStage($ticket->ID);

        // Get ticket history from default connection
        $ticketHistory = DB::select('
        SELECT 
            tw.*
        FROM ticket_workflow tw
        WHERE tw.TICKET_ID = ?
        ORDER BY tw.ACTION_AT DESC
    ', [$ticket->ID]);

        // Enrich with employee names from masterlist
        $ticketHistory = $this->enrichWithEmployeeNames($ticketHistory, 'ACTION_BY');
        foreach ($ticketHistory as $history) {
            $history->action_by_name = $history->employee_display;
        }

        // Get remarks history from default connection
        $remarksHistory = DB::select('
        SELECT 
            rh.*
        FROM remarks_history rh
        WHERE rh.TICKET_ID = ?
        ORDER BY rh.CREATED_AT DESC
    ', [$ticket->ID]);

        // Enrich with employee names from masterlist
        $remarksHistory = $this->enrichWithEmployeeNames($remarksHistory, 'CREATED_BY');
        foreach ($remarksHistory as $remark) {
            $remark->created_by_name = $remark->employee_display;
        }

        // Get assigned employee names if ticket is assigned
        $assignedEmployees = [];
        if (!empty($ticket->ASSIGNED_TO)) {
            $assignedEmployees = $this->getAssignedEmployeeNames($ticket->ASSIGNED_TO);
        }

        // Get programmer options for assignment action
        $programmerOptions = [];
        if (in_array('ASSIGN', $availableActions)) {
            $programmerOptions = $this->getProgrammerOptions();
        }

        return Inertia::render('Ticketing/ViewDetails', [
            'ticket' => $ticket,
            'action' => $action,
            'attachments' => $attachments,
            'requestTypes' => $requestTypes,
            'statusTypes' => $statusTypes,
            'availableActions' => $availableActions,
            'workflowStage' => $workflowStage,
            'ticketHistory' => $ticketHistory,
            'remarksHistory' => $remarksHistory,
            'assignedEmployees' => $assignedEmployees,
            'programmerOptions' => $programmerOptions,
            'userRoles' => $userRoles,
        ]);
    }
    public function store(Request $request)
    {
        // dd($request->all());
        // Validation
        $validated = $request->validate([
            'request_type' => 'required|integer|in:1,2,3,4,5,6',
            'project' => 'required_unless:request_type,1|nullable|string|max:255',
            'project_name' => 'required_if:request_type,1|nullable|string|max:255',
            'parent_ticket' => 'nullable|string|exists:tickets,TICKET_ID',
            'testers' => 'nullable|array',
            'testers.*' => 'string|max:20',
            'details' => 'required|string|min:10',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf,doc,docx,ppt,pptx|max:10240',
        ]);

        DB::beginTransaction();

        try {
            $empData = session('emp_data');
            $workflowPath = $this->getRequiredWorkflowPath($validated['request_type']);

            // Determine ticket level and ID
            $isChildTicket = !empty($validated['parent_ticket']);
            $ticketLevel = $isChildTicket ? 'child' : 'parent';

            if ($isChildTicket) {
                $ticketId = $this->generateChildTicketId($validated['parent_ticket']);
            } else {
                $ticketId = $this->generateTicketNumber();
            }

            // Determine initial status based on request type
            if ($workflowPath['can_direct_assign']) {
                // Testing/Parallel Run: Direct to NEW (waiting for assignment)
                $initialStatus = self::STATUS_NEW;
            } else {
                // Other requests: Direct to NEW (waiting for triage/assessment)
                $initialStatus = self::STATUS_NEW;
            }

            // Determine project name
            $projectName = null;
            if ($validated['request_type'] == self::REQUEST_NEW_SYSTEM) {
                // New System - use provided project name
                $projectName = $validated['project_name'];
            } elseif (!empty($validated['project'])) {
                // Existing project - use selected project
                $projectName = $validated['project'];
            } elseif (!empty($validated['parent_ticket'])) {
                // Child ticket - inherit from parent
                $parentTicket = DB::selectOne('
                SELECT PROJECT_NAME 
                FROM tickets 
                WHERE TICKET_ID = ? 
                AND DELETED_AT IS NULL
            ', [$validated['parent_ticket']]);

                if ($parentTicket) {
                    $projectName = $parentTicket->PROJECT_NAME;
                }
            }

            // Insert into tickets table
            $ticketDbId = DB::table('tickets')->insertGetId([
                'TICKET_ID' => $ticketId,
                'TICKET_LEVEL' => $ticketLevel,
                'PARENT_TICKET_ID' => $validated['parent_ticket'] ?? null,
                'TYPE_OF_REQUEST' => $validated['request_type'],
                'PROJECT_NAME' => $projectName,
                'DETAILS' => $validated['details'],
                'EMPLOYID' => $empData['emp_id'],
                'EMPNAME' => $empData['emp_name'],
                'DEPARTMENT' => $empData['emp_dept'],
                'STATUS' => $initialStatus,
                'ASSIGNED_TO' => null,
                'CREATED_AT' => now(),
                'UPDATED_AT' => now(),
                'DELETED_AT' => null,
            ]);

            // Insert testers for Testing/Parallel Run requests
            if (in_array($validated['request_type'], [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
                if (!empty($validated['testers'])) {
                    foreach ($validated['testers'] as $testerId) {
                        DB::table('ticket_testers')->insert([
                            'TICKET_ID' => $ticketDbId,
                            'TESTER_ID' => $testerId,
                            'ASSIGNED_AT' => now(),
                            'STATUS' => 'PENDING',
                        ]);
                    }
                }
            }

            // Handle file attachments
            if ($request->hasFile('attachments')) {
                $this->handleAttachments(
                    $request->file('attachments'),
                    $ticketId,
                    $empData['emp_id']
                );
            }

            // Insert into tickets_history (CREATE action)
            $this->logTicketHistory(
                $ticketDbId,
                'CREATE',
                null,
                null,
                json_encode([
                    'ticket_id' => $ticketId,
                    'request_type' => $this->getRequestTypeLabel($validated['request_type']),
                    'project_name' => $projectName,
                    'status' => $this->getStatusLabel($initialStatus),
                ]),
                $empData['emp_id']
            );

            // Insert initial remark
            $initialRemarkText = $workflowPath['can_direct_assign']
                ? "Ticket created. Awaiting assignment by programmer."
                : "Ticket created. Awaiting triage by programmer.";

            $this->insertRemark(
                $ticketDbId,
                $empData['emp_id'],
                'CREATION',
                $initialRemarkText,
                null,
                $initialStatus,
                null,
                null,
                false
            );

            // NOTE: ticket_workflow is NOT inserted here
            // It will only be inserted when workflow actions occur:
            // - ASSESSED (when programmer assesses)
            // - DH_APPROVED (when DH approves)
            // - OD_APPROVED (when OD approves)
            // - ASSIGNED (when assigned to programmer)
            // - etc.

            DB::commit();

            return redirect()
                ->route('tickets.show', $ticketId)
                ->with('success', 'Ticket created successfully! Ticket ID: ' . $ticketId);
        } catch (\Exception $e) {
            DB::rollBack();

            // \Log::error('Ticket creation failed', [
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString(),
            //     'request' => $request->all()
            // ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to create ticket: ' . $e->getMessage())
                ->withInput();
        }
    }
    public function getTicketsDataTable(Request $request)
    {
        $empData = session('emp_data');
        $userRoles = $this->getUserAccountType($empData);

        // DataTable params
        $search = $request->input('search.value');
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $orderCol = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        $columns = $request->input('columns', []);

        $query = DB::table('tickets')->whereNull('DELETED_AT');

        // Filter by status/project
        if ($request->filled('status')) {
            $query->where('STATUS', $request->input('status'));
        }
        if ($request->filled('project')) {
            $query->where('PROJECT_NAME', $request->input('project'));
        }

        $userId = $empData['emp_id'];

        // Get approver tickets
        $approverIds = [];
        if (in_array('DEPARTMENT_HEAD', $userRoles)) {
            $approverIds = DB::connection('masterlist')
                ->table('employee_masterlist')
                ->whereRaw("? IN (APPROVER1, APPROVER2, APPROVER3)", [$userId])
                ->pluck('EMPLOYID')
                ->toArray();
        }

        // Get tickets where user is assigned as tester
        $testerTicketIds = DB::table('ticket_testers')
            ->where('TESTER_ID', $userId)
            ->whereNull('DELETED_AT')
            ->pluck('TICKET_ID')
            ->toArray();

        // Apply role-based filters
        $query->where(function ($q) use ($userRoles, $userId, $approverIds, $testerTicketIds) {
            // Programmers or MIS supervisors: only new tickets
            if (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles)) {
                $q->orWhere('STATUS', 1);
            }

            // Department head: only tickets of employees they approve
            if (in_array('DEPARTMENT_HEAD', $userRoles) && !empty($approverIds)) {
                $q->orWhereIn('EMPLOYID', $approverIds);
            }

            // OD sees all tickets
            if (in_array('OD', $userRoles)) {
                $q->orWhereRaw('1 = 1'); // no filter
            }

            if (in_array('MIS_SUPERVISOR', $userRoles)) {
                $q->orWhere('STATUS', 3);
            }

            // Always include requestor's own tickets
            $q->orWhere('EMPLOYID', $userId);

            // Include tickets assigned to user as programmer
            $q->orWhere('ASSIGNED_TO', $userId);

            // Include tickets where user is assigned as tester
            if (!empty($testerTicketIds)) {
                $q->orWhereIn('tickets.ID', $testerTicketIds);
            }
        });

        // Global search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('TICKET_ID', 'like', "%{$search}%")
                    ->orWhere('PROJECT_NAME', 'like', "%{$search}%")
                    ->orWhere('DETAILS', 'like', "%{$search}%");
            });
        }

        // Sorting
        $orderBy = $columns[$orderCol]['data'] ?? 'CREATED_AT';
        $query->orderBy($orderBy, $orderDir);

        $total = $query->count();
        $tickets = $query->offset($start)->limit($length)->get();

        // Map actions
        $data = [];
        foreach ($tickets as $ticket) {
            $actions = [];

            // Check if user is a tester for this ticket
            $isTester = in_array($ticket->ID, $testerTicketIds);

            if ($this->canPerformAction($ticket, 'ASSESS', $empData, $userRoles)) {
                $actions[] = 'Assess';
            }
            if ($this->canPerformAction($ticket, 'DH_APPROVE', $empData, $userRoles)) {
                $actions[] = 'Approve';
            }
            if ($this->canPerformAction($ticket, 'OD_APPROVE', $empData, $userRoles)) {
                $actions[] = 'Approve';
            }
            if ($this->canPerformAction($ticket, 'ASSIGN', $empData, $userRoles)) {
                $actions[] = 'Assign';
            }
            if ($this->canPerformAction($ticket, 'RESOLVE', $empData, $userRoles)) {
                $actions[] = 'Resolve';
            }
            if ($this->canPerformAction($ticket, 'CLOSE', $empData, $userRoles)) {
                $actions[] = 'Close';
            }

            // Add test action for testers
            if ($isTester && in_array($ticket->STATUS, [self::STATUS_NEW, self::STATUS_RESOLVED])) {
                $actions[] = 'Test';
            }

            $data[] = [
                'ticket_id' => $ticket->TICKET_ID,
                'emp_name' => $ticket->EMPNAME,
                'project_name' => $ticket->PROJECT_NAME,
                'type_of_request' => $this->getRequestTypeLabel($ticket->TYPE_OF_REQUEST),
                'status' => $this->getStatusLabel($ticket->STATUS),
                'created_at' => $ticket->CREATED_AT,
                'actions' => $actions,
                'is_tester' => $isTester, // Flag to indicate if user is a tester
            ];
        }

        return Inertia::render('Ticketing/Table', [
            'tickets' => $data,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'draw' => intval($request->input('draw')),
            'filters' => $request->all(),
        ]);
    }
    /**
     * Get employee names from masterlist database
     * Returns array with EMPLOYID as key and EMPNAME as value
     */
    private function getEmployeeNames(array $employeeIds)
    {
        // Remove duplicates, nulls, and non-numeric values
        $employeeIds = array_filter(array_unique($employeeIds), fn($id) => is_numeric($id));

        if (empty($employeeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));

        $employees = DB::connection('masterlist')->select(
            "SELECT EMPLOYID, EMPNAME 
         FROM employee_masterlist 
         WHERE EMPLOYID IN ($placeholders)",
            array_values($employeeIds) // ensure sequential numeric keys
        );

        $result = [];
        foreach ($employees as $emp) {
            $result[$emp->EMPLOYID] = $emp->EMPNAME;
        }

        return $result;
    }


    /**
     * Enrich data with employee names from masterlist
     * Adds employee_name field to each item
     */
    private function enrichWithEmployeeNames(array $data, string $employeeIdField = 'EMPLOYID')
    {
        if (empty($data)) {
            return $data;
        }

        // Extract all employee IDs
        $employeeIds = array_column($data, $employeeIdField);

        // Get employee names
        $employeeNames = $this->getEmployeeNames($employeeIds);

        // Add employee names to data
        foreach ($data as $item) {
            $empId = $item->{$employeeIdField};
            $item->employee_name = $employeeNames[$empId] ?? 'Unknown';
            $item->employee_display = ($employeeNames[$empId] ?? 'Unknown') . " ($empId)";
        }

        return $data;
    }

    /**
     * Get formatted employee display name
     */
    private function getEmployeeDisplayName($employeeId)
    {
        if (empty($employeeId)) {
            return 'Unknown';
        }

        $employee = DB::connection('masterlist')->selectOne("
        SELECT EMPNAME 
        FROM employee_masterlist 
        WHERE EMPLOYID = ?
    ", [$employeeId]);

        if ($employee) {
            return $employee->EMPNAME . " ($employeeId)";
        }

        return "Unknown ($employeeId)";
    }

    /**
     * Get multiple assigned employee names from comma-separated string
     */
    private function getAssignedEmployeeNames($assignedToString)
    {
        if (empty($assignedToString)) {
            return [];
        }

        $employeeIds = $this->extractMultipleEmployeeIds($assignedToString);
        $employeeNames = $this->getEmployeeNames($employeeIds);

        $result = [];
        foreach ($employeeIds as $id) {
            $result[] = [
                'id' => $id,
                'name' => $employeeNames[$id] ?? 'Unknown',
                'display' => ($employeeNames[$id] ?? 'Unknown') . " ($id)"
            ];
        }

        return $result;
    }
    /**
     * Get list of programmers for assignment dropdown
     */
    private function getProgrammerOptions()
    {
        // Get all MIS employees who are programmers
        $programmers = DB::connection('masterlist')->select("
        SELECT 
            EMPLOYID as value,
            CONCAT(EMPLOYID, ' - ', EMPNAME) as label,
            EMPNAME as name,
            JOB_TITLE
        FROM employee_masterlist 
        WHERE ACCSTATUS = 1 
        AND EMPLOYID != 0
        AND UPPER(DEPARTMENT) = 'MIS'
        AND (
            UPPER(JOB_TITLE) LIKE '%PROGRAMMER%'
            OR UPPER(JOB_TITLE) LIKE '%DEVELOPER%'
        )
        ORDER BY EMPNAME ASC
    ");

        return $programmers;
    }

    /**
     * Check if employee is a programmer
     */
    private function isProgrammer($employeeId)
    {
        $employee = DB::connection('masterlist')->selectOne("
        SELECT JOB_TITLE, DEPARTMENT
        FROM employee_masterlist 
        WHERE EMPLOYID = ? 
        AND ACCSTATUS = 1
    ", [$employeeId]);

        if (!$employee) {
            return false;
        }

        $dept = strtoupper($employee->DEPARTMENT);
        $job_Title = strtoupper($employee->JOB_TITLE);

        return $dept === 'MIS' && (
            strpos($job_Title, 'PROGRAMMER') !== false ||
            strpos($job_Title, 'DEVELOPER') !== false ||
            strpos($job_Title, 'MIS') !== false
        );
    }
    /**
     * Determine required workflow path based on request type
     */
    private function getRequiredWorkflowPath($requestType)
    {
        switch ($requestType) {
            case self::REQUEST_NEW_SYSTEM:
            case self::REQUEST_MODIFICATION:
            case self::REQUEST_ENHANCEMENT:
                // Full workflow: Assess → DH → OD → Assign
                return [
                    'requires_assessment' => true,
                    'requires_dh_approval' => true,
                    'requires_od_approval' => true,
                    'can_direct_assign' => false,
                    'workflow_type' => 'FULL_APPROVAL'
                ];

            case self::REQUEST_ADJUSTMENT:
                // Simplified workflow: Assess → DH → Assign (no OD)
                return [
                    'requires_assessment' => true,
                    'requires_dh_approval' => true,
                    'requires_od_approval' => false,
                    'can_direct_assign' => false,
                    'workflow_type' => 'DH_APPROVAL_ONLY'
                ];

            case self::REQUEST_TESTING:
            case self::REQUEST_PARALLEL_RUN:
                // Direct assignment: Programmer can directly assign
                return [
                    'requires_assessment' => false,
                    'requires_dh_approval' => false,
                    'requires_od_approval' => false,
                    'can_direct_assign' => true,
                    'workflow_type' => 'DIRECT_ASSIGN'
                ];

            default:
                return [
                    'requires_assessment' => true,
                    'requires_dh_approval' => true,
                    'requires_od_approval' => true,
                    'can_direct_assign' => false,
                    'workflow_type' => 'FULL_APPROVAL'
                ];
        }
    }

    /**
     * Get request type label
     */
    private function getRequestTypeLabel($requestType)
    {
        $labels = [
            self::REQUEST_NEW_SYSTEM => 'New System Request',
            self::REQUEST_MODIFICATION => 'Modification Request',
            self::REQUEST_ENHANCEMENT => 'Enhancement Request',
            self::REQUEST_ADJUSTMENT => 'Adjustment Request',
            self::REQUEST_TESTING => 'Testing Request',
            self::REQUEST_PARALLEL_RUN => 'Parallel Run Request',
        ];

        return $labels[$requestType] ?? 'Unknown Request Type';
    }

    /**
     * Check if all required approvals are complete for a ticket
     */
    private function areApprovalsComplete($ticketId, $requestType)
    {
        $workflow = $this->getRequiredWorkflowPath($requestType);

        if ($workflow['can_direct_assign']) {
            return true;
        }

        $workflowHistory = DB::table('ticket_workflow')
            ->where('TICKET_ID', $ticketId)
            ->whereIn('ACTION_TYPE', [
                self::WORKFLOW_ASSESSED,
                self::WORKFLOW_DH_APPROVED,
                self::WORKFLOW_OD_APPROVED
            ])
            ->pluck('ACTION_TYPE')
            ->toArray();

        $hasAssessment = in_array(self::WORKFLOW_ASSESSED, $workflowHistory);
        $hasDHApproval = in_array(self::WORKFLOW_DH_APPROVED, $workflowHistory);
        $hasODApproval = in_array(self::WORKFLOW_OD_APPROVED, $workflowHistory);

        if ($workflow['requires_assessment'] && !$hasAssessment) {
            return false;
        }

        if ($workflow['requires_dh_approval'] && !$hasDHApproval) {
            return false;
        }

        if ($workflow['requires_od_approval'] && !$hasODApproval) {
            return false;
        }

        return true;
    }

    /**
     * Get current workflow stage with request type context
     */
    private function getCurrentWorkflowStage($ticketId)
    {
        $ticket = DB::selectOne('
            SELECT STATUS, TYPE_OF_REQUEST 
            FROM tickets 
            WHERE TICKET_ID = ?
        ', [$ticketId]);

        if (!$ticket) {
            return null;
        }

        $workflowPath = $this->getRequiredWorkflowPath($ticket->TYPE_OF_REQUEST);

        $workflow = DB::table('ticket_workflow')
            ->where('TICKET_ID', $ticketId)
            ->orderBy('ACTION_AT', 'desc')
            ->first();

        $pendingAction = $this->getPendingAction(
            $ticket->STATUS,
            $ticket->TYPE_OF_REQUEST,
            $ticketId
        );

        return [
            'status' => $ticket->STATUS,
            'status_label' => $this->getStatusLabel($ticket->STATUS),
            'request_type' => $ticket->TYPE_OF_REQUEST,
            'request_type_label' => $this->getRequestTypeLabel($ticket->TYPE_OF_REQUEST),
            'workflow_type' => $workflowPath['workflow_type'],
            'last_action' => $workflow ? $workflow->ACTION_TYPE : null,
            'last_action_by' => $workflow ? $workflow->ACTION_BY : null,
            'last_action_at' => $workflow ? $workflow->ACTION_AT : null,
            'pending_action' => $pendingAction,
            'can_direct_assign' => $workflowPath['can_direct_assign'],
        ];
    }

    /**
     * Get pending action description based on status and request type
     */
    private function getPendingAction($status, $requestType, $ticketId = null)
    {
        $workflowPath = $this->getRequiredWorkflowPath($requestType);

        // For Testing and Parallel Run requests
        if ($workflowPath['can_direct_assign']) {
            $actions = [
                self::STATUS_NEW => 'Awaiting direct assignment by programmer',
                self::STATUS_APPROVED => 'Awaiting assignment',
                self::STATUS_IN_PROGRESS => 'Work in progress',
                self::STATUS_RESOLVED => 'Awaiting verification by requestor',
                self::STATUS_CLOSED => 'Completed',
                self::STATUS_REJECTED => 'Rejected',
                self::STATUS_ON_HOLD => 'On hold',
            ];
            return $actions[$status] ?? 'Unknown';
        }

        // For requests requiring approvals
        if ($status === self::STATUS_TRIAGED && $ticketId) {
            $workflowHistory = DB::table('ticket_workflow')
                ->where('TICKET_ID', $ticketId)
                ->whereIn('ACTION_TYPE', [
                    self::WORKFLOW_ASSESSED,
                    self::WORKFLOW_DH_APPROVED,
                    self::WORKFLOW_OD_APPROVED
                ])
                ->pluck('ACTION_TYPE')
                ->toArray();

            $hasAssessment = in_array(self::WORKFLOW_ASSESSED, $workflowHistory);
            $hasDHApproval = in_array(self::WORKFLOW_DH_APPROVED, $workflowHistory);

            if (!$hasAssessment) {
                return 'Awaiting assessment by programmer';
            }

            if ($workflowPath['requires_dh_approval'] && !$hasDHApproval) {
                return 'Awaiting Department Head approval';
            }

            if ($workflowPath['requires_od_approval']) {
                return 'Awaiting Operations Director approval';
            }
        }

        $actions = [
            self::STATUS_NEW => 'Awaiting triage by programmer',
            self::STATUS_TRIAGED => 'In approval process',
            self::STATUS_APPROVED => 'Awaiting assignment by MIS Supervisor',
            self::STATUS_IN_PROGRESS => 'Work in progress',
            self::STATUS_RESOLVED => 'Awaiting verification by requestor',
            self::STATUS_CLOSED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_ON_HOLD => 'On hold',
        ];

        return $actions[$status] ?? 'Unknown';
    }

    /**
     * Get user-friendly status label
     */
    private function getStatusLabel($status)
    {
        $labels = [
            self::STATUS_NEW => 'New',
            self::STATUS_TRIAGED => 'Triaged',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_ON_HOLD => 'On Hold',
        ];

        return $labels[$status] ?? 'Unknown';
    }

    // Add these methods to your TicketingController

    /**
     * Handle ticket assignment (by MIS Supervisor)
     */
    public function assignTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'assigned_to' => 'required|array',
            'assigned_to.*' => 'required|string|max:20',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $ticket = DB::selectOne('
        SELECT ID, TICKET_ID, STATUS, TYPE_OF_REQUEST 
        FROM tickets 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
    ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        $workflowPath = $this->getRequiredWorkflowPath($ticket->TYPE_OF_REQUEST);

        // Check if ticket is in correct status for assignment
        if (!$workflowPath['can_direct_assign'] && $ticket->STATUS !== self::STATUS_APPROVED) {
            return response()->json([
                'error' => 'Ticket must be approved before assignment'
            ], 400);
        }

        $currentUser = session('emp_data');
        $assignedToString = implode(',', $validated['assigned_to']);

        DB::beginTransaction();
        try {
            // Update ticket
            DB::table('tickets')
                ->where('ID', $ticket->ID)
                ->update([
                    'ASSIGNED_TO' => $assignedToString,
                    'STATUS' => self::STATUS_IN_PROGRESS, // Auto-acknowledge
                    'UPDATED_AT' => now(),
                ]);

            // Log workflow action
            $this->logWorkflowAction(
                $ticket->ID,
                self::WORKFLOW_ASSIGNED,
                $currentUser['emp_id'],
                $validated['remarks'] ?? 'Ticket assigned to programmer(s)',
                ['assigned_to' => $validated['assigned_to']]
            );

            // Log ticket history
            $this->logTicketHistory(
                $ticket->ID,
                'ASSIGNMENT',
                'ASSIGNED_TO',
                null,
                $assignedToString,
                $currentUser['emp_id']
            );

            // Insert remark
            $this->insertRemark(
                $ticket->ID,
                $currentUser['emp_id'],
                'ASSIGNMENT',
                $validated['remarks'] ?? 'Ticket assigned and auto-acknowledged',
                $ticket->STATUS,
                self::STATUS_IN_PROGRESS,
                null,
                $assignedToString,
                false
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket assigned successfully. Status changed to In Progress.',
                'next_step' => 'Waiting for programmer to complete and resolve'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Assignment failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle ticket resolution (by assigned programmer)
     */
    public function resolveTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'remarks' => 'required|string|min:10|max:1000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf,doc,docx,ppt,pptx|max:10240',
        ]);

        $ticket = DB::selectOne('
        SELECT ID, TICKET_ID, STATUS, ASSIGNED_TO, EMPLOYID 
        FROM tickets 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
    ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        if ($ticket->STATUS !== self::STATUS_IN_PROGRESS) {
            return response()->json([
                'error' => 'Only tickets in progress can be resolved'
            ], 400);
        }

        $currentUser = session('emp_data');
        $assignedIds = $this->extractMultipleEmployeeIds($ticket->ASSIGNED_TO ?? '');

        // Verify user is assigned to this ticket
        if (!in_array($currentUser['emp_id'], $assignedIds)) {
            return response()->json([
                'error' => 'You are not assigned to this ticket'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Update ticket status to RESOLVED
            DB::table('tickets')
                ->where('ID', $ticket->ID)
                ->update([
                    'STATUS' => self::STATUS_RESOLVED,
                    'RESOLVED_AT' => now(),
                    'UPDATED_AT' => now(),
                ]);

            // Log workflow action
            $this->logWorkflowAction(
                $ticket->ID,
                self::WORKFLOW_RESOLVED,
                $currentUser['emp_id'],
                $validated['remarks']
            );

            // Handle attachments if provided
            if ($request->hasFile('attachments')) {
                $this->handleAttachments(
                    $request->file('attachments'),
                    $ticketId,
                    $currentUser['emp_id']
                );
            }

            // Log ticket history
            $this->logTicketHistory(
                $ticket->ID,
                'RESOLUTION',
                'STATUS',
                $this->getStatusLabel(self::STATUS_IN_PROGRESS),
                $this->getStatusLabel(self::STATUS_RESOLVED),
                $currentUser['emp_id']
            );

            // Insert remark
            $this->insertRemark(
                $ticket->ID,
                $currentUser['emp_id'],
                'RESOLUTION',
                $validated['remarks'],
                self::STATUS_IN_PROGRESS,
                self::STATUS_RESOLVED,
                null,
                null,
                false
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket resolved successfully',
                'next_step' => 'Waiting for requestor to verify and close the ticket'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Resolution failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle ticket closure (by original requestor)
     */
    public function closeTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'remarks' => 'required|string|min:10|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
        ]);

        $ticket = DB::selectOne('
        SELECT ID, TICKET_ID, STATUS, EMPLOYID 
        FROM tickets 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
    ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        if ($ticket->STATUS !== self::STATUS_RESOLVED) {
            return response()->json([
                'error' => 'Only resolved tickets can be closed'
            ], 400);
        }

        $currentUser = session('emp_data');

        // Verify user is the original requestor
        if ($ticket->EMPLOYID !== $currentUser['emp_id']) {
            return response()->json([
                'error' => 'Only the original requestor can close this ticket'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Update ticket status to CLOSED
            DB::table('tickets')
                ->where('ID', $ticket->ID)
                ->update([
                    'STATUS' => self::STATUS_CLOSED,
                    'CLOSED_AT' => now(),
                    'RATING' => $validated['rating'] ?? null,
                    'UPDATED_AT' => now(),
                ]);

            // Log workflow action
            $this->logWorkflowAction(
                $ticket->ID,
                self::WORKFLOW_CLOSED,
                $currentUser['emp_id'],
                $validated['remarks'],
                ['rating' => $validated['rating'] ?? null]
            );

            // Log ticket history
            $this->logTicketHistory(
                $ticket->ID,
                'CLOSURE',
                'STATUS',
                $this->getStatusLabel(self::STATUS_RESOLVED),
                $this->getStatusLabel(self::STATUS_CLOSED),
                $currentUser['emp_id']
            );

            // Insert remark
            $this->insertRemark(
                $ticket->ID,
                $currentUser['emp_id'],
                'CLOSURE',
                $validated['remarks'],
                self::STATUS_RESOLVED,
                self::STATUS_CLOSED,
                null,
                null,
                false
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket closed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Closure failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle ticket return (send back to requestor for clarification)
     */
    public function returnTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'remarks' => 'required|string|min:10|max:1000',
        ]);

        $ticket = DB::selectOne('
        SELECT ID, TICKET_ID, STATUS, TYPE_OF_REQUEST 
        FROM tickets 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
    ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        // Can only return tickets that are in NEW or TRIAGED status
        if (!in_array($ticket->STATUS, [self::STATUS_NEW, self::STATUS_TRIAGED])) {
            return response()->json([
                'error' => 'Ticket cannot be returned in current status'
            ], 400);
        }

        $currentUser = session('emp_data');

        DB::beginTransaction();
        try {
            $oldStatus = $ticket->STATUS;

            // Update ticket - keep status but mark as returned
            DB::table('tickets')
                ->where('ID', $ticket->ID)
                ->update([
                    'STATUS' => self::STATUS_NEW, // Reset to NEW
                    'UPDATED_AT' => now(),
                ]);

            // Log workflow action
            $this->logWorkflowAction(
                $ticket->ID,
                self::WORKFLOW_RETURNED,
                $currentUser['emp_id'],
                $validated['remarks']
            );

            // Log ticket history
            $this->logTicketHistory(
                $ticket->ID,
                'RETURN',
                'STATUS',
                $this->getStatusLabel($oldStatus),
                $this->getStatusLabel(self::STATUS_NEW),
                $currentUser['emp_id']
            );

            // Insert remark
            $this->insertRemark(
                $ticket->ID,
                $currentUser['emp_id'],
                'RETURN',
                $validated['remarks'],
                $oldStatus,
                self::STATUS_NEW,
                null,
                null,
                false
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket returned to requestor for clarification',
                'next_step' => 'Waiting for requestor to update ticket details'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Return failed: ' . $e->getMessage()], 500);
        }
    }
    /**
     * Get tester status for a ticket
     */
    private function getTesterStatus($ticketId, $testerId)
    {
        $tester = DB::selectOne('
        SELECT STATUS, TESTED_AT, REMARKS 
        FROM ticket_testers 
        WHERE TICKET_ID = ? 
        AND TESTER_ID = ? 
        AND DELETED_AT IS NULL
    ', [$ticketId, $testerId]);

        return $tester;
    }

    /**
     * Get all testers for a ticket
     */
    private function getTicketTesters($ticketId)
    {
        $testers = DB::select('
        SELECT 
            tt.*
        FROM ticket_testers tt
        WHERE tt.TICKET_ID = ?
        AND tt.DELETED_AT IS NULL
        ORDER BY tt.ASSIGNED_AT DESC
    ', [$ticketId]);

        // Enrich with employee names
        $testers = $this->enrichWithEmployeeNames($testers, 'TESTER_ID');

        foreach ($testers as $tester) {
            $tester->tester_name = $tester->employee_display;
        }

        return $testers;
    }

    /**
     * Submit test results (by tester)
     */
    public function submitTestResult(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'test_status' => 'required|in:PASSED,FAILED',
            'remarks' => 'required|string|min:10|max:1000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,pdf,doc,docx,ppt,pptx|max:10240',
        ]);

        $ticket = DB::selectOne('
        SELECT ID, TICKET_ID, STATUS, TYPE_OF_REQUEST 
        FROM tickets 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
    ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        // Verify ticket is in testable status
        if (!in_array($ticket->STATUS, [self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED])) {
            return response()->json([
                'error' => 'Ticket is not in a testable status'
            ], 400);
        }

        $currentUser = session('emp_data');
        $userId = $currentUser['emp_id'];

        // Verify user is assigned as tester
        $tester = DB::selectOne('
        SELECT ID, STATUS 
        FROM ticket_testers 
        WHERE TICKET_ID = ? 
        AND TESTER_ID = ? 
        AND DELETED_AT IS NULL
    ', [$ticket->ID, $userId]);

        if (!$tester) {
            return response()->json([
                'error' => 'You are not assigned as a tester for this ticket'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Update tester status
            DB::table('ticket_testers')
                ->where('ID', $tester->ID)
                ->update([
                    'STATUS' => $validated['test_status'],
                    'TESTED_AT' => now(),
                    'REMARKS' => $validated['remarks'],
                    'UPDATED_AT' => now(),
                ]);

            // Handle attachments if provided
            if ($request->hasFile('attachments')) {
                $this->handleAttachments(
                    $request->file('attachments'),
                    $ticketId,
                    $userId
                );
            }

            // Check if all testers have completed testing
            $allTesters = DB::select('
            SELECT STATUS 
            FROM ticket_testers 
            WHERE TICKET_ID = ? 
            AND DELETED_AT IS NULL
        ', [$ticket->ID]);

            $allCompleted = true;
            $allPassed = true;

            foreach ($allTesters as $t) {
                if ($t->STATUS === 'PENDING') {
                    $allCompleted = false;
                    break;
                }
                if ($t->STATUS === 'FAILED') {
                    $allPassed = false;
                }
            }

            // Insert remark for test result
            $remarkText = "Test result: {$validated['test_status']} - {$validated['remarks']}";

            $this->insertRemark(
                $ticket->ID,
                $userId,
                'TESTING',
                $remarkText,
                $ticket->STATUS,
                $ticket->STATUS,
                null,
                null,
                false
            );

            // Log ticket history
            $this->logTicketHistory(
                $ticket->ID,
                'TEST_RESULT',
                'TESTER_STATUS',
                'PENDING',
                $validated['test_status'],
                $userId
            );

            $message = "Test result submitted successfully";
            $nextStep = null;

            if ($allCompleted) {
                if ($allPassed) {
                    $message .= ". All tests passed!";
                    $nextStep = "Ready for resolution";
                } else {
                    $message .= ". Some tests failed - assigned programmer needs to address issues";
                    $nextStep = "Waiting for programmer to fix failed tests";
                }
            } else {
                $nextStep = "Waiting for other testers to complete testing";
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'test_status' => $validated['test_status'],
                'all_completed' => $allCompleted,
                'all_passed' => $allPassed,
                'next_step' => $nextStep
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to submit test result: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update viewTicket method to include tester information
     */
    public function viewTicketWithTesters($hash): Response
    {
        $decodedData = base64_decode($hash);
        $parts = explode(':', $decodedData);

        if (count($parts) === 2) {
            [$ticketId, $action] = $parts;
        } elseif (count($parts) === 3) {
            [$ticketId, $action, $userAccountType] = $parts;
        } else {
            abort(400, 'Invalid hash format');
        }

        $ticket = DB::selectOne('
        SELECT * FROM tickets 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
    ', [$ticketId]);

        if (!$ticket) {
            abort(404, 'Ticket not found');
        }

        $attachments = DB::select('
        SELECT * FROM ticket_attachments 
        WHERE TICKET_ID = ? AND DELETED_AT IS NULL
        ORDER BY UPLOADED_AT DESC
    ', [$ticket->ID]);

        // Get current user data
        $empData = session('emp_data');
        $userRoles = $this->getUserAccountType($empData);

        // Get tester information if applicable
        $ticketTesters = [];
        $userTesterStatus = null;

        if (in_array($ticket->TYPE_OF_REQUEST, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            $ticketTesters = $this->getTicketTesters($ticket->ID);
            $userTesterStatus = $this->getTesterStatus($ticket->ID, $empData['emp_id']);
        }

        // Map request type constants to labels
        $requestTypes = [
            1 => 'New System',
            2 => 'Modification',
            3 => 'Enhancement',
            4 => 'Adjustment',
            5 => 'Testing',
            6 => 'Parallel Run',
        ];

        // Map status constants
        $statusTypes = [
            1 => 'New',
            2 => 'Triaged',
            3 => 'Approved',
            4 => 'In Progress',
            5 => 'Resolved',
            6 => 'Closed',
            7 => 'Rejected',
            8 => 'On Hold',
        ];

        // Determine available actions for current user
        $availableActions = [];
        $possibleActions = ['ASSESS', 'RETURN', 'DH_APPROVE', 'OD_APPROVE', 'ASSIGN', 'RESOLVE', 'CLOSE', 'TEST'];

        foreach ($possibleActions as $possibleAction) {
            if ($this->canPerformAction($ticket, $possibleAction, $empData, $userRoles)) {
                $availableActions[] = $possibleAction;
            }
        }

        // Get workflow stage information
        $workflowStage = $this->getCurrentWorkflowStage($ticket->ID);

        // Get ticket history
        $ticketHistory = DB::select('
        SELECT tw.*
        FROM ticket_workflow tw
        WHERE tw.TICKET_ID = ?
        ORDER BY tw.ACTION_AT DESC
    ', [$ticket->ID]);

        $ticketHistory = $this->enrichWithEmployeeNames($ticketHistory, 'ACTION_BY');
        foreach ($ticketHistory as $history) {
            $history->action_by_name = $history->employee_display;
        }

        // Get remarks history
        $remarksHistory = DB::select('
        SELECT rh.*
        FROM remarks_history rh
        WHERE rh.TICKET_ID = ?
        ORDER BY rh.CREATED_AT DESC
    ', [$ticket->ID]);

        $remarksHistory = $this->enrichWithEmployeeNames($remarksHistory, 'CREATED_BY');
        foreach ($remarksHistory as $remark) {
            $remark->created_by_name = $remark->employee_display;
        }

        // Get assigned employee names
        $assignedEmployees = [];
        if (!empty($ticket->ASSIGNED_TO)) {
            $assignedEmployees = $this->getAssignedEmployeeNames($ticket->ASSIGNED_TO);
        }

        // Get programmer options for assignment action
        $programmerOptions = [];
        if (in_array('ASSIGN', $availableActions)) {
            $programmerOptions = $this->getProgrammerOptions();
        }

        return Inertia::render('Ticketing/ViewDetails', [
            'ticket' => $ticket,
            'action' => $action,
            'attachments' => $attachments,
            'requestTypes' => $requestTypes,
            'statusTypes' => $statusTypes,
            'availableActions' => $availableActions,
            'workflowStage' => $workflowStage,
            'ticketHistory' => $ticketHistory,
            'remarksHistory' => $remarksHistory,
            'assignedEmployees' => $assignedEmployees,
            'programmerOptions' => $programmerOptions,
            'userRoles' => $userRoles,
            'ticketTesters' => $ticketTesters,
            'userTesterStatus' => $userTesterStatus,
        ]);
    }


    /**
     * Update canPerformAction to include new actions
     */
    private function canPerformAction($ticket, $action, $currentUser, $userRoles)
    {
        $status = $ticket->STATUS;
        $userId = $currentUser['emp_id'];
        $requestType = $ticket->TYPE_OF_REQUEST;
        $workflowPath = $this->getRequiredWorkflowPath($requestType);
        // Requestor cannot perform actions on their own tickets
        if ($ticket->EMPLOYID == $userId) {
            // For testing or parallel run, block all actions
            if (in_array($ticket->TYPE_OF_REQUEST, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
                return false;
            }

            // For other request types, allow only CLOSE
            if ($action !== 'CLOSE') {
                return false;
            }
        }


        // For Testing/Parallel Run
        if ($workflowPath['can_direct_assign']) {
            if ($action === 'TEST') {
                // Tester can test if assigned and status is NEW or IN_PROGRESS
                $isTester = DB::table('ticket_testers')
                    ->where('TICKET_ID', $ticket->ID)
                    ->where('TESTER_ID', $userId)
                    ->where('STATUS', 'PENDING')
                    ->whereNull('DELETED_AT')
                    ->exists();
                return $isTester && in_array($status, [self::STATUS_NEW, self::STATUS_IN_PROGRESS]);
            }

            if ($action === 'CLOSE') {
                // Requestor can close if all testers passed
                $allPassed = DB::table('ticket_testers')
                    ->where('TICKET_ID', $ticket->ID)
                    ->where('STATUS', 'FAILED')
                    ->doesntExist();
                return $ticket->EMPLOYID === $userId && $allPassed;
            }
            if ($action === 'RETURN') {
                // Requestor can return for retesting if any failed
                $anyFailed = DB::table('ticket_testers')
                    ->where('TICKET_ID', $ticket->ID)
                    ->where('STATUS', 'FAILED')
                    ->exists();
                return $ticket->EMPLOYID === $userId && $anyFailed;
            }
            // No assignment or programmer actions
            return false;
        }
        switch ($action) {
            case 'ASSESS':
                if (!$workflowPath['requires_assessment']) return false;
                return in_array($status, [self::STATUS_NEW, self::STATUS_TRIAGED]) &&
                    (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles));

            case 'RETURN':
                return in_array($status, [self::STATUS_NEW, self::STATUS_TRIAGED]) &&
                    (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles));

            case 'DH_APPROVE':
                if (!$workflowPath['requires_dh_approval']) return false;
                if ($status !== self::STATUS_TRIAGED) return false;
                if (!in_array('DEPARTMENT_HEAD', $userRoles)) return false;

                if ($workflowPath['requires_assessment']) {
                    $hasAssessment = DB::table('ticket_workflow')
                        ->where('TICKET_ID', $ticket->ID)
                        ->where('ACTION_TYPE', self::WORKFLOW_ASSESSED)
                        ->exists();
                    if (!$hasAssessment) return false;
                }

                $alreadyApproved = DB::table('ticket_workflow')
                    ->where('TICKET_ID', $ticket->ID)
                    ->where('ACTION_TYPE', self::WORKFLOW_DH_APPROVED)
                    ->exists();
                return !$alreadyApproved;

            case 'OD_APPROVE':
                if (!$workflowPath['requires_od_approval']) return false;
                if ($status !== self::STATUS_TRIAGED) return false;
                if (!in_array('OD', $userRoles)) return false;

                $hasDHApproval = DB::table('ticket_workflow')
                    ->where('TICKET_ID', $ticket->ID)
                    ->where('ACTION_TYPE', self::WORKFLOW_DH_APPROVED)
                    ->exists();
                if (!$hasDHApproval) return false;

                $alreadyApproved = DB::table('ticket_workflow')
                    ->where('TICKET_ID', $ticket->ID)
                    ->where('ACTION_TYPE', self::WORKFLOW_OD_APPROVED)
                    ->exists();
                return !$alreadyApproved;

            case 'ASSIGN':
                if ($workflowPath['can_direct_assign']) {
                    return ($status === self::STATUS_NEW || $status === self::STATUS_APPROVED) &&
                        (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles));
                }

                if ($status !== self::STATUS_APPROVED) return false;
                if (!in_array('MIS_SUPERVISOR', $userRoles)) return false;
                return $this->areApprovalsComplete($ticket->ID, $requestType);

            case 'RESOLVE':
            case 'IN_PROGRESS':
                if ($status !== self::STATUS_IN_PROGRESS) return false;
                $assignedIds = $this->extractMultipleEmployeeIds($ticket->ASSIGNED_TO ?? '');
                return in_array($userId, $assignedIds);

            case 'CLOSE':
                return $status === self::STATUS_RESOLVED && $ticket->EMPLOYID === $userId;

            default:
                return false;
        }
    }

    /**
     * Handle ticket assessment (moves to TRIAGED and logs assessment)
     */
    public function assessTicket(Request $request, $ticketId)
    {
        $ticket = DB::selectOne('
            SELECT ID, TICKET_ID, STATUS, TYPE_OF_REQUEST 
            FROM tickets 
            WHERE TICKET_ID = ? AND DELETED_AT IS NULL
        ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        $workflowPath = $this->getRequiredWorkflowPath($ticket->TYPE_OF_REQUEST);

        if (!$workflowPath['requires_assessment']) {
            return response()->json([
                'error' => 'This request type does not require assessment'
            ], 400);
        }

        if ($ticket->STATUS !== self::STATUS_NEW && $ticket->STATUS !== self::STATUS_TRIAGED) {
            return response()->json([
                'error' => 'Ticket cannot be assessed in current status'
            ], 400);
        }

        $currentUser = session('emp_data');

        $this->logWorkflowAction(
            $ticket->ID,
            self::WORKFLOW_ASSESSED,
            $currentUser['emp_id'],
            $request->input('remarks')
        );

        DB::table('tickets')
            ->where('ID', $ticket->ID)
            ->update(['STATUS' => self::STATUS_TRIAGED]);

        $this->insertRemark(
            $ticket->ID,
            $currentUser['emp_id'],
            'ASSESSMENT',
            $request->input('remarks') ?? 'Ticket assessed and ready for approval',
            self::STATUS_NEW,
            self::STATUS_TRIAGED
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket assessed successfully',
            'next_step' => $this->getPendingAction(
                self::STATUS_TRIAGED,
                $ticket->TYPE_OF_REQUEST,
                $ticket->ID
            )
        ]);
    }

    /**
     * Handle direct assignment (for Testing/Parallel Run)
     */
    public function directAssignTicket(Request $request, $ticketId)
    {
        $ticket = DB::selectOne('
            SELECT ID, TICKET_ID, STATUS, TYPE_OF_REQUEST 
            FROM tickets 
            WHERE TICKET_ID = ? AND DELETED_AT IS NULL
        ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        $workflowPath = $this->getRequiredWorkflowPath($ticket->TYPE_OF_REQUEST);

        if (!$workflowPath['can_direct_assign']) {
            return response()->json([
                'error' => 'This request type requires approval workflow'
            ], 400);
        }

        $currentUser = session('emp_data');
        $assignedTo = $request->input('assigned_to');

        DB::table('tickets')
            ->where('ID', $ticket->ID)
            ->update([
                'ASSIGNED_TO' => $assignedTo,
                'STATUS' => self::STATUS_APPROVED
            ]);

        $this->logWorkflowAction(
            $ticket->ID,
            self::WORKFLOW_ASSIGNED,
            $currentUser['emp_id'],
            $request->input('remarks'),
            ['assigned_to' => $assignedTo]
        );

        $this->insertRemark(
            $ticket->ID,
            $currentUser['emp_id'],
            'ASSIGNMENT',
            $request->input('remarks') ?? 'Ticket assigned directly',
            $ticket->STATUS,
            self::STATUS_APPROVED,
            null,
            $assignedTo
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully'
        ]);
    }

    /**
     * Check if ticket needs to auto-transition after approval
     */
    private function checkAndTransitionAfterApproval($ticketId, $requestType)
    {
        $workflowPath = $this->getRequiredWorkflowPath($requestType);

        if ($this->areApprovalsComplete($ticketId, $requestType)) {
            DB::table('tickets')
                ->where('ID', $ticketId)
                ->update(['STATUS' => self::STATUS_APPROVED]);

            return true;
        }

        return false;
    }

    /**
     * Handle DH Approval
     */
    public function approveDH(Request $request, $ticketId)
    {
        $ticket = DB::selectOne('
            SELECT ID, TICKET_ID, STATUS, TYPE_OF_REQUEST 
            FROM tickets 
            WHERE TICKET_ID = ? AND DELETED_AT IS NULL
        ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        $currentUser = session('emp_data');

        $this->logWorkflowAction(
            $ticket->ID,
            self::WORKFLOW_DH_APPROVED,
            $currentUser['emp_id'],
            $request->input('remarks')
        );

        $transitioned = $this->checkAndTransitionAfterApproval(
            $ticket->ID,
            $ticket->TYPE_OF_REQUEST
        );

        $this->insertRemark(
            $ticket->ID,
            $currentUser['emp_id'],
            'APPROVAL',
            $request->input('remarks') ?? 'Approved by Department Head',
            $ticket->STATUS,
            $transitioned ? self::STATUS_APPROVED : $ticket->STATUS
        );

        return response()->json([
            'success' => true,
            'message' => 'Department Head approval recorded',
            'status_changed' => $transitioned,
            'next_step' => $this->getPendingAction(
                $transitioned ? self::STATUS_APPROVED : self::STATUS_TRIAGED,
                $ticket->TYPE_OF_REQUEST,
                $ticket->ID
            )
        ]);
    }

    /**
     * Handle OD Approval
     */
    public function approveOD(Request $request, $ticketId)
    {
        $ticket = DB::selectOne('
            SELECT ID, TICKET_ID, STATUS, TYPE_OF_REQUEST 
            FROM tickets 
            WHERE TICKET_ID = ? AND DELETED_AT IS NULL
        ', [$ticketId]);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket not found'], 404);
        }

        $currentUser = session('emp_data');

        $this->logWorkflowAction(
            $ticket->ID,
            self::WORKFLOW_OD_APPROVED,
            $currentUser['emp_id'],
            $request->input('remarks')
        );

        DB::table('tickets')
            ->where('ID', $ticket->ID)
            ->update(['STATUS' => self::STATUS_APPROVED]);

        $this->insertRemark(
            $ticket->ID,
            $currentUser['emp_id'],
            'APPROVAL',
            $request->input('remarks') ?? 'Approved by Operations Director',
            self::STATUS_TRIAGED,
            self::STATUS_APPROVED
        );

        return response()->json([
            'success' => true,
            'message' => 'Operations Director approval recorded',
            'status_changed' => true,
            'next_step' => 'Ready for assignment by MIS Supervisor'
        ]);
    }

    /**
     * Log workflow action to ticket_workflow table
     */
    private function logWorkflowAction($ticketId, $actionType, $actionBy, $remarks = null, $metadata = null)
    {
        DB::table('ticket_workflow')->insert([
            'TICKET_ID'   => $ticketId,
            'ACTION_TYPE' => $actionType,
            'ACTION_BY'   => $actionBy,
            'ACTION_AT'   => now(),
            'REMARKS'     => $remarks,
            'METADATA'    => $metadata ? json_encode($metadata) : null,
        ]);
    }

    /**
     * Extract single employee ID
     */
    private function extractEmployeeId($value)
    {
        if (empty($value)) {
            return null;
        }

        $value = trim($value);
        if (strpos($value, '(') !== false) {
            $value = trim(substr($value, 0, strpos($value, '(')));
        }

        return $value;
    }

    /**
     * Extract multiple employee IDs from comma-separated string
     */
    private function extractMultipleEmployeeIds($value)
    {
        if (empty($value)) {
            return [];
        }

        $parts = explode(',', $value);
        $ids = [];

        foreach ($parts as $part) {
            $id = $this->extractEmployeeId($part);
            if ($id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function generateTicketNumber()
    {
        $year = date('Y');
        $prefix = "TKT-{$year}-";

        $lastTicket = DB::selectOne('
            SELECT TICKET_ID FROM tickets 
            WHERE TICKET_ID LIKE ? 
            ORDER BY TICKET_ID DESC LIMIT 1
        ', ["{$prefix}%"]);

        if ($lastTicket) {
            $lastNumber = (int) substr($lastTicket->TICKET_ID, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    private function generateChildTicketId($parentTicketId)
    {
        $existingChildTickets = DB::select('
            SELECT TICKET_ID FROM tickets 
            WHERE PARENT_TICKET_ID = ? 
            AND DELETED_AT IS NULL
            ORDER BY TICKET_ID DESC
        ', [$parentTicketId]);

        if (empty($existingChildTickets)) {
            return $parentTicketId . '-1';
        }

        $maxNumber = 0;
        foreach ($existingChildTickets as $childTicket) {
            $parts = explode('-', $childTicket->TICKET_ID);
            $lastPart = end($parts);

            if (ctype_digit($lastPart)) {
                $maxNumber = max($maxNumber, (int)$lastPart);
            }
        }

        $nextNumber = $maxNumber + 1;
        return $parentTicketId . '-' . $nextNumber;
    }

    private function handleAttachments($files, $ticketId, $uploadedBy)
    {
        $folder = 'attachmentFiles';
        if (!Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }

        $ticket = DB::selectOne('SELECT ID FROM tickets WHERE TICKET_ID = ?', [$ticketId]);
        if (!$ticket) {
            return;
        }

        foreach ($files as $file) {
            $fileName = now()->format('Ymd') . "_{$ticketId}_{$uploadedBy}_" . $file->getClientOriginalName();
            $filePath = $file->storeAs('attachmentFiles', $fileName, 'public');
            $fileSize = $file->getSize();
            $fileType = $file->getClientMimeType();

            DB::table('ticket_attachments')->insert([
                'TICKET_ID'   => $ticket->ID,
                'FILE_NAME'   => $fileName,
                'FILE_PATH'   => $filePath,
                'FILE_SIZE'   => $fileSize,
                'FILE_TYPE'   => $fileType,
                'UPLOADED_BY' => $uploadedBy,
                'UPLOADED_AT' => now(),
                'DELETED_AT'  => null,
            ]);
        }
    }

    private function logTicketHistory($ticketId, $action, $fieldName = null, $oldValue = null, $newValue = null, $changedBy)
    {
        DB::table('tickets_history')->insert([
            'TICKET_ID'   => $ticketId,
            'ACTION'      => $action,
            'FIELD_NAME'  => $fieldName,
            'OLD_VALUE'   => $oldValue,
            'NEW_VALUE'   => $newValue,
            'CHANGED_BY'  => $changedBy,
            'CHANGED_AT'  => now(),
        ]);
    }

    private function insertRemark($ticketId, $createdBy, $remarkType, $remarkText, $oldStatus = null, $newStatus = null, $oldAssignedTo = null, $newAssignedTo = null, $isInternal = false)
    {
        DB::table('remarks_history')->insert([
            'TICKET_ID'         => $ticketId,
            'CREATED_BY'        => $createdBy,
            'REMARK_TYPE'       => $remarkType,
            'REMARK_TEXT'       => $remarkText,
            'OLD_STATUS'        => $oldStatus,
            'NEW_STATUS'        => $newStatus,
            'OLD_ASSIGNED_TO'   => $oldAssignedTo,
            'NEW_ASSIGNED_TO'   => $newAssignedTo,
            'IS_INTERNAL'       => $isInternal,
            'IS_SYSTEM_GENERATED' => false,
            'CREATED_AT'        => now(),
            'UPDATED_AT'        => now(),
        ]);
    }

    private function ticketValidationRules($isUpdate = false)
    {
        return [
            'employee_id' => 'required|string|max:20',
            'employee_name' => 'required|string|max:250',
            'department' => 'required|string|max:100',
            'type_of_request' => 'required|integer|in:1,2,3,4,5,6',
            'project_name' => 'required|string|max:255',
            'details' => 'required|string',
            'status' => 'required|integer|in:1,2,3,4,5,6,7,8',
            'ticket_level' => 'nullable|string|max:50',
            'parent_ticket_id' => 'nullable|string|max:20',
            'assigned_to' => 'nullable|string|max:255',
        ];
    }

    private function isRequestorAccount($empData)
    {
        return !$this->isAssessedByProgrammer($empData) &&
            !$this->isDepartmentHead($empData) &&
            !$this->isODAccount($empData) &&
            !$this->isMISSupervisor($empData);
    }

    private function isAssessedByProgrammer($empData)
    {
        $dept = strtoupper($empData['emp_dept']);
        $job_Title = strtolower($empData['emp_jobtitle']);

        return $dept === 'MIS' &&
            (
                strpos($job_Title, 'programmer') !== false ||
                (strpos($job_Title, 'mis') !== false && strpos($job_Title, 'supervisor') !== false)
            );
    }

    private function isDepartmentHead($empData)
    {
        $userId = $empData['emp_id'];
        $hasApprovalRights = DB::connection('masterlist')->select("
            SELECT COUNT(*) as count FROM employee_masterlist 
            WHERE (APPROVER2 = '{$userId}' OR APPROVER3 = '{$userId}')
        ");

        return $hasApprovalRights[0]->count > 0;
    }

    private function isODAccount($empData)
    {
        return strtoupper($empData['emp_dept']) === 'OPERATIONS' ||
            strtoupper($empData['emp_jobtitle']) === 'OPERATIONS DIRECTOR';
    }

    private function isMISSupervisor($empData)
    {
        return strtoupper($empData['emp_dept']) === 'MIS' &&
            stripos($empData['emp_jobtitle'], 'supervisor') !== false;
    }

    private function getUserAccountType($empData)
    {
        $roles = [];

        if ($this->isMISSupervisor($empData)) {
            $roles[] = 'MIS_SUPERVISOR';
            $roles[] = 'PROGRAMMER';
        } elseif ($this->isAssessedByProgrammer($empData)) {
            $roles[] = 'PROGRAMMER';
        }

        if ($this->isODAccount($empData)) {
            $roles[] = 'OD';
        }

        if ($this->isDepartmentHead($empData)) {
            $roles[] = 'DEPARTMENT_HEAD';
        }

        if ($this->isRequestorAccount($empData)) {
            $roles[] = 'REQUESTOR';
        }

        return $roles ?: ['UNKNOWN'];
    }
}
