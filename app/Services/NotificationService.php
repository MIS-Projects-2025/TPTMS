<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketApprovedNotification;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Models\NotificationUser;

class NotificationService
{
    // Request type constants
    const REQUEST_NEW_SYSTEM = 1;
    const REQUEST_MODIFICATION = 2;
    const REQUEST_ENHANCEMENT = 3;
    const REQUEST_ADJUSTMENT = 4;
    const REQUEST_TESTING = 5;
    const REQUEST_PARALLEL_RUN = 6;

    /**
     * Send notification when ticket is created
     */
    public function notifyTicketCreated($ticketId, $requestType, $creatorName, $details, $projectName, $assignedTo = null)
    {
        Log::info("=== NOTIFYING TICKET CREATION: {$ticketId} ===");

        $requestTypeLabel = $this->getRequestTypeLabel($requestType);
        $recipients = $this->getRecipientsForCreation($requestType, $assignedTo);

        Log::info("Recipients for creation: " . json_encode($recipients));

        if (empty($recipients)) {
            Log::warning("No recipients found for ticket creation");
            return ['success' => 0, 'failed' => 0];
        }

        return $this->sendNotifications(
            $recipients,
            new TicketCreatedNotification(
                $ticketId,
                $requestType,
                $creatorName,
                $details,
                $projectName,
                $requestTypeLabel
            ),
            "TICKET_CREATED"
        );
    }

    /**
     * Send notification when assessment is complete
     */
    public function notifyAssessmentComplete($ticketId, $requestType, $requestorDept, $approvedBy, $projectName)
    {
        Log::info("=== NOTIFYING ASSESSMENT COMPLETE: {$ticketId} ===");

        $recipients = [];

        // Type 1,2,3,4: Notify Department Heads
        if (!in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            $recipients = $this->getDepartmentHeads($requestorDept);
            Log::info("Department Heads for {$requestorDept}: " . json_encode($recipients));
        }

        if (empty($recipients)) {
            Log::warning("No department heads found");
            return ['success' => 0, 'failed' => 0];
        }

        return $this->sendNotifications(
            $recipients,
            new TicketApprovedNotification($ticketId, $approvedBy, 'ASSESSMENT', $projectName),
            "ASSESSMENT_COMPLETE"
        );
    }

    /**
     * Send notification when DH approves
     */
    public function notifyDHApproved($ticketId, $requestType, $approvedBy, $projectName)
    {
        Log::info("=== NOTIFYING DH APPROVAL: {$ticketId} ===");

        $recipients = [];

        // Type 1,2,3: Notify OD
        if (in_array($requestType, [self::REQUEST_NEW_SYSTEM, self::REQUEST_MODIFICATION, self::REQUEST_ENHANCEMENT])) {
            $recipients = $this->getOperationsDirector();
            Log::info("Notifying OD for Type {$requestType}");
        }
        // Type 4: Notify MIS Supervisor (skip OD, go to assignment)
        elseif ($requestType === self::REQUEST_ADJUSTMENT) {
            $recipients = $this->getMISSupervisors();
            Log::info("Notifying MIS Supervisors for Type 4 (Adjustment)");
        }

        if (empty($recipients)) {
            Log::warning("No recipients found after DH approval");
            return ['success' => 0, 'failed' => 0];
        }

        return $this->sendNotifications(
            $recipients,
            new TicketApprovedNotification($ticketId, $approvedBy, 'DH', $projectName),
            "DH_APPROVED"
        );
    }

    /**
     * Send notification when OD approves
     */
    public function notifyODApproved($ticketId, $requestType, $approvedBy, $projectName)
    {
        Log::info("=== NOTIFYING OD APPROVAL: {$ticketId} ===");

        $recipients = [];

        // Type 1,2,3: Notify MIS Supervisor
        if (in_array($requestType, [self::REQUEST_NEW_SYSTEM, self::REQUEST_MODIFICATION, self::REQUEST_ENHANCEMENT])) {
            $recipients = $this->getMISSupervisors();
            Log::info("Notifying MIS Supervisors for OD approval");
        }

        if (empty($recipients)) {
            Log::warning("No recipients found after OD approval");
            return ['success' => 0, 'failed' => 0];
        }

        return $this->sendNotifications(
            $recipients,
            new TicketApprovedNotification($ticketId, $approvedBy, 'OD', $projectName),
            "OD_APPROVED"
        );
    }

    /**
     * Send notification when ticket is assigned
     */
    public function notifyTicketAssigned($ticketId, $requestType, $assignedTo, $assignedBy, $projectName)
    {
        Log::info("=== NOTIFYING TICKET ASSIGNMENT: {$ticketId} ===");

        // Skip for Type 5,6 (already notified at creation)
        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            Log::info("Skipping assignment notification for Type {$requestType}");
            return ['success' => 0, 'failed' => 0];
        }

        $recipients = $this->extractMultipleEmployeeIds($assignedTo);
        Log::info("Recipients for assignment: " . json_encode($recipients));

        if (empty($recipients)) {
            Log::warning("No recipients found for assignment");
            return ['success' => 0, 'failed' => 0];
        }

        return $this->sendNotifications(
            $recipients,
            new TicketAssignedNotification($ticketId, $assignedBy, $projectName),
            "TICKET_ASSIGNED"
        );
    }

    /**
     * Send notification when ticket is resolved
     */
    public function notifyTicketResolved($ticketId, $requestType, $requestorId, $resolvedBy, $projectName)
    {
        Log::info("=== NOTIFYING TICKET RESOLVED: {$ticketId} ===");

        $recipients = [];

        // Always notify requestor
        if (!empty($requestorId)) {
            $recipients[] = $requestorId;
        }

        // For testing/parallel run, also notify testers
        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            $testers = $this->getTicketTesters($ticketId);
            $recipients = array_merge($recipients, $testers);
            Log::info("Added testers to recipients: " . json_encode($testers));
        }

        $recipients = array_unique(array_filter($recipients));
        Log::info("Recipients for resolution: " . json_encode($recipients));

        if (empty($recipients)) {
            Log::warning("No recipients found for resolution");
            return ['success' => 0, 'failed' => 0];
        }

        return $this->sendNotifications(
            $recipients,
            new TicketResolvedNotification($ticketId, $resolvedBy, $projectName),
            "TICKET_RESOLVED"
        );
    }

    /**
     * Get recipients for ticket creation
     */
    private function getRecipientsForCreation($requestType, $assignedTo)
    {
        // Type 5,6: Notify assigned employee
        if (in_array($requestType, [self::REQUEST_TESTING, self::REQUEST_PARALLEL_RUN])) {
            if (!empty($assignedTo)) {
                return $this->extractMultipleEmployeeIds($assignedTo);
            }
            return [];
        }

        // Type 1,2,3,4: Notify MIS Programmers
        return $this->getMISProgrammers();
    }

    /**
     * Get MIS Programmers from masterlist
     */
    private function getMISProgrammers()
    {
        try {
            $programmers = DB::connection('masterlist')->select("
                SELECT DISTINCT EMPLOYID 
                FROM employee_masterlist 
                WHERE DEPARTMENT = 'MIS' 
                AND (
                    UPPER(JOB_TITLE) LIKE '%PROGRAMMER%'
                    OR (UPPER(JOB_TITLE) LIKE '%MIS%' AND UPPER(JOB_TITLE) LIKE '%SUPERVISOR%')
                )
                AND ACCSTATUS = 1
            ");

            return array_map(fn($p) => $p->EMPLOYID, $programmers);
        } catch (\Exception $e) {
            Log::error('Error fetching MIS programmers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get MIS Supervisors from masterlist
     */
    private function getMISSupervisors()
    {
        try {
            $supervisors = DB::connection('masterlist')->select("
                SELECT DISTINCT EMPLOYID 
                FROM employee_masterlist 
                WHERE DEPARTMENT = 'MIS' 
                AND UPPER(JOB_TITLE) LIKE '%SUPERVISOR%'
                AND ACCSTATUS = 1
            ");

            return array_map(fn($s) => $s->EMPLOYID, $supervisors);
        } catch (\Exception $e) {
            Log::error('Error fetching MIS supervisors: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Operations Director from masterlist
     */
    private function getOperationsDirector()
    {
        try {
            $od = DB::connection('masterlist')->select("
                SELECT DISTINCT EMPLOYID 
                FROM employee_masterlist 
                WHERE (
                    DEPARTMENT = 'OPERATIONS' 
                    OR UPPER(JOB_TITLE) LIKE '%OPERATIONS%DIRECTOR%'
                )
                AND ACCSTATUS = 1
            ");

            return array_map(fn($o) => $o->EMPLOYID, $od);
        } catch (\Exception $e) {
            Log::error('Error fetching OD: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get Department Heads from masterlist
     */
    private function getDepartmentHeads($dept)
    {
        try {
            $heads = DB::connection('masterlist')->select("
                SELECT DISTINCT EMPLOYID 
                FROM employee_masterlist 
                WHERE DEPARTMENT = ?
                AND (APPROVER2 IS NOT NULL OR APPROVER3 IS NOT NULL)
                AND ACCSTATUS = 1
            ", [$dept]);

            return array_map(fn($h) => $h->EMPLOYID, $heads);
        } catch (\Exception $e) {
            Log::error('Error fetching department heads: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get testers for a ticket
     */
    private function getTicketTesters($ticketId)
    {
        try {
            $testers = DB::select("
                SELECT DISTINCT TESTER_ID 
                FROM ticket_testers 
                WHERE TICKET_ID = ?
                AND DELETED_AT IS NULL
            ", [$ticketId]);

            return array_map(fn($t) => $t->TESTER_ID, $testers);
        } catch (\Exception $e) {
            Log::error('Error fetching testers: ' . $e->getMessage());
            return [];
        }
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
            $part = trim($part);
            // Remove parentheses if present: "123 (Name)" → "123"
            if (strpos($part, '(') !== false) {
                $part = trim(substr($part, 0, strpos($part, '(')));
            }
            if (!empty($part)) {
                $ids[] = $part;
            }
        }

        return array_unique($ids);
    }

    /**
     * Send notifications to multiple recipients
     * FIXED: Use NotificationUser instead of User
     */
    private function sendNotifications($recipients, $notification, $notificationType)
    {
        $successCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipientId) {
            try {
                // FIXED: Changed from User::find() to NotificationUser
                $user = NotificationUser::where('emp_id', $recipientId)->first();

                // Auto-create if doesn't exist
                if (!$user) {
                    $user = NotificationUser::create([
                        'emp_id' => $recipientId,
                        'emp_name' => 'User ' . $recipientId,
                        'emp_dept' => 'Unknown',
                    ]);
                    Log::info("Created new NotificationUser: {$recipientId}");
                }

                if ($user) {
                    $user->notify($notification);
                    $successCount++;
                    Log::info("✓ {$notificationType} sent to user: {$recipientId}");
                } else {
                    $failedCount++;
                    Log::warning("Failed to create or find user: {$recipientId}");
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error("Failed to notify {$recipientId}: " . $e->getMessage());
            }
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'total' => count($recipients),
        ];
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
}
