<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Constants\TicketConstants;
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

    public function updateTesterStatus($ticketId, $testerId, $status, $remarks)
    {
        return DB::table('ticket_testers')
            ->where('TICKET_ID', $ticketId)
            ->where('TESTER_ID', $testerId)
            ->update([
                'STATUS' => $status,
                'REMARKS' => $remarks,
                'TESTED_AT' => now(),
            ]);
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
    public function getAssignedTickets($empId)
    {
        return DB::connection('mysql')->select('
        SELECT 
            t.TICKET_ID as value,
            CONCAT(t.TICKET_ID, " - ", t.PROJECT_NAME) as label,
            t.PROJECT_NAME,
            t.DETAILS,
            t.TYPE_OF_REQUEST
        FROM tickets t
        WHERE FIND_IN_SET(?, t.ASSIGNED_TO) > 0 
        AND t.STATUS IN ("5", "6") 
        AND t.DELETED_AT IS NULL 
        ORDER BY t.CREATED_AT DESC
    ', [$empId]);
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
    // GENERIC WORKFLOW ACTION EXECUTOR
    // ========================

    /**
     * Generic atomic transaction wrapper for workflow actions
     * Handles: workflow logging, status updates, history, remarks, assignments
     * 
     * @param array $data - Contains action details (ticket_id, action_type, action_by, statuses, etc.)
     * @param callable|null $extraAction - Additional database operations to execute within transaction
     * @return bool - Success status
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

                // 3. Execute extra database action (e.g., update testers, create tasks)
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
    public function updateTargetDate($ticketDbId, $targetDate)
    {
        DB::table('tickets')
            ->where('id', $ticketDbId)
            ->update(['target_date' => $targetDate]);
    }
    public function getTicketByCode($ticketId)
    {
        return DB::table('ticketing')->where('TICKET_ID', $ticketId)->first();
    }
}
