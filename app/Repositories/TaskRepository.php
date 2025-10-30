<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Constants\TaskConstants;

class TaskRepository
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DB::connection('task');
    }

    /**
     * Get all tasks (for main tasks page)
     */
    public function getTasksForUser($empId)
    {
        return $this->connection->table('daily_tasks')
            ->whereNull('DELETED_AT')
            ->orderByDesc('CREATED_AT')
            ->get();
    }

    /**
     * Get existing tasks for specific employee (limited to 10)
     */
    public function getExistingTasks($empId)
    {
        return $this->connection->select('
            SELECT 
                TASK_ID, EMPLOYID, TASK_TITLE, SOURCE_TYPE, SOURCE_ID,
                STATUS, PRIORITY, CREATED_AT
            FROM daily_tasks 
            WHERE EMPLOYID = ? 
            AND DELETED_AT IS NULL
            ORDER BY 
                CASE WHEN STATUS = ? THEN 0 ELSE 1 END,
                PRIORITY ASC,
                CREATED_AT DESC
            LIMIT 10
        ', [$empId, TaskConstants::STATUS_IN_PROGRESS]);
    }

    /**
     * Find specific task by ID
     */
    public function findTask($taskId)
    {
        return $this->connection->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->whereNull('DELETED_AT')
            ->first();
    }

    /**
     * Update task status
     */
    public function updateTaskStatus($taskId, $status, $updatedBy)
    {
        return $this->connection->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->update([
                'STATUS' => $status,
                'UPDATED_BY' => $updatedBy,
                'UPDATED_AT' => now(),
            ]);
    }

    /**
     * Update progress notes
     */
    public function updateProgressNotes($taskId, $notes)
    {
        return $this->connection->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->update([
                'PROGRESS_NOTES' => $notes,
                'UPDATED_AT' => now(),
            ]);
    }

    /**
     * Create new task
     */
    public function createTask(array $taskData)
    {
        return $this->connection->table('daily_tasks')->insert($taskData);
    }

    /**
     * Get task logs/history
     */
    public function getTaskLogs($taskId)
    {
        return $this->connection->table('task_logs')
            ->where('TASK_ID', $taskId)
            ->orderByDesc('CREATED_AT')
            ->get();
    }

    /**
     * Log task action
     */
    public function logAction(array $logData)
    {
        return $this->connection->table('task_logs')->insert($logData);
    }

    /**
     * Count remaining tasks for auto-resolution
     */
    public function countRemainingTasks($sourceType, $sourceId)
    {
        return $this->connection->table('daily_tasks')
            ->where('SOURCE_TYPE', $sourceType)
            ->where('SOURCE_ID', $sourceId)
            ->whereNull('DELETED_AT')
            ->where('STATUS', '!=', TaskConstants::STATUS_COMPLETED)
            ->count();
    }

    /**
     * Count tasks created today for ID generation
     */
    public function countTodayTasks()
    {
        return $this->connection->table('daily_tasks')
            ->whereDate('CREATED_AT', now())
            ->count();
    }
}
