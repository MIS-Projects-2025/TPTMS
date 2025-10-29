<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

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

    protected function taskDB()
    {
        return DB::connection('task');
    }

    public function getTask()
    {
        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        try {
            $tasks = $this->taskDB()->table('daily_tasks')
                ->whereNull('DELETED_AT')
                ->orderByDesc('CREATED_AT')
                ->get()
                ->map(function ($task) {
                    return $this->formatTask($task);
                });

            // Debug: Log the tasks
            Log::info('Tasks loaded:', ['count' => $tasks->count()]);

            return Inertia::render('Tasks/Index', [
                'tasks' => $tasks->toArray(), // Convert collection to array
                'currentUser' => $empData['emp_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Error loading tasks: ' . $e->getMessage());

            return Inertia::render('Tasks/Index', [
                'tasks' => [],
                'currentUser' => $empData['emp_id'] ?? null,
                'error' => 'Failed to load tasks',
            ]);
        }
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, $taskId)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'status' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:500',
        ]);

        $task = $this->taskDB()->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->whereNull('DELETED_AT')
            ->first();

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        $oldStatus = $task->STATUS;
        $newStatus = $validated['status'];

        // Update task status
        $this->taskDB()->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->update([
                'STATUS' => $newStatus,
                'UPDATED_BY' => $empData['emp_id'],
                'UPDATED_AT' => now(),
            ]);

        // Log the status change
        $actionType = $this->getActionType($newStatus);
        $description = $validated['remarks'] ?? $this->getDefaultDescription($oldStatus, $newStatus);

        $this->logAction(
            $taskId,
            $actionType,
            $description,
            $oldStatus,
            $newStatus,
            $task->EMPLOYID,
            $empData['emp_id']
        );

        // If task is from ticket and marked as completed, you might want to update ticket status
        if ($task->SOURCE_TYPE == self::SOURCE_TICKET && $newStatus == self::STATUS_COMPLETED) {
            // TODO: Integrate with your ticket system
            // $this->updateTicketStatus($task->SOURCE_ID, 'COMPLETED');
        }

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'task' => $this->formatTask($this->taskDB()->table('daily_tasks')->where('TASK_ID', $taskId)->first()),
        ]);
    }

    /**
     * Quick complete task (one-click completion)
     */
    public function quickComplete($taskId)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $task = $this->taskDB()->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->whereNull('DELETED_AT')
            ->first();

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        if ($task->STATUS == self::STATUS_COMPLETED) {
            return response()->json(['error' => 'Task already completed'], 400);
        }

        $oldStatus = $task->STATUS;

        $this->taskDB()->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->update([
                'STATUS' => self::STATUS_COMPLETED,
                'UPDATED_BY' => $empData['emp_id'],
                'UPDATED_AT' => now(),
            ]);

        $this->logAction(
            $taskId,
            'COMPLETED',
            'Task marked as completed',
            $oldStatus,
            self::STATUS_COMPLETED,
            $task->EMPLOYID,
            $empData['emp_id']
        );

        return response()->json([
            'success' => true,
            'message' => 'Task completed successfully',
        ]);
    }

    /**
     * Add task notes/comments
     */
    public function addNote(Request $request, $taskId)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $task = $this->taskDB()->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->whereNull('DELETED_AT')
            ->first();

        if (!$task) {
            return response()->json(['error' => 'Task not found'], 404);
        }

        // ✅ Update existing progress note instead of inserting a new record
        $this->taskDB()->table('daily_tasks')
            ->where('TASK_ID', $taskId)
            ->update([
                'PROGRESS_NOTES' => $validated['note'],
                'UPDATED_AT' => now(),
            ]);

        // ✅ Log the note action
        $this->logAction(
            $taskId,
            'NOTE_ADDED',
            $validated['note'],
            $task->STATUS,
            $task->STATUS,
            $task->EMPLOYID,
            $empData['emp_id']
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress note updated successfully',
        ]);
    }


    /**
     * Get task history/logs
     */
    public function getHistory($taskId)
    {
        $logs = $this->taskDB()->table('task_logs')
            ->where('TASK_ID', $taskId)
            ->orderByDesc('CREATED_AT')
            ->get();

        $statusMap = $this->getStatusMap();

        $formattedLogs = $logs->map(function ($log) use ($statusMap) {
            return [
                'action_type' => $log->ACTION_TYPE,
                'description' => $log->DESCRIPTION,
                'old_status' => $statusMap[$log->OLD_STATUS] ?? null,
                'new_status' => $statusMap[$log->NEW_STATUS] ?? null,
                'created_by' => $log->CREATED_BY,
                'created_at' => $log->CREATED_AT,
            ];
        });

        return response()->json([
            'logs' => $formattedLogs,
        ]);
    }

    /**
     * Format task for frontend
     */
    private function formatTask($task)
    {
        $statusMap = $this->getStatusMap();
        $sourceMap = $this->getSourceMap();

        return [
            'id' => $task->TASK_ID,
            'date' => $task->TASK_DATE,
            'title' => $task->TASK_TITLE,
            'description' => $task->TASK_DESCRIPTION,
            'status' => $task->STATUS,
            'status_label' => $statusMap[$task->STATUS] ?? 'Unknown',
            'source_type' => $task->SOURCE_TYPE,
            'source_label' => $sourceMap[$task->SOURCE_TYPE] ?? 'Unknown',
            'source_id' => $task->SOURCE_ID,
            'priority' => $task->PRIORITY ?? 3,
            'created_by' => $task->CREATED_BY,
            'created_at' => $task->CREATED_AT,
            'updated_at' => $task->UPDATED_AT,
            'employee_id' => $task->EMPLOYID,
        ];
    }

    /**
     * Get status map
     */
    private function getStatusMap()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get source map
     */
    private function getSourceMap()
    {
        return [
            self::SOURCE_TICKET => 'Ticket',
            self::SOURCE_PROJECT => 'Project',
            self::SOURCE_MANUAL => 'Manual',
        ];
    }

    /**
     * Get action type based on status
     */
    private function getActionType($status)
    {
        $map = [
            self::STATUS_PENDING => 'REVERTED',
            self::STATUS_IN_PROGRESS => 'STARTED',
            self::STATUS_COMPLETED => 'COMPLETED',
            self::STATUS_ON_HOLD => 'PAUSED',
            self::STATUS_CANCELLED => 'CANCELLED',
        ];

        return $map[$status] ?? 'UPDATED';
    }

    /**
     * Get default description for status change
     */
    private function getDefaultDescription($oldStatus, $newStatus)
    {
        $statusMap = $this->getStatusMap();
        return "Status changed from {$statusMap[$oldStatus]} to {$statusMap[$newStatus]}";
    }

    public function createFromTicket($ticketCode, $projId, $remarks, $assignedTo, $createdBy)
    {
        $taskCode = $this->generateTaskId();
        $this->taskDB()->table('daily_tasks')->insert([
            'TASK_ID' => $taskCode,
            'TASK_DATE' => now()->format('Y-m-d'),
            'EMPLOYID' => $assignedTo,
            'SOURCE_TYPE' => self::SOURCE_TICKET,
            'SOURCE_ID' => $ticketCode,
            'TASK_TITLE' => 'Ticket: ' . $ticketCode,
            'TASK_DESCRIPTION' => $remarks ?? 'Assigned for work',
            'PRIORITY' => 3,
            'STATUS' => self::STATUS_PENDING,
            'CREATED_BY' => $createdBy,
            'CREATED_AT' => now(),
            'UPDATED_AT' => now(),
        ]);

        $this->logAction(
            $taskCode,
            'CREATED',
            'Task created from ticket assignment',
            null,
            self::STATUS_PENDING,
            $assignedTo,
            $createdBy
        );

        return $taskCode;
    }


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

    private function generateTaskId()
    {
        $count = $this->taskDB()->table('daily_tasks')
            ->whereDate('CREATED_AT', now())->count() + 1;

        return 'TSK-' . sprintf('%03d', $count);
    }
}
