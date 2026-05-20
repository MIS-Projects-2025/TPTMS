<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class TaskRepository
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DB::connection('task');
    }

    /**
     * Insert or create new task
     */
    public function createTask(array $taskData)
    {
        return $this->connection->table('daily_tasks')->insert($taskData);
    }

    /**
     * Get tasks for current user
     */
    public function getTasksForUser($empId)
    {
        return $this->connection->table('daily_tasks')
            ->where('EMPLOYID', $empId)
            ->whereNull('DELETED_AT')
            ->orderByDesc('CREATED_AT')
            ->get();
    }
    public function getLatestTaskId()
    {
        return DB::connection('task')
            ->table('daily_tasks')
            ->orderBy('ID', 'desc')
            ->value('TASK_ID');
    }
    public function getProgrammersList()
    {
        return DB::connection('masterlist')
            ->table('employee_masterlist')
            ->Where('JOB_TITLE', 'like', '%Programmer%')
            ->where('ACCSTATUS', 1)
            ->select('EMPLOYID', 'EMPNAME')
            ->orderBy('EMPLOYID')
            ->get()
            ->toArray();
    }
    public function getAllTasks()
    {
        return $this->connection->table('daily_tasks')
            ->whereNull('DELETED_AT')
            ->orderByDesc('CREATED_AT')
            ->get();
    }
    public function getEmployeesByIds($ids)
    {
        if (empty($ids)) {
            return [];
        }

        // ✅ Use the DB facade directly (no "$this->DB::")
        return DB::connection('masterlist')
            ->table('employee_masterlist') // ✅ match your actual table name
            ->select('EMPLOYID', 'FIRSTNAME', 'MIDDLE_INITIAL', 'LASTNAME') // ✅ match column names
            ->whereIn('EMPLOYID', $ids)
            ->get()
            ->toArray();
    }



    public function getExistingTasks($empId)
    {
        return $this->connection->select('
            SELECT TASK_ID, EMPLOYID, TASK_TITLE, SOURCE_TYPE, SOURCE_ID, STATUS, PRIORITY, CREATED_AT
            FROM daily_tasks
            WHERE EMPLOYID = ?
              AND DELETED_AT IS NULL
            ORDER BY CASE WHEN STATUS = 1 THEN 0 ELSE 1 END,
                     PRIORITY ASC,
                     CREATED_AT DESC
            LIMIT 10
        ', [$empId]);
    }

    public function findTask($taskId)
    {
        return $this->connection->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->whereNull('DELETED_AT')
            ->first();
    }

    public function updateTaskStatus($taskId, $status, $updatedBy)
    {
        return $this->connection->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->update([
                'STATUS'     => $status,
                'UPDATED_BY' => $updatedBy,
                'UPDATED_AT' => now(),
            ]);
    }

    public function updateProgressNotes($taskId, $notes)
    {
        return $this->connection->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->update([
                'PROGRESS_NOTES' => $notes,
                'UPDATED_AT'     => now(),
            ]);
    }

    public function getTaskLogs($taskId)
    {
        return $this->connection->table('task_logs')
            ->where('TASK_ID', $taskId)
            ->orderByDesc('CREATED_AT')
            ->get();
    }

    public function logAction(array $logData)
    {
        return $this->connection->table('task_logs')->insert($logData);
    }

    public function countRemainingTasks($sourceType, $sourceId)
    {
        return $this->connection->table('daily_tasks')
            ->where('SOURCE_TYPE', $sourceType)
            ->where('SOURCE_ID', $sourceId)
            ->whereNull('DELETED_AT')
            ->where('STATUS', '!=', 3)
            ->count();
    }

    public function countTodayTasks()
    {
        return $this->connection->table('daily_tasks')
            ->whereDate('CREATED_AT', now())
            ->count();
    }
}
