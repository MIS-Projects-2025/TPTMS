<?php

namespace App\Services;

use App\Repositories\TicketRepository;
use App\Constants\TicketConstants;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ProjectController;
use App\Services\NotificationService;
use App\ValueObjects\WorkflowPath;

class TicketService
{
    public function __construct(
        private TicketRepository $ticketRepo,
        private NotificationService $notificationService
    ) {}

    // ========================
    // PUBLIC API METHODS
    // ========================

    public function getRequestTypes(): array
    {
        return [
            ['value' => TicketConstants::REQUEST_NEW_SYSTEM, 'label' => 'New System'],
            ['value' => TicketConstants::REQUEST_MODIFICATION, 'label' => 'Modification'],
            ['value' => TicketConstants::REQUEST_ENHANCEMENT, 'label' => 'Enhancement'],
            ['value' => TicketConstants::REQUEST_ADJUSTMENT, 'label' => 'Adjustment'],
            ['value' => TicketConstants::REQUEST_TESTING, 'label' => 'Testing'],
            ['value' => TicketConstants::REQUEST_PARALLEL_RUN, 'label' => 'Parallel Run'],
        ];
    }

    public function getTicketFormData(): array
    {
        return [
            'ticketOptions' => $this->ticketRepo->getParentTickets(),
            'ticketProjects' => $this->ticketRepo->getTicketProjectMap(),
            'employeeOptions' => $this->ticketRepo->getEmployeeOptions(),
            'projectOptions' => $this->ticketRepo->getProjectOptions(),
        ];
    }

    public function viewTicketData($ticketId, $empData)
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) return null;

        $userRoles = $this->getUserRoles($empData);
        $workflowStage = $this->getCurrentWorkflowStage($ticket->ID);

        return [
            'ticket' => $ticket,
            'attachments' => $this->ticketRepo->getAttachments($ticket->ID),
            'ticketHistory' => $this->enrichWithEmployeeNames(
                $this->ticketRepo->getTicketWorkflow($ticket->ID),
                'ACTION_BY'
            ),
            'remarksHistory' => $this->enrichWithEmployeeNames(
                $this->ticketRepo->getRemarksHistory($ticket->ID),
                'CREATED_BY'
            ),
            'childTickets' => $this->ticketRepo->getChildTickets($ticket->TICKET_ID),
            'assignedEmployees' => !empty($ticket->ASSIGNED_TO)
                ? $this->getAssignedEmployeeNames($ticket->ASSIGNED_TO)
                : [],
            'availableActions' => $this->determineAvailableActions($ticket, $empData, $userRoles),
            'programmerOptions' => in_array(
                TicketConstants::WORKFLOW_ASSIGNED,
                $this->determineAvailableActions($ticket, $empData, $userRoles)
            )
                ? $this->ticketRepo->getProgrammers()
                : [],
            'workflowStage' => $workflowStage,
            'userRoles' => $userRoles,
            'testerInfo' => $this->getTesterInfo($ticket),
        ];
    }

    public function createTicketWithProject(array $validated, array $empData, array $attachments = []): array
    {
        $isChild = !empty($validated['parent_ticket']);
        $ticketId = $isChild
            ? $this->ticketRepo->generateChildTicketId($validated['parent_ticket'])
            : $this->ticketRepo->generateTicketNumber();

        $ticketLevel = $isChild ? 'child' : 'parent';
        $projectName = $this->ticketRepo->determineProjectName($validated);
        $workflowPath = WorkflowPath::forRequestType($validated['request_type']);
        $initialStatus = TicketConstants::STATUS_NEW;

        $ticketDbId = $this->ticketRepo->insertTicket(
            $ticketId,
            $ticketLevel,
            $validated,
            $empData,
            $projectName,
            $initialStatus
        );

        if (!empty($validated['testers'])) {
            $this->ticketRepo->insertTesters($ticketId, $validated['testers']);
        }

        if (!empty($attachments)) {
            $this->ticketRepo->handleAttachments($attachments, $ticketId, $empData['emp_id']);
        }

        $this->logTicketCreation($ticketDbId, $ticketId, $validated, $projectName, $initialStatus, $empData, $workflowPath);

        $projId = null;
        if ($validated['request_type'] == TicketConstants::REQUEST_NEW_SYSTEM) {
            $projId = $this->ticketRepo->createProjectFromTicket(
                $ticketId,
                $projectName,
                $validated['details'],
                $empData,
                $validated['request_type']
            );
            if (!$projId) {
                throw new \Exception('Project creation failed');
            }
        }
        if ($validated['request_type'] == TicketConstants::REQUEST_PARALLEL_RUN || $validated['request_type'] == TicketConstants::REQUEST_TESTING) {
            $testerId = $this->ticketRepo->getTesters($ticketId);
        }

        return [$ticketId, $projId, $projectName, $testerId];
    }

    public function getTicketsDataTable(array $filters, array $empData, array $userRoles)
    {
        $userId = $empData['emp_id'];
        $page = $filters['page'] ?? 1;
        $pageSize = $filters['pageSize'] ?? 10;
        $search = trim($filters['search'] ?? '');
        $sortField = $filters['sortField'] ?? 'created_at';
        $sortOrder = $filters['sortOrder'] ?? 'desc';
        $status = $filters['status'] ?? 'all';
        $project = $filters['project'] ?? '';

        $query = $this->ticketRepo->queryTickets();

        // Role-based visibility
        $testerTicketIds = $this->ticketRepo->getTesterTicketIds($userId);
        $approverIds = in_array('DEPARTMENT_HEAD', $userRoles)
            ? $this->ticketRepo->getApproverIds($userId)
            : [];

        $query = $this->applyRoleVisibility($query, $userRoles, $userId, $testerTicketIds, $approverIds);

        // Apply project filter
        if ($project) $query->where('PROJECT_NAME', $project);

        // Calculate status counts BEFORE applying status filter
        $baseQuery = clone $query;
        $statusCounts = $this->ticketRepo->getStatusCounts($baseQuery);

        // Apply filters
        $query = $this->applyStatusFilter($query, $status);
        if ($search) {
            $query->where(fn($q) => $q->where('TICKET_ID', 'like', "%{$search}%")
                ->orWhere('PROJECT_NAME', 'like', "%{$search}%")
                ->orWhere('DETAILS', 'like', "%{$search}%"));
        }

        $total = $query->count();
        $query = $this->applySorting($query, $sortField, $sortOrder);
        $tickets = $query->forPage($page, $pageSize)->get();

        $data = $tickets->map(fn($ticket) => $this->mapTicketActions(
            $ticket,
            $empData,
            $userRoles,
            $testerTicketIds,
            $this->ticketRepo->wasTicketReturned($ticket->ID)
        ));

        return [
            'tickets' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $pageSize,
                'total' => $total,
                'last_page' => (int) ceil($total / $pageSize),
            ],
            'statusCounts' => $statusCounts,
            'projects' => $this->ticketRepo->getDistinctProjects(),
            'filters' => $filters,
        ];
    }

    // ========================
    // TICKET ACTIONS
    // ========================

    public function assessTicket(string $ticketId, array $empData, ?string $remarks): array
    {
        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_ASSESSED,
            [
                'new_status' => TicketConstants::STATUS_TRIAGED,
                'remark_text' => $remarks ?? 'Ticket assessed and ready for approval',
                'remark_type' => 'ASSESSMENT',
            ],
            fn($ticket) => $this->validateAssessment($ticket),
            fn($ticket, $empData) => $this->sendAssessmentNotifications($ticket, $empData)
        );
    }

    public function approveDH(string $ticketId, array $empData, ?string $remarks): array
    {
        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_DH_APPROVED,
            function ($ticket) use ($remarks) {
                $newStatus = ($ticket->TYPE_OF_REQUEST === TicketConstants::REQUEST_ADJUSTMENT)
                    ? TicketConstants::STATUS_APPROVED
                    : TicketConstants::STATUS_TRIAGED;

                return [
                    'old_status' => $ticket->STATUS,
                    'new_status' => $newStatus,
                    'remark_text' => $remarks ?? 'Approved by Department Head',
                    'remark_type' => 'APPROVAL',
                    'project_name' => $ticket->PROJECT_NAME,
                    'request_type' => $ticket->TYPE_OF_REQUEST,
                    'ticket_number' => $ticket->TICKET_ID,
                ];
            },
            fn($ticket) => $this->validateDHApproval($ticket),
            fn($ticket, $empData) => $this->sendDHApprovalNotifications($ticket, $empData)
        );
    }

    public function approveOD(string $ticketId, array $empData, ?string $remarks): array
    {
        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_OD_APPROVED,
            fn($ticket) => [
                'old_status' => $ticket->STATUS,
                'new_status' => TicketConstants::STATUS_APPROVED,
                'approval_type' => 'OD_APPROVED',
                'remark_text' => $remarks ?? 'Approved by Operations Director',
                'remark_type' => 'APPROVAL',
                'update_project' => true,
                'project_name' => $ticket->PROJECT_NAME,
                'request_type' => $ticket->TYPE_OF_REQUEST,
                'ticket_number' => $ticket->TICKET_ID,
            ],
            fn($ticket) => $this->validateODApproval($ticket),
            fn($ticket, $empData) => $this->sendODApprovalNotifications($ticket, $empData)
        );
    }

    public function assignTicket(string $ticketId, array $assignedTo, array $empData, ?string $remarks): array
    {
        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_ASSIGNED,
            fn($ticket) => [
                'ticket_number' => $ticket->TICKET_ID,
                'project_name' => $ticket->PROJECT_NAME,
                'request_type' => $ticket->TYPE_OF_REQUEST,
                'assigned_to' => implode(',', $assignedTo),
                'assigned_to_array' => $assignedTo,
                'old_status' => $ticket->STATUS,
                'new_status' => TicketConstants::STATUS_IN_PROGRESS,
                'remark_text' => $remarks ?? 'Ticket assigned and work in progress',
            ],
            fn($ticket) => $this->validateAssignment($ticket, $assignedTo),
            function ($ticket, $empData) {
                $this->syncProjectStatus($ticket->PROJECT_NAME);
                $this->sendAssignmentNotifications($ticket, $ticket->ASSIGNED_TO, $empData);
            }
        );
    }

    public function resolveTicket(string $ticketId, array $empData, string $remarks, array $attachments = []): array
    {
        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_RESOLVED,
            fn($ticket) => [
                'ticket_number' => $ticket->TICKET_ID,
                'resolved_by' => $empData['emp_id'],
                'remarks' => $remarks,
                'attachments' => $attachments,
                'old_status' => TicketConstants::STATUS_IN_PROGRESS,
                'new_status' => TicketConstants::STATUS_RESOLVED,
                'request_type' => $ticket->TYPE_OF_REQUEST,
                'project_name' => $ticket->PROJECT_NAME,
            ],
            fn($ticket) => $this->validateResolution($ticket, $empData),
            function ($ticket, $empData) {
                $this->syncProjectStatus($ticket->PROJECT_NAME);
                $this->notificationService->notifyTicketResolved(
                    $ticket->TICKET_ID,
                    $ticket->TYPE_OF_REQUEST,
                    $ticket->EMPLOYID,
                    $empData['emp_name'],
                    $ticket->PROJECT_NAME
                );
            }
        );
    }

    public function closeTicket(string $ticketId, array $empData, ?string $remarks = null, ?int $rating = null): array
    {
        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_CLOSED,
            fn($ticket) => [
                'ticket_number' => $ticket->TICKET_ID,
                'project_name' => $ticket->PROJECT_NAME,
                'closed_by' => $empData['emp_id'],
                'remarks' => $remarks ?? 'Ticket closed',
                'rating' => $rating,
                'old_status' => $ticket->STATUS,
                'new_status' => TicketConstants::STATUS_CLOSED,
                'request_type' => $ticket->TYPE_OF_REQUEST,
            ],
            fn($ticket) => $this->validateClosure($ticket, $empData),
            function ($ticket, $empData) use ($rating) {
                $this->handleProjectDeployment($ticket, $empData['emp_id']);
                $this->notificationService->notifyTicketClosed(
                    $ticket->TICKET_ID,
                    $ticket->TYPE_OF_REQUEST,
                    $empData['emp_name'],
                    $ticket->PROJECT_NAME,
                    $rating
                );
            }
        );
    }

    public function returnTicket(string $ticketId, array $empData, string $remarks): array
    {
        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_RETURNED,
            fn($ticket) => [
                'ticket_number' => $ticket->TICKET_ID,
                'returned_by' => $empData['emp_id'],
                'remarks' => $remarks,
                'old_status' => $ticket->STATUS,
                'new_status' => TicketConstants::STATUS_RETURNED,
                'project_name' => $ticket->PROJECT_NAME,
                'requestor_id' => $ticket->EMPLOYID,
            ],
            fn($ticket) => $this->validateReturn($ticket),
            function ($ticket, $empData) use ($remarks) {
                $this->syncProjectStatus($ticket->PROJECT_NAME);
                $this->notificationService->notifyTicketReturned(
                    $ticket->TICKET_ID,
                    $ticket->EMPLOYID,
                    $empData['emp_name'],
                    $ticket->PROJECT_NAME,
                    $remarks
                );
            }
        );
    }

    public function resubmitTicket(string $ticketId, array $empData): array
    {
        $returnedBy = $this->ticketRepo->getReturnedBy($ticketId);

        return $this->executeTicketAction(
            $ticketId,
            $empData,
            TicketConstants::WORKFLOW_RESUBMITTED,
            fn($ticket) => [
                'ticket_number' => $ticket->TICKET_ID,
                'resubmitted_by' => $empData['emp_id'],
                'old_status' => $ticket->STATUS,
                'new_status' => TicketConstants::STATUS_TRIAGED,
                'project_name' => $ticket->PROJECT_NAME,
                'returned_by' => $returnedBy, // use the employee who returned it
            ],
            fn($ticket) => $this->validateResubmission($ticket, $empData),
            function ($ticket, $empData) use ($returnedBy) {
                $this->syncProjectStatus($ticket->PROJECT_NAME);
                $this->notificationService->notifyTicketResubmitted(
                    $ticket->TICKET_ID,
                    $ticket->TYPE_OF_REQUEST,
                    $empData['emp_name'],
                    $ticket->PROJECT_NAME,
                    $returnedBy // make sure notification shows correct returned_by
                );
            }
        );
    }


    public function submitTestResult(string $ticketId, array $empData, array $validated, array $attachments = []): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found', 'status' => 404];
        }

        if (!$this->validateTestSubmission($ticket, $empData)) {
            return ['success' => false, 'message' => 'Invalid test submission', 'status' => 403];
        }

        $data = [
            'ticket_id' => $ticket->ID,
            'ticket_number' => $ticket->TICKET_ID,
            'tester_id' => $empData['emp_id'],
            'test_status' => $validated['test_status'],
            'remarks' => $validated['remarks'],
            'attachments' => $attachments,
            'old_status' => $ticket->STATUS,
        ];

        $result = $this->ticketRepo->performSubmitTestResult($data);

        if ($result['success']) {
            $this->syncProjectStatus($ticket->PROJECT_NAME);
        }

        return $result;
    }

    // ========================
    // GENERIC ACTION EXECUTOR
    // ========================

    private function executeTicketAction(
        string $ticketId,
        array $empData,
        string $actionType,
        $actionData,
        ?callable $validator = null,
        ?callable $postCommit = null
    ): array {
        // 1. Get ticket
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        // 2. Validate (custom logic if needed)
        if ($validator && !$validator($ticket)) {
            return ['success' => false, 'message' => 'Validation failed'];
        }

        // 3. Prepare data (can be array or callable)
        $data = is_callable($actionData) ? $actionData($ticket) : $actionData;

        // 4. Perform action
        $result = $this->ticketRepo->performWorkflowAction(array_merge([
            'ticket_id' => $ticket->ID,
            'action_type' => $actionType,
            'action_by' => $empData['emp_id'],
        ], $data));

        if (!$result) {
            return ['success' => false, 'message' => 'Action failed'];
        }

        // 5. Post-commit actions (notifications, project sync)
        if ($postCommit) {
            try {
                $postCommit($ticket, $empData);
            } catch (\Exception $e) {
                Log::warning('Post-commit action failed', [
                    'ticket_id' => $ticketId,
                    'action' => $actionType,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return ['success' => true, 'message' => 'Action completed successfully'];
    }

    // ========================
    // VALIDATION METHODS
    // ========================

    private function validateAssessment($ticket): bool
    {
        $workflowPath = WorkflowPath::forRequestType($ticket->TYPE_OF_REQUEST);

        if (!$workflowPath->requiresAssessment) {
            return false;
        }

        return in_array($ticket->STATUS, [
            TicketConstants::STATUS_NEW,
            TicketConstants::STATUS_TRIAGED
        ]);
    }

    private function validateDHApproval($ticket): bool
    {
        return $ticket->STATUS === TicketConstants::STATUS_TRIAGED
            && !empty($ticket->PROJECT_NAME);
    }

    private function validateODApproval($ticket): bool
    {
        return $ticket->STATUS === TicketConstants::STATUS_TRIAGED
            && !empty($ticket->PROJECT_NAME);
    }

    private function validateAssignment($ticket, $assignedTo): bool
    {
        if (empty($ticket->PROJECT_NAME) || empty($assignedTo)) {
            return false;
        }

        $workflowPath = WorkflowPath::forRequestType($ticket->TYPE_OF_REQUEST);

        if (!$workflowPath->canDirectAssign && $ticket->STATUS !== TicketConstants::STATUS_APPROVED) {
            return false;
        }

        return true;
    }

    private function validateResolution($ticket, $empData): bool
    {
        if ($ticket->STATUS !== TicketConstants::STATUS_IN_PROGRESS) {
            return false;
        }

        $assignedIds = $this->extractMultipleEmployeeIds($ticket->ASSIGNED_TO ?? '');
        return in_array($empData['emp_id'], $assignedIds);
    }

    private function validateClosure($ticket, $empData): bool
    {
        $userId = $empData['emp_id'];

        // For Testing/Parallel Run: Only assigned testers can close
        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            // Check if user is an assigned tester
            $isAssignedTester = $this->ticketRepo->getAssignedTester($ticket->ID, $userId);

            if (!$isAssignedTester) {
                return false;
            }

            // Testers can close if all tests passed OR if status is resolved
            return $ticket->STATUS === TicketConstants::STATUS_RESOLVED
                || $this->ticketRepo->allTestersPassed($ticket->ID);
        }

        // For normal tickets: Only requestor can close when resolved
        return $ticket->STATUS === TicketConstants::STATUS_RESOLVED
            && $ticket->EMPLOYID === $userId;
    }

    private function validateReturn($ticket): bool
    {
        return in_array($ticket->STATUS, [
            TicketConstants::STATUS_NEW,
            TicketConstants::STATUS_TRIAGED
        ]);
    }

    private function validateResubmission($ticket, $empData): bool
    {
        // Only requestor can resubmit
        if ($ticket->EMPLOYID !== $empData['emp_id']) {
            return false;
        }

        // Must be in RETURNED status
        if ($ticket->STATUS !== TicketConstants::STATUS_RETURNED) {
            return false;
        }

        // For Testing/Parallel Run: Can only resubmit if returned by a tester
        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            $returnedBy = $this->ticketRepo->getReturnedBy($ticket->ID);

            // Check if the person who returned it is a tester
            $wasTesterReturn = $this->ticketRepo->getAssignedTester($ticket->ID, $returnedBy);

            return (bool) $wasTesterReturn;
        }

        return true;
    }

    private function validateTestSubmission($ticket, $empData): bool
    {
        if (!in_array($ticket->STATUS, [
            TicketConstants::STATUS_NEW,
            TicketConstants::STATUS_TRIAGED,
            TicketConstants::STATUS_IN_PROGRESS,
            TicketConstants::STATUS_RESOLVED
        ])) {
            return false;
        }

        return (bool) $this->ticketRepo->isTesterAssignedAndPending($ticket->ID, $empData['emp_id']);
    }
    // ========================
    // AUTHORIZATION
    // ========================

    private function determineAvailableActions($ticket, $currentUser, $userRoles)
    {
        $userId = $currentUser['emp_id'];
        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        // For Testing/Parallel Run tickets - Special handling
        if ($isTestingTicket) {
            $isAssignedTester = $this->ticketRepo->getAssignedTester($ticket->ID, $userId);

            if ($isAssignedTester) {
                // Testers ONLY get TEST action (which includes Pass & Close / Fail & Return)
                if ($this->canTest($ticket, $currentUser)) {
                    return ['TEST']; // Only TEST action, no separate CLOSE
                }

                return [];
            }

            // Requestor of Testing/Parallel Run can only resubmit if tester returned it
            if ($ticket->EMPLOYID === $userId) {
                if ($this->canResubmit($ticket, $currentUser)) {
                    return [TicketConstants::WORKFLOW_RESUBMITTED];
                }
                return [];
            }

            // Programmers can assess/assign Testing/Parallel Run tickets
            if ($this->isProgrammerOrSupervisor($userRoles)) {
                $actions = [];

                if ($this->canAssess($ticket, $currentUser, $userRoles)) {
                    $actions[] = TicketConstants::WORKFLOW_ASSESSED;
                }

                if ($this->canAssign($ticket, $userRoles)) {
                    $actions[] = TicketConstants::WORKFLOW_ASSIGNED;
                }

                return $actions;
            }

            return [];
        }

        // Normal workflow for other tickets
        $possibleActions = [
            TicketConstants::WORKFLOW_ASSESSED,
            TicketConstants::WORKFLOW_DH_APPROVED,
            TicketConstants::WORKFLOW_OD_APPROVED,
            TicketConstants::WORKFLOW_ASSIGNED,
            TicketConstants::WORKFLOW_RESOLVED,
            TicketConstants::WORKFLOW_CLOSED,
            TicketConstants::WORKFLOW_RESUBMITTED,
        ];

        return array_values(array_filter(
            $possibleActions,
            fn($action) => $this->canPerformAction($ticket, $action, $currentUser, $userRoles)
        ));
    }
    public function canPerformAction($ticket, $action, $currentUser, $userRoles): bool
    {
        $validators = [
            TicketConstants::WORKFLOW_ASSESSED => fn() => $this->canAssess($ticket, $currentUser, $userRoles),
            TicketConstants::WORKFLOW_DH_APPROVED => fn() => $this->canDHApprove($ticket, $userRoles),
            TicketConstants::WORKFLOW_OD_APPROVED => fn() => $this->canODApprove($ticket, $userRoles),
            TicketConstants::WORKFLOW_ASSIGNED => fn() => $this->canAssign($ticket, $userRoles),
            TicketConstants::WORKFLOW_RESOLVED => fn() => $this->canResolve($ticket, $currentUser),
            'IN_PROGRESS' => fn() => $this->canResolve($ticket, $currentUser),
            TicketConstants::WORKFLOW_CLOSED => fn() => $this->canClose($ticket, $currentUser),
            TicketConstants::WORKFLOW_RESUBMITTED => fn() => $this->canResubmit($ticket, $currentUser),
            'TEST' => fn() => $this->canTest($ticket, $currentUser),
        ];

        return isset($validators[$action]) && $validators[$action]();
    }

    private function canAssess($ticket, $currentUser, $userRoles): bool
    {
        $workflowPath = WorkflowPath::forRequestType($ticket->TYPE_OF_REQUEST);

        // Cannot assess tickets that don't require assessment
        if (!$workflowPath->requiresAssessment) {
            return false;
        }

        // CRITICAL FIX: Programmers cannot assess their own tickets
        if ($currentUser['emp_id'] == $ticket->EMPLOYID) {
            return false;
        }

        // Must be programmer or supervisor
        if (!$this->isProgrammerOrSupervisor($userRoles)) {
            return false;
        }

        $wasReturned = $this->ticketRepo->ticketWorkflowExists($ticket->ID, TicketConstants::WORKFLOW_RETURNED);
        $validStatus = $ticket->STATUS == TicketConstants::STATUS_NEW
            || ($ticket->STATUS == TicketConstants::STATUS_TRIAGED && $wasReturned);

        return $validStatus;
    }

    private function canReturn($ticket, $userRoles): bool
    {
        // For Testing/Parallel Run: Only testers can return
        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            return false; // Testers use canTest() which handles return via TEST action
        }

        // For normal tickets: Programmers/supervisors can return during triage
        return in_array($ticket->STATUS, [
            TicketConstants::STATUS_NEW,
            TicketConstants::STATUS_TRIAGED
        ]) && $this->isProgrammerOrSupervisor($userRoles);
    }

    private function canDHApprove($ticket, $userRoles): bool
    {
        if ($ticket->STATUS !== TicketConstants::STATUS_TRIAGED || !in_array('DEPARTMENT_HEAD', $userRoles)) {
            return false;
        }

        $workflowPath = WorkflowPath::forRequestType($ticket->TYPE_OF_REQUEST);

        if (!$workflowPath->requiresDHApproval) return false;

        if (
            $workflowPath->requiresAssessment &&
            !$this->ticketRepo->ticketWorkflowExists($ticket->ID, TicketConstants::WORKFLOW_ASSESSED)
        ) {
            return false;
        }

        return !$this->ticketRepo->ticketWorkflowExists($ticket->ID, TicketConstants::WORKFLOW_DH_APPROVED);
    }

    private function canODApprove($ticket, $userRoles): bool
    {
        if ($ticket->STATUS !== TicketConstants::STATUS_TRIAGED || !in_array('OD', $userRoles)) {
            return false;
        }

        $workflowPath = WorkflowPath::forRequestType($ticket->TYPE_OF_REQUEST);

        if (!$workflowPath->requiresODApproval) return false;

        if (!$this->ticketRepo->ticketWorkflowExists($ticket->ID, TicketConstants::WORKFLOW_DH_APPROVED)) {
            return false;
        }

        return !$this->ticketRepo->ticketWorkflowExists($ticket->ID, TicketConstants::WORKFLOW_OD_APPROVED);
    }

    private function canAssign($ticket, $userRoles): bool
    {
        $workflowPath = WorkflowPath::forRequestType($ticket->TYPE_OF_REQUEST);

        if ($workflowPath->canDirectAssign) {
            return in_array($ticket->STATUS, [
                TicketConstants::STATUS_NEW,
                TicketConstants::STATUS_APPROVED
            ]) && $this->isProgrammerOrSupervisor($userRoles);
        }

        return $ticket->STATUS === TicketConstants::STATUS_APPROVED
            && in_array('MIS_SUPERVISOR', $userRoles)
            && $this->areApprovalsComplete($ticket->ID, $ticket->TYPE_OF_REQUEST);
    }

    private function canResolve($ticket, $currentUser): bool
    {
        if ($ticket->STATUS !== TicketConstants::STATUS_IN_PROGRESS) return false;

        $assignedIds = $this->extractMultipleEmployeeIds($ticket->ASSIGNED_TO ?? '');
        return in_array($currentUser['emp_id'], $assignedIds);
    }

    private function canClose($ticket, $currentUser): bool
    {
        $userId = $currentUser['emp_id'];

        // For Testing/Parallel Run tickets: No separate CLOSE action
        // Testers use TEST action which handles Pass & Close / Fail & Return
        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            return false; // Testers use TEST action, not CLOSE
        }

        // For normal tickets: Only requestor can close when resolved
        return $ticket->STATUS === TicketConstants::STATUS_RESOLVED
            && $ticket->EMPLOYID === $userId;
    }


    private function canResubmit($ticket, $currentUser): bool
    {
        // Only requestor can resubmit
        if ($ticket->EMPLOYID !== $currentUser['emp_id']) {
            return false;
        }

        // Must be in RETURNED status
        if ($ticket->STATUS !== TicketConstants::STATUS_RETURNED) {
            return false;
        }

        // For Testing/Parallel Run: Only allow if returned by tester
        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            $returnedBy = $this->ticketRepo->getReturnedBy($ticket->ID);

            // Verify the person who returned it is a tester
            $wasTesterReturn = $this->ticketRepo->getAssignedTester($ticket->ID, $returnedBy);

            return (bool) $wasTesterReturn;
        }

        return true;
    }
    private function canTest($ticket, $currentUser): bool
    {
        // Must be assigned as tester
        if (!$this->ticketRepo->isTesterAssignedAndPending($ticket->ID, $currentUser['emp_id'])) {
            return false;
        }

        // Can test in these statuses
        return in_array($ticket->STATUS, [
            TicketConstants::STATUS_NEW,
            TicketConstants::STATUS_TRIAGED,
            TicketConstants::STATUS_IN_PROGRESS,
            TicketConstants::STATUS_RESOLVED
        ]);
    }
    // ========================
    // ROLE CHECKING
    // ========================

    private function getUserRoles($empData): array
    {
        $roleChecks = [
            'MIS_SUPERVISOR' => fn() => $this->isMISSupervisor($empData),
            'PROGRAMMER' => fn() => $this->isAssessedByProgrammer($empData) || $this->isMISSupervisor($empData),
            'OD' => fn() => $this->isODAccount($empData),
            'DEPARTMENT_HEAD' => fn() => $this->isDepartmentHead($empData),
            'REQUESTOR' => fn() => $this->isRequestorAccount($empData),
        ];

        $roles = [];
        foreach ($roleChecks as $role => $check) {
            if ($check()) $roles[] = $role;
        }

        return $roles ?: ['UNKNOWN'];
    }

    private function isProgrammerOrSupervisor($userRoles): bool
    {
        return in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles);
    }

    private function isRequestorAccount($empData): bool
    {
        return !$this->isAssessedByProgrammer($empData)
            && !$this->isDepartmentHead($empData)
            && !$this->isODAccount($empData)
            && !$this->isMISSupervisor($empData);
    }

    private function isAssessedByProgrammer($empData): bool
    {
        $dept = strtoupper($empData['emp_dept']);
        $jobTitle = strtolower($empData['emp_jobtitle']);

        return $dept === 'MIS' && (
            strpos($jobTitle, 'programmer') !== false ||
            (strpos($jobTitle, 'mis') !== false && strpos($jobTitle, 'supervisor') !== false)
        );
    }

    private function isDepartmentHead($empData): bool
    {
        return $this->ticketRepo->isDepartmentHead($empData['emp_id']);
    }

    private function isODAccount($empData): bool
    {
        return strtoupper($empData['emp_dept']) === 'OPERATIONS'
            || strtoupper($empData['emp_jobtitle']) === 'OPERATIONS DIRECTOR';
    }

    private function isMISSupervisor($empData): bool
    {
        return strtoupper($empData['emp_dept']) === 'MIS'
            && stripos($empData['emp_jobtitle'], 'supervisor') !== false;
    }

    // ========================
    // WORKFLOW HELPERS
    // ========================

    private function getCurrentWorkflowStage($ticketId)
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) return null;

        $workflowPath = WorkflowPath::forRequestType($ticket->TYPE_OF_REQUEST);
        $workflow = $this->ticketRepo->getTicketWorkflow($ticketId)[0] ?? null;

        return [
            'status' => $ticket->STATUS,
            'status_label' => $this->getStatusLabel($ticket->STATUS),
            'request_type' => $ticket->TYPE_OF_REQUEST,
            'request_type_label' => $this->getRequestTypeLabel($ticket->TYPE_OF_REQUEST),
            'workflow_type' => $workflowPath->workflowType,
            'last_action' => $workflow->ACTION_TYPE ?? null,
            'last_action_by' => $workflow->ACTION_BY ?? null,
            'last_action_at' => $workflow->ACTION_AT ?? null,
            'pending_action' => $this->getPendingAction($ticket->STATUS, $ticket->TYPE_OF_REQUEST, $ticketId),
            'can_direct_assign' => $workflowPath->canDirectAssign,
        ];
    }

    private function areApprovalsComplete($ticketId, $requestType): bool
    {
        $workflow = WorkflowPath::forRequestType($requestType);

        if ($workflow->canDirectAssign) {
            return true;
        }

        $workflowHistory = $this->ticketRepo->getWorkflowHistory([
            TicketConstants::WORKFLOW_ASSESSED,
            TicketConstants::WORKFLOW_DH_APPROVED,
            TicketConstants::WORKFLOW_OD_APPROVED
        ], $ticketId);

        $hasAssessment = in_array(TicketConstants::WORKFLOW_ASSESSED, $workflowHistory);
        $hasDHApproval = in_array(TicketConstants::WORKFLOW_DH_APPROVED, $workflowHistory);
        $hasODApproval = in_array(TicketConstants::WORKFLOW_OD_APPROVED, $workflowHistory);

        if ($workflow->requiresAssessment && !$hasAssessment) return false;
        if ($workflow->requiresDHApproval && !$hasDHApproval) return false;
        if ($workflow->requiresODApproval && !$hasODApproval) return false;

        return true;
    }

    private function getPendingAction($status, $requestType, $ticketId = null): string
    {
        $workflowPath = WorkflowPath::forRequestType($requestType);

        // For Testing and Parallel Run requests
        if ($workflowPath->canDirectAssign) {
            $actions = [
                TicketConstants::STATUS_NEW => 'Awaiting direct assignment by programmer',
                TicketConstants::STATUS_APPROVED => 'Awaiting assignment',
                TicketConstants::STATUS_IN_PROGRESS => 'Work in progress',
                TicketConstants::STATUS_RESOLVED => 'Awaiting verification by requestor',
                TicketConstants::STATUS_CLOSED => 'Completed',
                TicketConstants::STATUS_REJECTED => 'Rejected',
                TicketConstants::STATUS_ON_HOLD => 'On hold',
            ];
            return $actions[$status] ?? 'Unknown';
        }

        // For requests requiring approvals
        if ($status === TicketConstants::STATUS_TRIAGED && $ticketId) {
            $workflowHistory = $this->ticketRepo->getWorkflowHistory([
                TicketConstants::WORKFLOW_ASSESSED,
                TicketConstants::WORKFLOW_DH_APPROVED,
                TicketConstants::WORKFLOW_OD_APPROVED
            ], $ticketId);

            $hasAssessment = in_array(TicketConstants::WORKFLOW_ASSESSED, $workflowHistory);
            $hasDHApproval = in_array(TicketConstants::WORKFLOW_DH_APPROVED, $workflowHistory);

            if (!$hasAssessment) {
                return 'Awaiting assessment by programmer';
            }

            if ($workflowPath->requiresDHApproval && !$hasDHApproval) {
                return 'Awaiting Department Head approval';
            }

            if ($workflowPath->requiresODApproval) {
                return 'Awaiting Operations Director approval';
            }
        }

        $actions = [
            TicketConstants::STATUS_NEW => 'Awaiting triage by programmer',
            TicketConstants::STATUS_TRIAGED => 'In approval process',
            TicketConstants::STATUS_APPROVED => 'Awaiting assignment by MIS Supervisor',
            TicketConstants::STATUS_IN_PROGRESS => 'Work in progress',
            TicketConstants::STATUS_RESOLVED => 'Awaiting verification by requestor',
            TicketConstants::STATUS_CLOSED => 'Completed',
            TicketConstants::STATUS_REJECTED => 'Rejected',
            TicketConstants::STATUS_ON_HOLD => 'On hold',
        ];

        return $actions[$status] ?? 'Unknown';
    }

    // ========================
    // EMPLOYEE HELPERS
    // ========================

    private function enrichWithEmployeeNames(array $data, string $employeeIdField): array
    {
        if (empty($data)) return $data;

        $employeeIds = array_column($data, $employeeIdField);
        $employeeNames = $this->ticketRepo->getEmployeeNames($employeeIds);

        foreach ($data as $item) {
            $empId = $item->{$employeeIdField};
            $item->employee_name = $employeeNames[$empId] ?? 'Unknown';
            $item->employee_display = ($employeeNames[$empId] ?? 'Unknown') . " ($empId)";
        }

        return $data;
    }

    private function getAssignedEmployeeNames(string $assignedToString): array
    {
        $ids = array_filter(explode(',', $assignedToString));
        $names = $this->ticketRepo->getEmployeeNames($ids);

        return array_map(fn($id) => [
            'id' => $id,
            'name' => $names[$id] ?? 'Unknown',
            'display' => ($names[$id] ?? 'Unknown') . " ($id)"
        ], $ids);
    }

    private function extractMultipleEmployeeIds($value): array
    {
        if (empty($value)) return [];

        $parts = explode(',', $value);
        $ids = [];

        foreach ($parts as $part) {
            $id = $this->extractEmployeeId($part);
            if ($id) $ids[] = $id;
        }

        return $ids;
    }

    private function extractEmployeeId($value): ?string
    {
        if (empty($value)) return null;

        $value = trim($value);
        if (strpos($value, '(') !== false) {
            $value = trim(substr($value, 0, strpos($value, '(')));
        }

        return $value;
    }

    // ========================
    // TESTER HELPERS
    // ========================

    private function getTesterInfo($ticket): array
    {
        if (!in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ])) {
            return [];
        }

        $testers = $this->ticketRepo->getTesters($ticket->ID);
        if (empty($testers)) return [];

        $testerIds = array_column($testers, 'TESTER_ID');
        $testerNames = $this->ticketRepo->getTesterNamesFromMasterlist($testerIds);

        $nameMap = [];
        foreach ($testerNames as $t) {
            $nameMap[$t->EMPLOYID] = $t->EMPNAME;
        }

        foreach ($testers as &$tester) {
            $tester->TESTER_NAME = $nameMap[$tester->TESTER_ID] ?? 'Unknown';
        }

        return $testers;
    }

    // ========================
    // LABEL HELPERS
    // ========================

    private function getStatusLabel($status): string
    {
        $labels = [
            TicketConstants::STATUS_NEW => 'New',
            TicketConstants::STATUS_TRIAGED => 'Triaged',
            TicketConstants::STATUS_APPROVED => 'Approved',
            TicketConstants::STATUS_IN_PROGRESS => 'In Progress',
            TicketConstants::STATUS_RESOLVED => 'Resolved',
            TicketConstants::STATUS_CLOSED => 'Closed',
            TicketConstants::STATUS_REJECTED => 'Rejected',
            TicketConstants::STATUS_ON_HOLD => 'On Hold',
            TicketConstants::STATUS_RETURNED => 'Returned',
        ];

        return $labels[$status] ?? 'Unknown';
    }

    private function getRequestTypeLabel($requestType): string
    {
        $labels = [
            TicketConstants::REQUEST_NEW_SYSTEM => 'New System Request',
            TicketConstants::REQUEST_MODIFICATION => 'Modification Request',
            TicketConstants::REQUEST_ENHANCEMENT => 'Enhancement Request',
            TicketConstants::REQUEST_ADJUSTMENT => 'Adjustment Request',
            TicketConstants::REQUEST_TESTING => 'Testing Request',
            TicketConstants::REQUEST_PARALLEL_RUN => 'Parallel Run Request',
        ];

        return $labels[$requestType] ?? 'Unknown Request Type';
    }

    // ========================
    // DATATABLE HELPERS
    // ========================

    private function applyStatusFilter($query, $status)
    {
        $statusTab = strtolower($status ?? 'all');

        if ($statusTab === 'urgent') {
            return $query->whereIn('TYPE_OF_REQUEST', [
                TicketConstants::REQUEST_TESTING,
                TicketConstants::REQUEST_PARALLEL_RUN
            ])->where('STATUS', '!=', TicketConstants::STATUS_CLOSED);
        }

        $statusMap = [
            'all' => null,
            'active' => [TicketConstants::STATUS_NEW, TicketConstants::STATUS_TRIAGED],
            'in_progress' => [TicketConstants::STATUS_IN_PROGRESS],
            'closed' => [TicketConstants::STATUS_CLOSED],
        ];

        if (isset($statusMap[$statusTab]) && $statusMap[$statusTab] !== null) {
            $query->whereIn('STATUS', $statusMap[$statusTab]);
        }

        return $query;
    }

    private function applyRoleVisibility($query, $userRoles, $userId, $testerTicketIds, $approverIds)
    {
        return $query->where(function ($q) use ($userRoles, $userId, $testerTicketIds, $approverIds) {
            if ($this->isProgrammerOrSupervisor($userRoles)) {
                $q->orWhereIn('STATUS', [TicketConstants::STATUS_NEW]);
            }
            if ($approverIds) {
                $q->orWhereIn('EMPLOYID', $approverIds);
            }
            if (in_array('OD', $userRoles)) {
                $q->orWhereRaw('1=1');
            }
            if (in_array('MIS_SUPERVISOR', $userRoles)) {
                $q->orWhere('STATUS', TicketConstants::STATUS_APPROVED);
            }
            $q->orWhere('EMPLOYID', $userId)->orWhere('ASSIGNED_TO', $userId);

            if ($testerTicketIds) {
                $q->orWhereIn('ID', $testerTicketIds);
            }
        });
    }

    private function applySorting($query, $sortField, $sortOrder)
    {
        $columnMap = [
            'ticket_id' => 'TICKET_ID',
            'emp_name' => 'EMPNAME',
            'project_name' => 'PROJECT_NAME',
            'created_at' => 'CREATED_AT',
        ];

        return $query->orderBy($columnMap[$sortField] ?? 'CREATED_AT', $sortOrder);
    }

    private function mapTicketActions($ticket, $empData, $userRoles, $testerTicketIds, $wasReturned): array
    {
        $actionLabelMap = [
            'ASSESS' => 'Assess',
            'RETURN' => 'Return',
            'DH_APPROVE' => 'DH Approve',
            'OD_APPROVE' => 'OD Approve',
            'ASSIGN' => 'Assign',
            'RESOLVE' => 'Resolve',
            'CLOSE' => 'Close',
            'TEST' => 'Test',
            'RESUBMIT' => 'Resubmit',
        ];

        $actions = [];
        foreach (array_keys($actionLabelMap) as $actionType) {
            if ($this->canPerformAction($ticket, $actionType, $empData, $userRoles)) {
                $actions[] = $actionLabelMap[$actionType];
            }
        }

        if (in_array($ticket->ID, $testerTicketIds) && !in_array('Test', $actions)) {
            $actions[] = 'Test';
        }

        return [
            'ticket_id' => $ticket->TICKET_ID,
            'employid' => $ticket->EMPLOYID,
            'emp_name' => $ticket->EMPNAME,
            'project_name' => $ticket->PROJECT_NAME,
            'type_of_request' => $this->getRequestTypeLabel($ticket->TYPE_OF_REQUEST),
            'status' => $this->getStatusLabel($ticket->STATUS),
            'created_at' => $ticket->CREATED_AT,
            'actions' => $actions,
            'is_tester' => in_array($ticket->ID, $testerTicketIds),
        ];
    }

    // ========================
    // NOTIFICATION HELPERS
    // ========================

    private function sendAssessmentNotifications($ticket, $empData): void
    {
        try {
            $ticketDetails = $this->ticketRepo->getTicketDetailsForNotification($ticket->ID);

            $this->notificationService->notifyAssessmentComplete(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $ticketDetails->EMPLOYID ?? null,
                $empData['emp_name'],
                $ticket->PROJECT_NAME ?? ''
            );

            Log::info('Assessment notifications sent', ['ticket_id' => $ticket->TICKET_ID]);
        } catch (\Exception $e) {
            Log::warning('Failed to send assessment notification', [
                'ticket_id' => $ticket->TICKET_ID,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendDHApprovalNotifications($ticket, $empData): void
    {
        try {
            $this->notificationService->notifyDHApproved(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $empData['emp_name'],
                $ticket->PROJECT_NAME
            );

            Log::info('DH approval notifications sent', ['ticket_id' => $ticket->TICKET_ID]);
        } catch (\Exception $e) {
            Log::warning('Failed to send DH approval notification', [
                'ticket_id' => $ticket->TICKET_ID,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendODApprovalNotifications($ticket, $empData): void
    {
        try {
            $this->notificationService->notifyODApproved(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $empData['emp_name'],
                $ticket->PROJECT_NAME
            );

            Log::info('OD approval notifications sent', ['ticket_id' => $ticket->TICKET_ID]);
        } catch (\Exception $e) {
            Log::warning('Failed to send OD approval notification', [
                'ticket_id' => $ticket->TICKET_ID,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendAssignmentNotifications($ticket, $assignedToString, $empData): void
    {
        try {
            $this->notificationService->notifyTicketAssigned(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $assignedToString,
                $empData['emp_name'],
                $ticket->PROJECT_NAME
            );

            Log::info('Assignment notifications sent', ['ticket_id' => $ticket->TICKET_ID]);
        } catch (\Exception $e) {
            Log::warning('Failed to send assignment notification', [
                'ticket_id' => $ticket->TICKET_ID,
                'error' => $e->getMessage()
            ]);
        }
    }

    // ========================
    // PROJECT HELPERS
    // ========================

    private function syncProjectStatus(?string $projectName): void
    {
        if (empty($projectName)) return;

        try {
            $projectController = new ProjectController();
            $projectController->updateProjectStatusFromTickets($projectName);
        } catch (\Exception $e) {
            Log::warning('Failed to sync project status: ' . $e->getMessage());
        }
    }

    private function handleProjectDeployment($ticket, $userId): void
    {
        if (empty($ticket->PROJECT_NAME)) return;

        $projectController = new ProjectController();

        try {
            $projectController->updateToDeployed(
                $ticket->PROJECT_NAME,
                $userId,
                $ticket->TYPE_OF_REQUEST,
                $ticket->TICKET_ID
            );
        } catch (\Exception $e) {
            Log::info('Project not deployed yet: ' . $e->getMessage());
            $projectController->updateProjectStatusFromTickets($ticket->PROJECT_NAME);
        }
    }

    // ========================
    // TICKET CREATION HELPERS
    // ========================



    private function logTicketCreation(
        $ticketDbId,
        $ticketId,
        $validated,
        $projectName,
        $initialStatus,
        $empData,
        $workflowPath
    ): void {
        $this->ticketRepo->logTicketHistory(
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

        $remarkText = $workflowPath->canDirectAssign
            ? "Ticket created. Awaiting assignment by programmer."
            : "Ticket created. Awaiting triage by programmer.";

        $this->ticketRepo->insertRemark(
            $ticketDbId,
            $empData['emp_id'],
            'CREATION',
            $remarkText,
            null,
            $initialStatus
        );
    }
}
