<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Constants\TicketConstants;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TicketRepository
{
    // ========================
    // QUERY BUILDERS
    // ========================

    public function queryTickets()
    {
        return DB::table('tickets')->whereNull('DELETED_AT');
    }

    public function getTicketById($ticketId)
    {
        return $this->queryTickets()
            ->where('TICKET_ID', $ticketId)
            ->first();
    }

    public function getChildTickets($parentTicketId)
    {
        return $this->queryTickets()
            ->where('PARENT_TICKET_ID', $parentTicketId)
            ->orderBy('CREATED_AT', 'ASC')
            ->get()
            ->toArray();
    }

    // ========================
    // TICKET OPTIONS
    // ========================

    public function getParentTickets()
    {
        return $this->queryTickets()
            ->where('TICKET_LEVEL', 'parent')
            ->select([
                'TICKET_ID as value',
                DB::raw('CONCAT(TICKET_ID, " - ", PROJECT_NAME) as label'),
                'PROJECT_NAME as project_name'
            ])
            ->orderBy('CREATED_AT', 'DESC')
            ->get()
            ->toArray();
    }

    public function getTicketProjectMap()
    {
        $tickets = $this->queryTickets()
            ->select(['TICKET_ID', 'PROJECT_NAME'])
            ->get();

        $map = [];
        foreach ($tickets as $t) {
            $map[$t->TICKET_ID] = $t->PROJECT_NAME;
        }

        return $map;
    }

    public function getEmployeeOptions()
    {
        return DB::connection('masterlist')
            ->table('employee_masterlist')
            ->where('ACCSTATUS', 1)
            ->where('EMPLOYID', '!=', 0)
            ->where('EMPPOSITION', '>=', 2)
            ->select([
                'EMPLOYID as value',
                DB::raw("CONCAT(EMPLOYID, ' - ', EMPNAME) as label")
            ])
            ->orderBy('EMPNAME', 'ASC')
            ->get()
            ->toArray();
    }

    public function getProjectOptions()
    {
        return DB::connection('projects')
            ->table('project_list')
            ->select([
                'PROJ_NAME as value',
                'PROJ_NAME as label'
            ])
            ->get()
            ->toArray();
    }

    public function getProgrammers()
    {
        return DB::connection('masterlist')
            ->table('employee_masterlist')
            ->where('ACCSTATUS', 1)
            ->where('EMPLOYID', '!=', 0)
            ->whereRaw('UPPER(DEPARTMENT) = ?', ['MIS'])
            ->where(function ($q) {
                $q->whereRaw('UPPER(JOB_TITLE) LIKE ?', ['%PROGRAMMER%'])
                    ->orWhereRaw('UPPER(JOB_TITLE) LIKE ?', ['%DEVELOPER%']);
            })
            ->select([
                'EMPLOYID as value',
                DB::raw("CONCAT(EMPLOYID, ' - ', EMPNAME) as label"),
                'EMPNAME as name',
                'JOB_TITLE'
            ])
            ->orderBy('EMPNAME', 'ASC')
            ->get()
            ->toArray();
    }

    public function getDistinctProjects()
    {
        return $this->queryTickets()
            ->distinct()
            ->pluck('PROJECT_NAME')
            ->toArray();
    }

    // ========================
    // ATTACHMENTS
    // ========================

    public function getAttachments($ticketId)
    {
        return DB::table('ticket_attachments')
            ->where('TICKET_ID', $ticketId)
            ->whereNull('DELETED_AT')
            ->orderBy('UPLOADED_AT', 'DESC')
            ->get()
            ->toArray();
    }

    public function handleAttachments($files, $ticketId, $uploadedBy)
    {
        $folder = 'attachmentFiles';
        if (!Storage::exists($folder)) {
            Storage::makeDirectory($folder);
        }

        $ticket = $this->getTicketById($ticketId);
        if (!$ticket) return;

        foreach ($files as $file) {
            $fileName = now()->format('Ymd') . "_{$ticketId}_{$uploadedBy}_" . $file->getClientOriginalName();
            $filePath = $file->storeAs('attachmentFiles', $fileName, 'public');

            DB::table('ticket_attachments')->insert([
                'TICKET_ID' => $ticket->ID,
                'FILE_NAME' => $fileName,
                'FILE_PATH' => $filePath,
                'FILE_SIZE' => $file->getSize(),
                'FILE_TYPE' => $file->getClientMimeType(),
                'UPLOADED_BY' => $uploadedBy,
                'UPLOADED_AT' => now(),
                'DELETED_AT' => null,
            ]);
        }
    }

    // ========================
    // WORKFLOW
    // ========================

    public function getTicketWorkflow($ticketId)
    {
        return DB::table('ticket_workflow')
            ->where('TICKET_ID', $ticketId)
            ->orderBy('ACTION_AT', 'DESC')
            ->get()
            ->toArray();
    }
    public function getReturnedBy(string $ticketId): ?string
    {
        $returnAction = DB::table('ticket_workflow')
            ->where('TICKET_ID', $ticketId)
            ->where('ACTION_TYPE', 'RETURN')
            ->orderBy('ACTION_AT', 'DESC')
            ->first();

        return $returnAction ? $returnAction->ACTION_BY : null;
    }

    public function ticketWorkflowExists($ticketId, $actionType): bool
    {
        return DB::table('ticket_workflow')
            ->where('TICKET_ID', $ticketId)
            ->where('ACTION_TYPE', $actionType)
            ->exists();
    }

    public function getWorkflowHistory(array $actionTypes, $ticketId): array
    {
        return DB::table('ticket_workflow')
            ->where('TICKET_ID', $ticketId)
            ->whereIn('ACTION_TYPE', $actionTypes)
            ->pluck('ACTION_TYPE')
            ->toArray();
    }

    public function wasTicketReturned($ticketId): bool
    {
        return $this->ticketWorkflowExists($ticketId, TicketConstants::WORKFLOW_RETURNED);
    }

    // ========================
    // REMARKS
    // ========================

    public function getRemarksHistory($ticketId)
    {
        return DB::table('remarks_history')
            ->where('TICKET_ID', $ticketId)
            ->orderBy('CREATED_AT', 'DESC')
            ->get()
            ->toArray();
    }

    public function insertRemark(
        $ticketId,
        $createdBy,
        $remarkType,
        $remarkText,
        $oldStatus = null,
        $newStatus = null,
        $oldAssignedTo = null,
        $newAssignedTo = null,
        $isInternal = false
    ) {
        return DB::table('remarks_history')->insert([
            'TICKET_ID' => $ticketId,
            'CREATED_BY' => $createdBy,
            'REMARK_TYPE' => $remarkType,
            'REMARK_TEXT' => $remarkText,
            'OLD_STATUS' => $oldStatus,
            'NEW_STATUS' => $newStatus,
            'OLD_ASSIGNED_TO' => $oldAssignedTo,
            'NEW_ASSIGNED_TO' => $newAssignedTo,
            'IS_INTERNAL' => $isInternal,
            'IS_SYSTEM_GENERATED' => false,
            'CREATED_AT' => now(),
            'UPDATED_AT' => now(),
        ]);
    }

    // ========================
    // TESTERS
    // ========================

    public function getTesters($ticketId, $testerId = null)
    {
        $query = DB::table('ticket_testers')
            ->where('TICKET_ID', $ticketId)
            ->whereNull('DELETED_AT');

        if ($testerId) {
            $query->where('TESTER_ID', $testerId);
        }

        return $testerId ? $query->first() : $query->get()->toArray();
    }

    public function insertTesters($ticketDbId, $testers)
    {
        foreach ($testers as $testerId) {
            DB::table('ticket_testers')->insert([
                'TICKET_ID' => $ticketDbId,
                'TESTER_ID' => $testerId,
                'ASSIGNED_AT' => now(),
                'STATUS' => 'PENDING',
            ]);
        }
    }

    public function isTesterAssignedAndPending($ticketId, $testerId): bool
    {
        return DB::table('ticket_testers')
            ->where('TICKET_ID', $ticketId)
            ->where('TESTER_ID', $testerId)
            ->where('STATUS', 'PENDING')
            ->whereNull('DELETED_AT')
            ->exists();
    }

    public function anyTesterFailed($ticketId): bool
    {
        return DB::table('ticket_testers')
            ->where('TICKET_ID', $ticketId)
            ->where('STATUS', 'FAILED')
            ->exists();
    }

    public function allTestersPassed($ticketId): bool
    {
        return !$this->anyTesterFailed($ticketId);
    }

    public function getTesterNamesFromMasterlist(array $testerIds)
    {
        if (empty($testerIds)) return [];

        return DB::connection('masterlist')
            ->table('employee_masterlist')
            ->whereIn('EMPLOYID', $testerIds)
            ->select(['EMPLOYID', 'EMPNAME'])
            ->get()
            ->toArray();
    }

    public function getTesterTicketIds($userId)
    {
        return DB::table('ticket_testers')
            ->where('TESTER_ID', $userId)
            ->whereNull('DELETED_AT')
            ->pluck('TICKET_ID')
            ->toArray();
    }

    public function getAssignedTester($ticketId, $userId)
    {
        return DB::table('ticket_testers')
            ->where('TICKET_ID', $ticketId)
            ->where('TESTER_ID', $userId)
            ->whereNull('DELETED_AT')
            ->first();
    }

    // ========================
    // EMPLOYEES
    // ========================

    public function getEmployeeNames(array $employeeIds)
    {
        if (empty($employeeIds)) return [];

        $rows = DB::connection('masterlist')
            ->table('employee_masterlist')
            ->whereIn('EMPLOYID', $employeeIds)
            ->select(['EMPLOYID', 'EMPNAME'])
            ->get();

        $names = [];
        foreach ($rows as $row) {
            $names[$row->EMPLOYID] = $row->EMPNAME;
        }

        return $names;
    }

    public function isDepartmentHead(string $empId): bool
    {
        return DB::connection('masterlist')
            ->table('employee_masterlist')
            ->where(function ($q) use ($empId) {
                $q->where('APPROVER2', $empId)
                    ->orWhere('APPROVER3', $empId);
            })
            ->exists();
    }

    public function getApproverIds($userId)
    {
        return DB::connection('masterlist')
            ->table('employee_masterlist')
            ->whereRaw("? IN (APPROVER1, APPROVER2, APPROVER3)", [$userId])
            ->pluck('EMPLOYID')
            ->toArray();
    }

    // ========================
    // TICKET CREATION
    // ========================

    public function generateTicketNumber(): string
    {
        $year = date('Y');
        $prefix = "TKT-{$year}-";

        $lastTicket = DB::table('tickets')
            ->where('TICKET_ID', 'like', "{$prefix}%")
            ->orderBy('TICKET_ID', 'DESC')
            ->first();

        $newNumber = $lastTicket
            ? ((int) substr($lastTicket->TICKET_ID, -3)) + 1
            : 1;

        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }

    public function generateChildTicketId($parentTicketId): string
    {
        $existingChildTickets = DB::table('tickets')
            ->where('PARENT_TICKET_ID', $parentTicketId)
            ->whereNull('DELETED_AT')
            ->orderBy('TICKET_ID', 'DESC')
            ->get();

        if ($existingChildTickets->isEmpty()) {
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

        return $parentTicketId . '-' . ($maxNumber + 1);
    }

    public function insertTicket($ticketId, $ticketLevel, $validated, $empData, $projectName, $status)
    {
        return DB::table('tickets')->insertGetId([
            'TICKET_ID' => $ticketId,
            'TICKET_LEVEL' => $ticketLevel,
            'PARENT_TICKET_ID' => $validated['parent_ticket'] ?? null,
            'TYPE_OF_REQUEST' => $validated['request_type'],
            'PROJECT_NAME' => $projectName,
            'DETAILS' => $validated['details'],
            'EMPLOYID' => $empData['emp_id'],
            'EMPNAME' => $empData['emp_name'],
            'DEPARTMENT' => $empData['emp_dept'],
            'STATUS' => $status,
            'ASSIGNED_TO' => null,
            'CREATED_AT' => now(),
            'UPDATED_AT' => now(),
            'DELETED_AT' => null,
        ]);
    }

    public function determineProjectName(array $validated): ?string
    {
        if ($validated['request_type'] == TicketConstants::REQUEST_NEW_SYSTEM) {
            return $validated['project_name'] ?? null;
        }

        if (!empty($validated['project'])) {
            return $validated['project'];
        }

        if (!empty($validated['parent_ticket'])) {
            $parent = DB::table('tickets')
                ->where('TICKET_ID', $validated['parent_ticket'])
                ->first();
            return $parent->PROJECT_NAME ?? null;
        }

        return null;
    }

    public function createProjectFromTicket($ticketId, $projectName, $details, $empData, $requestType)
    {
        $projectController = new ProjectController();

        return $projectController->createFromTicket(
            $projectName ?? ('Project for ' . $ticketId),
            $details,
            $empData['emp_dept'],
            $empData['emp_id'],
            $empData['emp_id'],
            $requestType,
            $ticketId
        );
    }

    // ========================
    // TICKET UPDATES
    // ========================

    public function updateTicketStatus($ticketDbId, $newStatus)
    {
        return DB::table('tickets')
            ->where('ID', $ticketDbId)
            ->update([
                'STATUS' => $newStatus,
                'UPDATED_AT' => now()
            ]);
    }

    public function updateTicketAssignment($ticketDbId, $assignedTo, $newStatus = null)
    {
        $data = [
            'ASSIGNED_TO' => $assignedTo,
            'UPDATED_AT' => now(),
        ];

        if ($newStatus !== null) {
            $data['STATUS'] = $newStatus;
        }

        return DB::table('tickets')
            ->where('ID', $ticketDbId)
            ->update($data);
    }

    // ========================
    // LOGGING
    // ========================

    public function logWorkflowAction($ticketId, $actionType, $actionBy, $remarks = null, $metadata = null)
    {
        return DB::table('ticket_workflow')->insert([
            'TICKET_ID' => $ticketId,
            'ACTION_TYPE' => $actionType,
            'ACTION_BY' => $actionBy,
            'ACTION_AT' => now(),
            'REMARKS' => $remarks,
            'METADATA' => $metadata ? json_encode($metadata) : null,
        ]);
    }

    public function logTicketHistory($ticketId, $action, $fieldName = null, $oldValue = null, $newValue = null, $changedBy)
    {
        return DB::table('tickets_history')->insert([
            'TICKET_ID' => $ticketId,
            'ACTION' => $action,
            'FIELD_NAME' => $fieldName,
            'OLD_VALUE' => $oldValue,
            'NEW_VALUE' => $newValue,
            'CHANGED_BY' => $changedBy,
            'CHANGED_AT' => now(),
        ]);
    }

    // ========================
    // STATUS COUNTS
    // ========================

    public function getStatusCounts($query)
    {
        return [
            'all' => (clone $query)->count(),
            'active' => (clone $query)->whereIn('STATUS', [
                TicketConstants::STATUS_NEW,
                TicketConstants::STATUS_TRIAGED
            ])->count(),
            'in_progress' => (clone $query)->where('STATUS', TicketConstants::STATUS_IN_PROGRESS)->count(),
            'closed' => (clone $query)->where('STATUS', TicketConstants::STATUS_CLOSED)->count(),
            'urgent' => (clone $query)
                ->whereIn('TYPE_OF_REQUEST', [
                    TicketConstants::REQUEST_TESTING,
                    TicketConstants::REQUEST_PARALLEL_RUN
                ])
                ->where('STATUS', '!=', TicketConstants::STATUS_CLOSED)
                ->count(),
        ];
    }

    // ========================
    // NOTIFICATIONS
    // ========================

    public function getTicketDetailsForNotification($ticketDbId)
    {
        return DB::table('tickets')
            ->where('ID', $ticketDbId)
            ->select(['EMPLOYID', 'DEPARTMENT', 'TICKET_ID', 'PROJECT_NAME', 'TYPE_OF_REQUEST'])
            ->first();
    }

    // ========================
    // GENERIC WORKFLOW ACTION EXECUTOR
    // ========================

    /**
     * Generic method for performing any ticket workflow action atomically
     * This handles all common workflow operations: assess, approve, assign, resolve, close, return, resubmit
     */
    public function performWorkflowAction(array $data, ?callable $extraAction = null): bool
    {
        try {
            return DB::transaction(function () use ($data, $extraAction) {
                // 1. Log workflow action
                $this->logWorkflowAction(
                    $data['ticket_id'],
                    $data['action_type'],
                    $data['action_by'],
                    $data['remark_text'] ?? $data['remarks'] ?? null,
                    $data['metadata'] ?? null
                );

                // 2. Update ticket status if provided
                if (isset($data['new_status'])) {
                    $this->updateTicketStatus($data['ticket_id'], $data['new_status']);
                }

                // 3. Workflow-specific action (task creation, project update, test submission, etc.)
                if ($extraAction) {
                    $extraAction($data);
                }

                // 4. Log ticket history if status changed
                if (isset($data['old_status'], $data['new_status'])) {
                    $this->logTicketHistory(
                        $data['ticket_id'],
                        $data['history_action'] ?? 'STATUS_CHANGE',
                        $data['field_name'] ?? 'STATUS',
                        $data['old_status'],
                        $data['new_status'],
                        $data['action_by']
                    );
                }

                // 5. Insert remark if provided
                if (isset($data['remark_text']) || isset($data['remarks'])) {
                    $this->insertRemark(
                        $data['ticket_id'],
                        $data['action_by'],
                        $data['remark_type'] ?? 'WORKFLOW',
                        $data['remark_text'] ?? $data['remarks'] ?? '',
                        $data['old_status'] ?? null,
                        $data['new_status'] ?? null,
                        $data['old_assigned_to'] ?? null,
                        $data['new_assigned_to'] ?? null,
                        $data['is_internal'] ?? false
                    );
                }

                // 6. Update assignment if provided
                if (isset($data['assigned_to'])) {
                    $this->updateTicketAssignment(
                        $data['ticket_id'],
                        $data['assigned_to'],
                        $data['new_status'] ?? null
                    );
                }

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Workflow action failed', [
                'ticket_id' => $data['ticket_id'] ?? 'unknown',
                'action_type' => $data['action_type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    // ========================
    // SPECIFIC WORKFLOW ACTIONS
    // ========================

    public function performAssessment(array $data): bool
    {
        return $this->performWorkflowAction($data);
    }

    public function performDHApproval(array $data): bool
    {
        return $this->performWorkflowAction($data, function ($data) {
            $this->updateProjectToReady(
                $data['project_name'],
                'DH_APPROVED',
                $data['action_by'],
                $data['request_type'],
                $data['ticket_number']
            );
        });
    }

    public function performApproval(array $data): bool
    {
        return $this->performWorkflowAction($data, function ($data) {
            if (isset($data['update_project']) && $data['update_project']) {
                $this->updateProjectToReady(
                    $data['project_name'],
                    $data['approval_type'],
                    $data['action_by'],
                    $data['request_type'],
                    $data['ticket_number']
                );
            }
        });
    }

    public function performAssignment(array $data): array
    {
        try {
            $taskId = null;

            $success = $this->performWorkflowAction($data, function ($data) use (&$taskId) {
                // Create linked task
                $taskId = $this->createTaskFromTicket(
                    $data['ticket_number'],
                    $data['project_name'],
                    $data['remark_text'],
                    $data['assigned_to'],
                    $data['action_by']
                );

                // Update project to IN_PROGRESS
                $this->updateProjectToInProgress(
                    $data['project_name'],
                    $data['assigned_to'],
                    $data['action_by'],
                    $data['request_type'],
                    $data['ticket_number']
                );
            });

            return [
                'success' => $success,
                'task_id' => $taskId,
                'message' => $success ? 'Assignment completed' : 'Assignment failed'
            ];
        } catch (\Exception $e) {
            Log::error('Assignment failed', [
                'ticket_id' => $data['ticket_id'],
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Assignment failed: ' . $e->getMessage()
            ];
        }
    }

    public function performResolution(array $data): array
    {
        $success = $this->performWorkflowAction(array_merge($data, [
            'action_type' => TicketConstants::WORKFLOW_RESOLVED,
            'action_by' => $data['resolved_by'] ?? null,
            'remark_text' => $data['remarks'] ?? null,
        ]), function ($d) {
            // Handle attachments for resolution
            if (!empty($d['attachments'])) {
                $this->handleAttachments(
                    $d['attachments'],
                    $d['ticket_number'] ?? null,
                    $d['resolved_by'] ?? null
                );
            }
        });

        return [
            'success' => $success,
            'message' => $success ? 'Ticket resolved' : 'Resolution failed'
        ];
    }

    public function performClosure(array $data): array
    {
        $success = $this->performWorkflowAction(array_merge($data, [
            'action_type' => TicketConstants::WORKFLOW_CLOSED,
            'action_by' => $data['closed_by'] ?? null,
            'remark_text' => $data['remarks'] ?? null,
            'metadata' => ['rating' => $data['rating'] ?? null],
        ]));

        return [
            'success' => $success,
            'message' => $success ? 'Ticket closed successfully' : 'Closure failed'
        ];
    }

    public function performReturn(array $data): array
    {
        $success = $this->performWorkflowAction(array_merge($data, [
            'action_type' => TicketConstants::WORKFLOW_RETURNED,
            'action_by' => $data['returned_by'] ?? null,
            'remark_text' => $data['remarks'] ?? null,
        ]));

        return [
            'success' => $success,
            'message' => $success ? 'Ticket returned successfully' : 'Return failed'
        ];
    }

    public function performResubmission(array $data): array
    {
        $success = $this->performWorkflowAction(array_merge($data, [
            'action_type' => TicketConstants::WORKFLOW_RESUBMITTED,
            'action_by' => $data['resubmitted_by'] ?? null,
            'remark_text' => 'Requestor resubmitted ticket after clarification.',
        ]));

        return [
            'success' => $success,
            'message' => $success ? 'Ticket resubmitted successfully' : 'Resubmission failed'
        ];
    }

    public function performSubmitTestResult(array $data): array
    {
        try {
            $success = $this->performWorkflowAction($data, function ($data) {
                // Update tester status
                DB::table('ticket_testers')
                    ->where('TICKET_ID', $data['ticket_id'])
                    ->where('TESTER_ID', $data['tester_id'])
                    ->update([
                        'STATUS' => $data['test_status'],
                        'REMARKS' => $data['remarks'],
                        'TESTED_AT' => now(),
                    ]);

                // Handle attachments
                if (!empty($data['attachments'])) {
                    $this->handleAttachments(
                        $data['attachments'],
                        $data['ticket_number'],
                        $data['tester_id']
                    );
                }

                // Additional remark for testing
                $this->insertRemark(
                    $data['ticket_id'],
                    $data['tester_id'],
                    'TESTING',
                    "Test result: {$data['test_status']} - {$data['remarks']}"
                );
            });

            // Check completion and pass status after transaction
            $testers = DB::table('ticket_testers')
                ->where('TICKET_ID', $data['ticket_id'])
                ->whereNull('DELETED_AT')
                ->get();

            $allCompleted = $testers->where('STATUS', 'PENDING')->isEmpty();
            $allPassed = $testers->where('STATUS', 'FAILED')->isEmpty();

            $message = "Test result submitted successfully";
            if ($allCompleted) {
                $message .= $allPassed ? ". All tests passed!" : ". Some tests failed";
            }

            return [
                'success' => $success,
                'message' => $message,
                'all_completed' => $allCompleted,
                'all_passed' => $allPassed,
                'next_step' => $allCompleted
                    ? ($allPassed ? 'close' : 'return')
                    : 'pending'
            ];
        } catch (\Exception $e) {
            Log::error('Test submission failed', [
                'ticket_id' => $data['ticket_id'],
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to submit test result: ' . $e->getMessage()
            ];
        }
    }

    // ========================
    // PROJECT INTEGRATION
    // ========================

    private function updateProjectToReady($projectName, $approvalType, $approvedBy, $requestType, $ticketId)
    {
        $projectController = new ProjectController();

        return $projectController->updateToReady(
            $projectName,
            $approvalType,
            $approvedBy,
            $requestType,
            $ticketId
        );
    }

    private function updateProjectToInProgress($projectName, $assignedTo, $assignedBy, $requestType, $ticketId)
    {
        $projectController = new ProjectController();

        return $projectController->updateToInProgress(
            $projectName,
            $assignedTo,
            $assignedBy,
            $requestType,
            $ticketId
        );
    }

    private function createTaskFromTicket($ticketId, $projectName, $remarks, $assignedTo, $createdBy)
    {
        $taskController = new \App\Http\Controllers\TaskController();

        return $taskController->createFromTicket(
            $ticketId,
            $projectName,
            $remarks,
            $assignedTo,
            $createdBy
        );
    }
}
