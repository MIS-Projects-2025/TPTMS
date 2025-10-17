<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    // ✅ Numeric status constants
    const STATUS_PENDING = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_ON_HOLD = 4;
    const STATUS_CANCELLED = 5;

    // ✅ Source type constants
    const SOURCE_TICKET = 1;
    const SOURCE_PROJECT = 2;
    const SOURCE_MANUAL = 3;

    /**
     * Helper function to get task DB connection
     */
    protected function taskDB()
    {
        return DB::connection('task'); // Use your task DB connection here
    }

    /**
     * Create task from ticket when ticket is assigned
     */
    public function createFromTicket($ticketCode, $projId, $remarks, $assignedTo, $createdBy)
    {
        $taskId = $this->taskDB()->table('daily_tasks')->insertGetId([
            'TASK_ID' => $this->generateTaskId(),
            'TASK_DATE' => now()->format('Y-m-d'),
            'EMPLOYID' => $createdBy,

            // ✅ use numeric source type
            'SOURCE_TYPE' => self::SOURCE_TICKET,
            'SOURCE_ID' => $ticketCode, // from ticket system
            'PROJ_ID' => $projId,

            'TASK_TITLE' => 'Ticket: ' . $ticketCode,
            'TASK_DESCRIPTION' => $remarks ?? 'Assigned for work',
            'PRIORITY' => 3,

            // ✅ numeric status
            'STATUS' => self::STATUS_PENDING,

            'CREATED_BY' => $createdBy,
            'CREATED_AT' => now(),
            'UPDATED_AT' => now(),
        ]);

        $this->logAction(
            $taskId,
            'CREATED',
            'Task created from ticket assignment',
            null,
            self::STATUS_PENDING,
            $assignedTo,
            $createdBy
        );

        return $taskId;
    }

    /**
     * Log task action to task_logs table
     */
    public function logAction($taskId, $actionType, $description, $oldStatus, $newStatus, $assignedTo, $createdBy)
    {
        $this->taskDB()->table('task_logs')->insert([
            'TASK_ID' => $taskId,
            'ACTION_TYPE' => $actionType,
            'DESCRIPTION' => $description,
            'OLD_STATUS' => $oldStatus,
            'NEW_STATUS' => $newStatus,
            'ASSIGNED_TO' => $assignedTo,
            'CREATED_BY' => $createdBy,
            'CREATED_AT' => now(),
        ]);
    }

    /**
     * Generate unique task ID (format: TSK-20251016-001)
     */
    private function generateTaskId()
    {
        $date = now()->format('Ymd');
        $count = $this->taskDB()->table('daily_tasks')
            ->whereDate('CREATED_AT', now())
            ->count() + 1;

        return 'TSK-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
