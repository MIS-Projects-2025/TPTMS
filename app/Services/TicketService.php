<?php

namespace App\Services;

use App\Repositories\TicketRepository;
use App\Constants\TicketConstants;
use Illuminate\Support\Facades\Log;
use App\Services\ProjectService;
use App\Http\Controllers\TaskController;
use App\Services\NotificationService;
use App\ValueObjects\WorkflowPath;

class TicketService
{
    public function __construct(
        private TicketRepository $ticketRepo,
        private NotificationService $notificationService,
        private ProjectService $projectService
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
        // Add this after inserting the ticket
        if (!empty($validated['target_date'])) {
            $this->ticketRepo->updateTargetDate($ticketDbId, $validated['target_date']);
        }
        if (!empty($validated['testers'])) {
            $this->ticketRepo->insertTesters($ticketDbId, $validated['testers']);
        }

        if (!empty($attachments)) {
            $this->ticketRepo->handleAttachments($attachments, $ticketId, $empData['emp_id']);
        }

        $this->logTicketCreation($ticketDbId, $ticketId, $validated, $projectName, $initialStatus, $empData, $workflowPath);

        $projId = null;
        if ($validated['request_type'] == TicketConstants::REQUEST_NEW_SYSTEM) {

            $projId = $this->projectService->createFromTicket(
                $projectName ?? ('Project for ' . $ticketId),
                $validated['details'],
                $empData['emp_dept'],
                $empData['emp_id'],
                $empData['emp_id'],
                $validated['request_type'],
                $ticketId
            );
            if (!$projId) {
                throw new \Exception('Project creation failed');
            }
        }

        $testerIds = null;
        if (in_array($validated['request_type'], [TicketConstants::REQUEST_TESTING, TicketConstants::REQUEST_PARALLEL_RUN])) {
            $testerIds = $validated['testers'] ?? [];
        }

        // NOTIFICATION: Ticket Created
        try {
            $this->notificationService->notifyTicketCreated(
                $ticketId,
                $validated['request_type'],
                $empData['emp_name'],
                $validated['details'],
                $projectName,
                $testerIds
            );
        } catch (\Exception $e) {
            Log::warning('Ticket creation notification failed', [
                'ticket_id' => $ticketId,
                'error' => $e->getMessage()
            ]);
        }

        return [$ticketId, $projId, $projectName, $testerIds];
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

        $testerTicketIds = $this->ticketRepo->getTesterTicketIds($userId);
        $approverIds = in_array('DEPARTMENT_HEAD', $userRoles)
            ? $this->ticketRepo->getApproverIds($userId)
            : [];

        $query = $this->applyRoleVisibility($query, $userRoles, $userId, $testerTicketIds, $approverIds);

        if ($project) $query->where('PROJECT_NAME', $project);

        $baseQuery = clone $query;
        $statusCounts = $this->ticketRepo->getStatusCounts($baseQuery);

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
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateAssessment($ticket)) {
            return ['success' => false, 'message' => 'Cannot assess this ticket'];
        }

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_ASSESSED,
            'action_by' => $empData['emp_id'],
            'old_status' => $ticket->STATUS,
            'new_status' => TicketConstants::STATUS_TRIAGED,
            'remark_text' => $remarks ?? 'Ticket assessed and ready for approval',
            'remark_type' => 'ASSESSMENT',
        ]);

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to assess ticket'];
        }

        try {
            $this->notificationService->notifyAssessmentComplete(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $ticket->EMPLOYID,
                $empData['emp_name'],
                $ticket->PROJECT_NAME ?? ''
            );
        } catch (\Exception $e) {
            Log::warning('Assessment notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket assessed successfully'];
    }

    public function approveDH(string $ticketId, array $empData, ?string $remarks): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateDHApproval($ticket)) {
            return ['success' => false, 'message' => 'Cannot approve this ticket'];
        }

        $newStatus = ($ticket->TYPE_OF_REQUEST === TicketConstants::REQUEST_ADJUSTMENT)
            ? TicketConstants::STATUS_APPROVED
            : TicketConstants::STATUS_TRIAGED;

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_DH_APPROVED,
            'action_by' => $empData['emp_id'],
            'old_status' => $ticket->STATUS,
            'new_status' => $newStatus,
            'remark_text' => $remarks ?? 'Approved by Department Head',
            'remark_type' => 'APPROVAL',
        ], function ($data) use ($ticket, $empData) {

            $this->projectService->updateToReady(
                $ticket->PROJECT_NAME,
                'DH_APPROVED',
                $empData['emp_id'],
                $ticket->TYPE_OF_REQUEST,
                $ticket->TICKET_ID
            );
        });

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to approve ticket'];
        }

        try {
            $this->notificationService->notifyDHApproved(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $empData['emp_name'],
                $ticket->PROJECT_NAME
            );
        } catch (\Exception $e) {
            Log::warning('DH approval notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket approved by Department Head'];
    }

    public function approveOD(string $ticketId, array $empData, ?string $remarks): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateODApproval($ticket)) {
            return ['success' => false, 'message' => 'Cannot approve this ticket'];
        }

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_OD_APPROVED,
            'action_by' => $empData['emp_id'],
            'old_status' => $ticket->STATUS,
            'new_status' => TicketConstants::STATUS_APPROVED,
            'remark_text' => $remarks ?? 'Approved by Operations Director',
            'remark_type' => 'APPROVAL',
        ], function ($data) use ($ticket, $empData) {

            $this->projectService->updateToReady(
                $ticket->PROJECT_NAME,
                'OD_APPROVED',
                $empData['emp_id'],
                $ticket->TYPE_OF_REQUEST,
                $ticket->TICKET_ID
            );
        });

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to approve ticket'];
        }

        try {
            $this->notificationService->notifyODApproved(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $empData['emp_name'],
                $ticket->PROJECT_NAME
            );
        } catch (\Exception $e) {
            Log::warning('OD approval notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket approved by Operations Director'];
    }

    public function assignTicket(string $ticketId, array $assignedTo, array $empData, ?string $remarks): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateAssignment($ticket, $assignedTo)) {
            return ['success' => false, 'message' => 'Cannot assign this ticket'];
        }

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_ASSIGNED,
            'action_by' => $empData['emp_id'],
            'assigned_to' => implode(',', $assignedTo),
            'old_status' => $ticket->STATUS,
            'new_status' => TicketConstants::STATUS_IN_PROGRESS,
            'remark_text' => $remarks ?? 'Ticket assigned and work in progress',
        ], function ($data) use ($ticket, $empData, $assignedTo, $remarks) {
            $taskController = app(TaskController::class);

            foreach ($assignedTo as $empId) {
                $taskController->createFromTicket(
                    $ticket->TICKET_ID,
                    "TICKET",
                    $remarks,
                    $empId,
                    $empData['emp_id']
                );
            }

            $this->projectService->updateToInProgress(
                $ticket->PROJECT_NAME,
                implode(',', $assignedTo),
                $empData['emp_id'],
                $ticket->TYPE_OF_REQUEST,
                $ticket->TICKET_ID
            );
        });

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to assign ticket'];
        }

        try {
            $this->notificationService->notifyTicketAssigned(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $assignedTo,
                $empData['emp_name'],
                $ticket->PROJECT_NAME
            );
        } catch (\Exception $e) {
            Log::warning('Assignment notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket assigned successfully'];
    }

    public function resolveTicket(string $ticketId, array $empData, string $remarks, array $attachments = []): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateResolution($ticket, $empData)) {
            return ['success' => false, 'message' => 'Cannot resolve this ticket'];
        }

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_RESOLVED,
            'action_by' => $empData['emp_id'],
            'old_status' => $ticket->STATUS,
            'new_status' => TicketConstants::STATUS_RESOLVED,
            'remark_text' => $remarks,
            'remark_type' => 'RESOLUTION',
        ], function ($data) use ($attachments, $ticketId, $empData) {
            if (!empty($attachments)) {
                $this->ticketRepo->handleAttachments($attachments, $ticketId, $empData['emp_id']);
            }
        });

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to resolve ticket'];
        }

        $this->syncProjectStatus($ticket->PROJECT_NAME);

        try {
            $this->notificationService->notifyTicketResolved(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $ticket->EMPLOYID,
                $empData['emp_name'],
                $ticket->PROJECT_NAME
            );
        } catch (\Exception $e) {
            Log::warning('Resolution notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket resolved successfully'];
    }

    public function closeTicket(string $ticketId, array $empData, ?string $remarks = null, ?int $rating = null): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateClosure($ticket, $empData)) {
            return ['success' => false, 'message' => 'Cannot close this ticket'];
        }

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_CLOSED,
            'action_by' => $empData['emp_id'],
            'old_status' => $ticket->STATUS,
            'new_status' => TicketConstants::STATUS_CLOSED,
            'remark_text' => $remarks ?? 'Ticket closed',
            'metadata' => ['rating' => $rating],
        ]);

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to close ticket'];
        }

        $this->handleProjectDeployment($ticket, $empData['emp_id']);

        try {
            $this->notificationService->notifyTicketClosed(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $empData['emp_name'],
                $ticket->PROJECT_NAME,
                $rating
            );
        } catch (\Exception $e) {
            Log::warning('Closure notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket closed successfully'];
    }

    public function returnTicket(string $ticketId, array $empData, string $remarks): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateReturn($ticket)) {
            return ['success' => false, 'message' => 'Cannot return this ticket'];
        }

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_RETURNED,
            'action_by' => $empData['emp_id'],
            'old_status' => $ticket->STATUS,
            'new_status' => TicketConstants::STATUS_RETURNED,
            'remark_text' => $remarks,
        ]);

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to return ticket'];
        }

        $this->syncProjectStatus($ticket->PROJECT_NAME);

        try {
            $this->notificationService->notifyTicketReturned(
                $ticket->TICKET_ID,
                $ticket->EMPLOYID,
                $empData['emp_name'],
                $ticket->PROJECT_NAME,
                $remarks
            );
        } catch (\Exception $e) {
            Log::warning('Return notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket returned successfully'];
    }

    public function resubmitTicket(string $ticketId, array $empData): array
    {
        $ticket = $this->ticketRepo->getTicketById($ticketId);
        if (!$ticket) {
            return ['success' => false, 'message' => 'Ticket not found'];
        }

        if (!$this->validateResubmission($ticket, $empData)) {
            return ['success' => false, 'message' => 'Cannot resubmit this ticket'];
        }

        $returnedBy = $this->ticketRepo->getReturnedBy($ticket->ID);

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => TicketConstants::WORKFLOW_RESUBMITTED,
            'action_by' => $empData['emp_id'],
            'old_status' => $ticket->STATUS,
            'new_status' => TicketConstants::STATUS_TRIAGED,
            'remark_text' => 'Requestor resubmitted ticket after clarification.',
        ]);

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to resubmit ticket'];
        }

        $this->syncProjectStatus($ticket->PROJECT_NAME);

        try {
            $this->notificationService->notifyTicketResubmitted(
                $ticket->TICKET_ID,
                $ticket->TYPE_OF_REQUEST,
                $empData['emp_name'],
                $ticket->PROJECT_NAME,
                $returnedBy
            );
        } catch (\Exception $e) {
            Log::warning('Resubmission notification failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
        }

        return ['success' => true, 'message' => 'Ticket resubmitted successfully'];
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

        $success = $this->ticketRepo->performWorkflowAction([
            'ticket_id' => $ticket->ID,
            'action_type' => 'TEST_SUBMITTED',
            'action_by' => $empData['emp_id'],
            'remark_text' => "Test result: {$validated['test_status']} - {$validated['remarks']}",
            'remark_type' => 'TESTING',
        ], function ($data) use ($ticket, $empData, $validated, $attachments, $ticketId) {
            $this->ticketRepo->updateTesterStatus(
                $ticket->ID,
                $empData['emp_id'],
                $validated['test_status'],
                $validated['remarks']
            );

            if (!empty($attachments)) {
                $this->ticketRepo->handleAttachments($attachments, $ticketId, $empData['emp_id']);
            }
        });

        if (!$success) {
            return ['success' => false, 'message' => 'Failed to submit test result'];
        }

        $testers = $this->ticketRepo->getTesters($ticket->ID);
        $allCompleted = collect($testers)->where('STATUS', 'PENDING')->isEmpty();
        $allPassed = collect($testers)->where('STATUS', 'FAILED')->isEmpty();

        $message = "Test result submitted successfully";
        if ($allCompleted) {
            $message .= $allPassed ? ". All tests passed!" : ". Some tests failed";
        }

        $this->syncProjectStatus($ticket->PROJECT_NAME);

        return [
            'success' => true,
            'message' => $message,
            'all_completed' => $allCompleted,
            'all_passed' => $allPassed,
            'next_step' => $allCompleted ? ($allPassed ? 'close' : 'return') : 'pending'
        ];
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

        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            $isAssignedTester = $this->ticketRepo->getAssignedTester($ticket->ID, $userId);

            if (!$isAssignedTester) {
                return false;
            }

            return $ticket->STATUS === TicketConstants::STATUS_RESOLVED
                || $this->ticketRepo->allTestersPassed($ticket->ID);
        }

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
        if ($ticket->EMPLOYID !== $empData['emp_id']) {
            return false;
        }

        if ($ticket->STATUS !== TicketConstants::STATUS_RETURNED) {
            return false;
        }

        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            $returnedBy = $this->ticketRepo->getReturnedBy($ticket->ID);
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

        if ($isTestingTicket) {
            $isAssignedTester = $this->ticketRepo->getAssignedTester($ticket->ID, $userId);

            if ($isAssignedTester) {
                if ($this->canTest($ticket, $currentUser)) {
                    return ['TEST'];
                }
                return [];
            }

            if ($ticket->EMPLOYID === $userId) {
                if ($this->canResubmit($ticket, $currentUser)) {
                    return [TicketConstants::WORKFLOW_RESUBMITTED];
                }
                return [];
            }

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

        if (!$workflowPath->requiresAssessment) {
            return false;
        }

        if ($currentUser['emp_id'] == $ticket->EMPLOYID) {
            return false;
        }

        if (!$this->isProgrammerOrSupervisor($userRoles)) {
            return false;
        }

        $wasReturned = $this->ticketRepo->ticketWorkflowExists($ticket->ID, TicketConstants::WORKFLOW_RETURNED);
        $validStatus = $ticket->STATUS == TicketConstants::STATUS_NEW
            || ($ticket->STATUS == TicketConstants::STATUS_TRIAGED && $wasReturned);

        return $validStatus;
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

        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            return false;
        }

        return $ticket->STATUS === TicketConstants::STATUS_RESOLVED
            && $ticket->EMPLOYID === $userId;
    }

    private function canResubmit($ticket, $currentUser): bool
    {
        if ($ticket->EMPLOYID !== $currentUser['emp_id']) {
            return false;
        }

        if ($ticket->STATUS !== TicketConstants::STATUS_RETURNED) {
            return false;
        }

        $isTestingTicket = in_array($ticket->TYPE_OF_REQUEST, [
            TicketConstants::REQUEST_TESTING,
            TicketConstants::REQUEST_PARALLEL_RUN
        ]);

        if ($isTestingTicket) {
            $returnedBy = $this->ticketRepo->getReturnedBy($ticket->ID);
            $wasTesterReturn = $this->ticketRepo->getAssignedTester($ticket->ID, $returnedBy);
            return (bool) $wasTesterReturn;
        }

        return true;
    }

    private function canTest($ticket, $currentUser): bool
    {
        if (!$this->ticketRepo->isTesterAssignedAndPending($ticket->ID, $currentUser['emp_id'])) {
            return false;
        }

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
    public function getTicketByCode($ticketId)
    {
        return $this->ticketRepo->getTicketById($ticketId);
    }
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

        // Make sure $parts is always an array
        $parts = is_array($value) ? $value : explode(',', $value);

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
    // PROJECT HELPERS
    // ========================

    private function syncProjectStatus(?string $projectName): void
    {
        if (empty($projectName)) return;

        try {
            $this->projectService->updateProjectStatusFromTickets($projectName);
        } catch (\Exception $e) {
            Log::warning('Failed to sync project status', [
                'project' => $projectName,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleProjectDeployment($ticket, $userId): void
    {
        if (empty($ticket->PROJECT_NAME)) return;


        try {
            $this->projectService->updateToDeployed(
                $ticket->PROJECT_NAME,
                $userId,
                $ticket->TYPE_OF_REQUEST,
                $ticket->TICKET_ID
            );
        } catch (\Exception $e) {
            Log::info('Project not deployed yet', [
                'project' => $ticket->PROJECT_NAME,
                'reason' => $e->getMessage()
            ]);
            $this->projectService->updateProjectStatusFromTickets($ticket->PROJECT_NAME);
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
    public function getAssignedTickets($empId)
    {

        return $this->ticketRepo->getAssignedTickets($empId);
    }
}
