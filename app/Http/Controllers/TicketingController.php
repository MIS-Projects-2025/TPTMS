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

        // Build query
        $query = DB::table('tickets')
            ->whereNull('DELETED_AT');

        if ($request->filled('status')) {
            $query->where('STATUS', $request->input('status'));
        }

        if ($request->filled('project')) {
            $query->where('PROJECT_NAME', $request->input('project'));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('TICKET_ID', 'like', "%{$search}%")
                    ->orWhere('PROJECT_NAME', 'like', "%{$search}%")
                    ->orWhere('DETAILS', 'like', "%{$search}%");
            });
        }

        $orderBy = $columns[$orderCol]['data'] ?? 'CREATED_AT';
        $query->orderBy($orderBy, $orderDir);

        $total = $query->count();
        $tickets = $query->offset($start)->limit($length)->get();

        $data = [];
        foreach ($tickets as $ticket) {
            $actions = [];
            if ($this->canPerformAction($ticket, 'ASSESS', $empData, $userRoles)) {
                $actions[] = 'Assess';
            }
            if ($this->canPerformAction($ticket, 'DH_APPROVE', $empData, $userRoles)) {
                $actions[] = 'DH Approve';
            }
            if ($this->canPerformAction($ticket, 'OD_APPROVE', $empData, $userRoles)) {
                $actions[] = 'OD Approve';
            }
            if ($this->canPerformAction($ticket, 'ASSIGN', $empData, $userRoles)) {
                $actions[] = 'Assign';
            }

            $data[] = [
                'ticket_id' => $ticket->TICKET_ID,
                'emp_name' => $ticket->EMPNAME,
                'project_name' => $ticket->PROJECT_NAME,
                'type_of_request' => $this->getRequestTypeLabel($ticket->TYPE_OF_REQUEST),
                'status' => $this->getStatusLabel($ticket->STATUS),
                'created_at' => $ticket->CREATED_AT,
                'actions' => $actions,
            ];
        }
        // dd($data);
        return Inertia::render('Ticketing/Table', [
            'tickets' => $data,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'draw' => intval($request->input('draw')),
            'filters' => $request->all(),
        ]);
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

    /**
     * Determine if user can perform action on ticket
     */
    private function canPerformAction($ticket, $action, $currentUser, $userRoles)
    {
        $status = $ticket->STATUS;
        $userId = $currentUser['emp_id'];
        $requestType = $ticket->TYPE_OF_REQUEST;
        $workflowPath = $this->getRequiredWorkflowPath($requestType);
        // dd($action, $status, $userRoles, $workflowPath);
        if ($ticket->EMPLOYID == $userId) {
            return false;
        }
        switch ($action) {
            case 'ASSESS':
                if (!$workflowPath['requires_assessment']) {
                    return false;
                }
                return in_array($status, [self::STATUS_NEW, self::STATUS_TRIAGED]) &&
                    (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles));

            case 'DH_APPROVE':
                if (!$workflowPath['requires_dh_approval']) {
                    return false;
                }

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
                if (!$workflowPath['requires_od_approval']) {
                    return false;
                }

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

            case 'ACKNOWLEDGE':
                if ($status !== self::STATUS_APPROVED) return false;
                $assignedIds = $this->extractMultipleEmployeeIds($ticket->ASSIGNED_TO ?? '');
                return in_array($userId, $assignedIds);

            case 'RESOLVE':
                if ($status !== self::STATUS_IN_PROGRESS) return false;
                $assignedIds = $this->extractMultipleEmployeeIds($ticket->ASSIGNED_TO ?? '');
                return in_array($userId, $assignedIds);

            case 'CLOSE':
                return $status === self::STATUS_RESOLVED &&
                    $ticket->EMPLOYEE_ID === $userId;

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

        $currentUser = session('employee');

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

        $currentUser = session('employee');
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

        $currentUser = session('employee');

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

        $currentUser = session('employee');

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
        $jobTitle = strtolower($empData['emp_jobtitle']);

        return $dept === 'MIS' &&
            (
                strpos($jobTitle, 'programmer') !== false ||
                (strpos($jobTitle, 'mis') !== false && strpos($jobTitle, 'supervisor') !== false)
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
