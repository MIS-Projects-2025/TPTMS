<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketApprovedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketClosedNotification;
use App\Notifications\TicketReturnedNotification;
use App\Notifications\TicketResubmittedNotification;
use App\Models\NotificationUser;
use App\Notifications\ProjectStatusChangedNotification;

class NotificationService
{
    const REQUEST_NEW_SYSTEM = 1;
    const REQUEST_MODIFICATION = 2;
    const REQUEST_ENHANCEMENT = 3;
    const REQUEST_ADJUSTMENT = 4;
    const REQUEST_TESTING = 5;
    const REQUEST_PARALLEL_RUN = 6;

    public function notifyTicketCreated($ticketId, $requestType, $creatorName, $details, $projectName, $assignedTo = null)
    {
        Log::info("=== NOTIFYING TICKET CREATION: {$ticketId} ===");

        $requestTypeLabel = $this->getRequestTypeLabel($requestType);
        $recipients = $this->getRecipientsForCreation($requestType, $assignedTo);

        if (empty($recipients)) {
            Log::warning("No recipients found for ticket creation");
            return ['success' => 0, 'failed' => 0];
        }

        // Determine action required per recipient
        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            $actionRequiredMap[$rec] = in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN]) ? 'TEST' : 'ASSESS';
        }

        return $this->sendNotifications(
            $recipients,
            new TicketCreatedNotification($ticketId, $requestType, $creatorName, $details, $projectName, $requestTypeLabel),
            "TICKET_CREATED",
            $actionRequiredMap
        );
    }

    public function notifyAssessmentComplete($ticketId, $requestType, $requestorId, $approvedBy, $projectName)
    {
        Log::info("=== NOTIFYING ASSESSMENT COMPLETE: {$ticketId} ===");

        $recipients = [];
        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            $recipients[] = $requestorId;
        } else {
            $recipients = $this->getRequestorApprovers($requestorId);
        }

        if (empty($recipients)) {
            return ['success' => 0, 'failed' => 0];
        }

        // Action required: requestor closes or approvers approve
        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            $actionRequiredMap[$rec] = ($rec == $requestorId) ? 'CLOSE' : 'APPROVE';
        }

        return $this->sendNotifications(
            $recipients,
            new TicketApprovedNotification($ticketId, $approvedBy, 'ASSESSMENT', $projectName),
            "ASSESSMENT_COMPLETE",
            $actionRequiredMap
        );
    }

    public function notifyDHApproved($ticketId, $requestType, $approvedBy, $projectName)
    {
        $recipients = [];
        if (in_array($requestType, [self::REQUEST_NEW_SYSTEM, self::REQUEST_MODIFICATION, self::REQUEST_ENHANCEMENT])) {
            $recipients = $this->getOperationsDirector();
        } elseif ($requestType === self::REQUEST_ADJUSTMENT) {
            $recipients = $this->getMISSupervisors();
        }

        if (empty($recipients)) {
            return ['success' => 0, 'failed' => 0];
        }

        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            $actionRequiredMap[$rec] = ($requestType === self::REQUEST_ADJUSTMENT) ? 'RESOLVE' : 'APPROVE';
        }

        return $this->sendNotifications(
            $recipients,
            new TicketApprovedNotification($ticketId, $approvedBy, 'DH', $projectName),
            "DH_APPROVED",
            $actionRequiredMap
        );
    }

    public function notifyODApproved($ticketId, $requestType, $approvedBy, $projectName)
    {
        $recipients = $this->getMISSupervisors();
        if (empty($recipients)) return ['success' => 0, 'failed' => 0];

        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            $actionRequiredMap[$rec] = 'ASSIGN';
        }

        return $this->sendNotifications(
            $recipients,
            new TicketApprovedNotification($ticketId, $approvedBy, 'OD', $projectName),
            "OD_APPROVED",
            $actionRequiredMap
        );
    }

    public function notifyTicketAssigned($ticketId, $requestType, $assignedTo, $assignedBy, $projectName)
    {
        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            return ['success' => 0, 'failed' => 0];
        }

        $recipients = $this->extractMultipleEmployeeIds($assignedTo);
        if (empty($recipients)) return ['success' => 0, 'failed' => 0];

        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            $actionRequiredMap[$rec] = 'RESOLVE';
        }

        return $this->sendNotifications(
            $recipients,
            new TicketAssignedNotification($ticketId, $assignedBy, $projectName),
            "TICKET_ASSIGNED",
            $actionRequiredMap
        );
    }

    public function notifyTicketResolved($ticketId, $requestType, $requestorId, $resolvedBy, $projectName)
    {
        $recipients = [];
        if (!empty($requestorId)) $recipients[] = $requestorId;

        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            $testers = $this->getTicketTesters($ticketId);
            $recipients = array_merge($recipients, $testers);
        }

        $recipients = array_unique(array_filter($recipients));
        if (empty($recipients)) return ['success' => 0, 'failed' => 0];

        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            if ($rec === $requestorId) $actionRequiredMap[$rec] = 'CLOSE';
            elseif (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) $actionRequiredMap[$rec] = 'TEST';
            else $actionRequiredMap[$rec] = null;
        }

        return $this->sendNotifications(
            $recipients,
            new TicketResolvedNotification($ticketId, $resolvedBy, $projectName),
            "TICKET_RESOLVED",
            $actionRequiredMap
        );
    }
    // Add these methods to your NotificationService class

    public function notifyTicketClosed($ticketId, $requestType, $closedBy, $projectName, $rating = null)
    {
        Log::info("=== NOTIFYING TICKET CLOSURE: {$ticketId} ===");

        $recipients = [];

        // Get programmers who worked on it
        $assignedProgrammers = $this->getTicketAssignees($ticketId);
        $recipients = array_merge($recipients, $assignedProgrammers);

        // Get approvers based on request type
        if (in_array($requestType, [self::REQUEST_NEW_SYSTEM, self::REQUEST_MODIFICATION, self::REQUEST_ENHANCEMENT])) {
            // Get DH and OD approvers
            $dhApprovers = $this->getDepartmentHeads($ticketId);
            $odApprovers = $this->getOperationsDirector();
            $recipients = array_merge($recipients, $dhApprovers, $odApprovers);
        } elseif ($requestType === self::REQUEST_ADJUSTMENT) {
            // Get DH and MIS Supervisors
            $dhApprovers = $this->getDepartmentHeads($ticketId);
            $misSuper = $this->getMISSupervisors();
            $recipients = array_merge($recipients, $dhApprovers, $misSuper);
        } elseif (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            // Get MIS Supervisors
            $recipients = array_merge($recipients, $this->getMISSupervisors());
        }

        $recipients = array_unique(array_filter($recipients));

        if (empty($recipients)) {
            Log::warning("No recipients found for ticket closure");
            return ['success' => 0, 'failed' => 0];
        }

        // No action required - just informational
        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            $actionRequiredMap[$rec] = null;
        }

        return $this->sendNotifications(
            $recipients,
            new TicketClosedNotification($ticketId, $closedBy, $projectName, $rating),
            "TICKET_CLOSED",
            $actionRequiredMap
        );
    }

    public function notifyTicketReturned($ticketId, $requestorId, $returnedBy, $projectName, $remarks)
    {
        Log::info("=== NOTIFYING TICKET RETURN: {$ticketId} ===");

        if (empty($requestorId)) {
            Log::warning("No requestor found for ticket return");
            return ['success' => 0, 'failed' => 0];
        }

        $recipients = [$requestorId];

        $actionRequiredMap = [];
        $actionRequiredMap[$requestorId] = 'CLARIFY';

        return $this->sendNotifications(
            $recipients,
            new TicketReturnedNotification($ticketId, $returnedBy, $projectName, $remarks),
            "TICKET_RETURNED",
            $actionRequiredMap
        );
    }


    public function notifyTicketResubmitted($ticketId, $requestType, $resubmittedBy, $projectName, $returnedById)
    {
        Log::info("=== NOTIFYING TICKET RESUBMISSION: {$ticketId} ===");

        $recipients = [];

        // Notify the person who returned it
        if (!empty($returnedById)) {
            $recipients[] = $returnedById;
        }

        // Also notify programmers/supervisors who need to reassess
        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            $testers = $this->getTicketTesters($ticketId);
            $recipients = array_merge($recipients, $testers);
        } else {
            $programmers = $this->getMISProgrammers();
            $recipients = array_merge($recipients, $programmers);
        }

        $recipients = array_unique(array_filter($recipients));

        if (empty($recipients)) {
            Log::warning("No recipients found for ticket resubmission");
            return ['success' => 0, 'failed' => 0];
        }

        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            // The person who returned it needs to reassess
            // Programmers/testers need to assess/test
            $actionRequiredMap[$rec] = ($rec === $returnedById) ? 'REASSESS' : (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN]) ? 'TEST' : 'ASSESS');
        }

        return $this->sendNotifications(
            $recipients,
            new TicketResubmittedNotification($ticketId, $resubmittedBy, $projectName),
            "TICKET_RESUBMITTED",
            $actionRequiredMap
        );
    }

    // Helper methods to add
    private function getTicketAssignees($ticketId)
    {
        $ticket = DB::selectOne("SELECT ASSIGNED_TO FROM tickets WHERE TICKET_ID=? AND DELETED_AT IS NULL", [$ticketId]);
        if (!$ticket || empty($ticket->ASSIGNED_TO)) return [];
        return $this->extractMultipleEmployeeIds($ticket->ASSIGNED_TO);
    }

    private function getDepartmentHeads($ticketId)
    {
        // Get the requestor's department and find their department head
        $ticket = DB::selectOne("SELECT EMPLOYID FROM tickets WHERE TICKET_ID=? AND DELETED_AT IS NULL", [$ticketId]);
        if (!$ticket) return [];

        $approvers = DB::connection('masterlist')->selectOne(
            "SELECT APPROVER2, APPROVER3 FROM employee_masterlist WHERE EMPLOYID=? AND ACCSTATUS=1 LIMIT 1",
            [$ticket->EMPLOYID]
        );

        $heads = [];
        if ($approvers) {
            if (!empty($approvers->APPROVER2)) $heads[] = $approvers->APPROVER2;
            if (!empty($approvers->APPROVER3)) $heads[] = $approvers->APPROVER3;
        }
        return array_unique(array_filter($heads));
    }
    /*** Utility functions ***/
    private function getRecipientsForCreation($requestType, $assignedTo)
    {
        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            return !empty($assignedTo) ? $this->extractMultipleEmployeeIds($assignedTo) : [];
        }
        return $this->getMISProgrammers();
    }

    private function sendNotifications($recipients, $notification, $notificationType, $actionRequiredMap = [])
    {
        $success = 0;
        $failed = 0;

        foreach ($recipients as $recipientId) {
            try {
                $user = NotificationUser::firstOrCreate(
                    ['emp_id' => $recipientId],
                    ['emp_name' => $this->getEmployeeName($recipientId), 'emp_dept' => $this->getEmployeeDept($recipientId)]
                );

                // Set action_required dynamically
                $action = $actionRequiredMap[$recipientId] ?? null;
                $notification->setActionRequired($action);

                $user->notify($notification);
                $success++;
            } catch (\Exception $e) {
                Log::error("Failed to notify {$recipientId}: " . $e->getMessage());
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed, 'total' => count($recipients)];
    }

    private function getEmployeeName($empId)
    {
        $emp = DB::connection('masterlist')->selectOne("SELECT EMPNAME FROM employee_masterlist WHERE EMPLOYID=? LIMIT 1", [$empId]);
        return $emp ? $emp->EMPNAME : 'User ' . $empId;
    }

    private function getEmployeeDept($empId)
    {
        $emp = DB::connection('masterlist')->selectOne("SELECT DEPARTMENT FROM employee_masterlist WHERE EMPLOYID=? LIMIT 1", [$empId]);
        return $emp ? $emp->DEPARTMENT : 'Unknown';
    }

    private function extractMultipleEmployeeIds($value)
    {
        // If it's already an array, return it
        if (is_array($value)) {
            return array_filter(array_map('trim', $value));
        }

        // If it's empty, return empty array
        if (empty($value)) {
            return [];
        }

        // If it's a string, process it
        $parts = explode(',', $value);
        $ids = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, '(') !== false) {
                $part = trim(substr($part, 0, strpos($part, '(')));
            }
            if (!empty($part)) {
                $ids[] = $part;
            }
        }
        return array_unique($ids);
    }

    private function getMISProgrammers()
    {
        $programmers = DB::connection('masterlist')->select("
            SELECT DISTINCT EMPLOYID FROM employee_masterlist 
            WHERE DEPARTMENT='MIS' AND (UPPER(JOB_TITLE) LIKE '%PROGRAMMER%' OR (UPPER(JOB_TITLE) LIKE '%MIS%' AND UPPER(JOB_TITLE) LIKE '%SUPERVISOR%')) 
            AND ACCSTATUS=1
        ");
        return array_map(fn($p) => $p->EMPLOYID, $programmers);
    }

    private function getMISSupervisors()
    {
        $supervisors = DB::connection('masterlist')->select("
            SELECT DISTINCT EMPLOYID FROM employee_masterlist 
            WHERE DEPARTMENT='MIS' AND UPPER(JOB_TITLE) LIKE '%SUPERVISOR%' AND ACCSTATUS=1
        ");
        return array_map(fn($s) => $s->EMPLOYID, $supervisors);
    }

    private function getOperationsDirector()
    {
        $od = DB::connection('masterlist')->select("
            SELECT DISTINCT EMPLOYID FROM employee_masterlist 
            WHERE DEPARTMENT='OPERATIONS' OR UPPER(JOB_TITLE) LIKE '%OPERATIONS%DIRECTOR%' AND ACCSTATUS=1
        ");
        return array_map(fn($o) => $o->EMPLOYID, $od);
    }

    private function getTicketTesters($ticketId)
    {
        $testers = DB::select("SELECT DISTINCT TESTER_ID FROM ticket_testers WHERE TICKET_ID=? AND DELETED_AT IS NULL", [$ticketId]);
        return array_map(fn($t) => $t->TESTER_ID, $testers);
    }

    private function getRequestorApprovers($requestorId)
    {
        $approvers = DB::connection('masterlist')->selectOne("SELECT APPROVER2, APPROVER3 FROM employee_masterlist WHERE EMPLOYID=? AND ACCSTATUS=1 LIMIT 1", [$requestorId]);
        $recipients = [];
        if (!empty($approvers)) {
            if (!empty($approvers->APPROVER2)) $recipients[] = $approvers->APPROVER2;
            if (!empty($approvers->APPROVER3)) $recipients[] = $approvers->APPROVER3;
        }
        return array_unique(array_filter($recipients));
    }

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

    /**
     * Notify when project status changes
     * Notifies: Requestor, Handler, Assigned Programmers, Supervisors+, Directors, and Presidents
     * This is informational only - no action required
     */
    public function notifyProjectStatusChanged($projectId, $oldStatus, $newStatus, $changedBy, $projectName, $department)
    {
        Log::info("=== NOTIFYING PROJECT STATUS CHANGE: {$projectId} ===", [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'project' => $projectName
        ]);

        $recipients = $this->getProjectStatusChangeRecipients($projectId, $department);

        if (empty($recipients)) {
            Log::warning("No recipients found for project status change notification");
            return ['success' => 0, 'failed' => 0];
        }

        // No action required - informational only
        $actionRequiredMap = [];
        foreach ($recipients as $rec) {
            $actionRequiredMap[$rec] = null;
        }

        return $this->sendNotifications(
            $recipients,
            new ProjectStatusChangedNotification(
                $projectId,
                $projectName,
                $oldStatus,
                $newStatus,
                $changedBy,
                $department
            ),
            "PROJECT_STATUS_CHANGED",
            $actionRequiredMap
        );
    }

    /**
     * Get recipients for project status change notification
     * Based on EMPPOSITION hierarchy
     */
    private function getProjectStatusChangeRecipients($projectId, $department)
    {
        $recipients = [];

        // Get project details
        $project = DB::connection('projects')->selectOne("
        SELECT PROJ_REQUESTOR, PROJ_HANDLER, ASSIGNED_PROGS 
        FROM project_list 
        WHERE PROJ_ID = ? AND DELETED_AT IS NULL
    ", [$projectId]);

        if (!$project) {
            Log::warning("Project not found: {$projectId}");
            return [];
        }

        // Add requestor
        if (!empty($project->PROJ_REQUESTOR)) {
            $recipients[] = $project->PROJ_REQUESTOR;
        }

        // Add handler
        if (!empty($project->PROJ_HANDLER)) {
            $recipients[] = $project->PROJ_HANDLER;
        }

        // Add assigned programmers
        if (!empty($project->ASSIGNED_PROGS)) {
            $assignedProgs = $this->extractMultipleEmployeeIds($project->ASSIGNED_PROGS);
            $recipients = array_merge($recipients, $assignedProgs);
        }

        // Add department hierarchy (Supervisor, Section Head, Manager)
        $deptHierarchy = $this->getDepartmentHierarchy($department);
        $recipients = array_merge($recipients, $deptHierarchy);

        // Always add Directors (position 5)
        $directors = $this->getEmployeesByPosition(5);
        $recipients = array_merge($recipients, $directors);

        // Always add Presidents (position 6)
        $presidents = $this->getEmployeesByPosition(6);
        $recipients = array_merge($recipients, $presidents);

        // Remove duplicates and filter empty values
        return array_unique(array_filter($recipients));
    }

    /**
     * Get department hierarchy (Supervisors, Section Heads, Managers)
     * EMPPOSITION: 2-Supervisor, 3-Section Head, 4-Manager
     */
    private function getDepartmentHierarchy($department)
    {
        if (empty($department)) {
            return [];
        }

        $hierarchy = DB::connection('masterlist')->select("
        SELECT DISTINCT EMPLOYID 
        FROM employee_masterlist 
        WHERE DEPARTMENT = ? 
        AND EMPPOSITION IN (2, 3, 4)
        AND ACCSTATUS = 1
    ", [$department]);

        return array_map(fn($h) => $h->EMPLOYID, $hierarchy);
    }

    /**
     * Get employees by position
     * 0-Admin, 1-Rank&File, 2-Supervisor, 3-SectionHead, 4-Manager, 5-Director, 6-President
     */
    private function getEmployeesByPosition($position)
    {
        $employees = DB::connection('masterlist')->select("
        SELECT DISTINCT EMPLOYID 
        FROM employee_masterlist 
        WHERE EMPPOSITION = ? 
        AND ACCSTATUS = 1
    ", [$position]);

        return array_map(fn($e) => $e->EMPLOYID, $employees);
    }
}
