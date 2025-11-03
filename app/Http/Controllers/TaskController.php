<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Services\TaskService;
use App\Constants\TaskConstants;

class TaskController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }
    public function store(Request $request)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'SOURCE_TYPE' => 'required|string',
            'SOURCE_ID' => 'max:50',
            'STATUS' => 'required|integer',
            'PRIORITY' => 'required|integer',
            'TASKS' => 'required|array|min:1',
            'TASKS.*.TASK_TITLE' => 'required|string|max:255',
            'TASKS.*.TASK_DESCRIPTION' => 'nullable|string|max:1000',
        ]);

        try {
            $this->taskService->createBulkTasks($validated, $empData['emp_id']);

            return response()->json(['success' => true, 'message' => 'Tasks created successfully']);
        } catch (\Exception $e) {
            Log::error('Task creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create tasks'], 500);
        }
    }


    /**
     * Get tasks for current user
     */
    public function getTask()
    {
        $empData = session('emp_data');
        if (!$empData) return redirect()->route('login');

        try {
            $tasks = $this->taskService->getTasksForUser($empData['emp_id'])
                ->map(fn($task) => $this->taskService->formatTask($task));

            Log::info('Tasks loaded', ['count' => $tasks->count()]);

            return Inertia::render('Tasks/Index', [
                'tasks' => $tasks->toArray(),
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

    public function getExistingTasks($empId)
    {
        $tasks = $this->taskService->getExistingTasks($empId);
        return response()->json($tasks);
    }

    /**
     * Update task status
     */
    public function updateStatus(Request $request, $taskId)
    {
        $validated = $request->validate([
            'status' => 'required|integer|min:1|max:5',
            'remarks' => 'nullable|string|max:500',
        ]);

        $empData = session('emp_data');
        if (!$empData) return response()->json(['error' => 'Unauthorized'], 401);

        try {
            $task = $this->taskService->completeTask(
                $taskId,
                $validated['status'],
                $validated['remarks'] ?? null,
                $empData
            );

            return response()->json([
                'success' => true,
                'message' => 'Task status updated successfully',
                'task' => $this->taskService->formatTask($task),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Quick complete task
     */
    public function quickComplete($taskId)
    {
        $empData = session('emp_data');
        if (!$empData) return response()->json(['error' => 'Unauthorized'], 401);

        try {
            $task = $this->taskService->completeTask(
                $taskId,
                TaskConstants::STATUS_COMPLETED,
                'Task marked as completed',
                $empData
            );

            return response()->json([
                'success' => true,
                'message' => 'Task completed successfully',
                'task' => $this->taskService->formatTask($task),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Add or update task note
     */
    public function addNote(Request $request, $taskId)
    {
        $empData = session('emp_data');
        if (!$empData) return response()->json(['error' => 'Unauthorized'], 401);

        $validated = $request->validate(['note' => 'required|string|max:1000']);

        try {
            $this->taskService->addProgressNote($taskId, $validated['note'], $empData);

            return response()->json([
                'success' => true,
                'message' => 'Progress note updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get task history/logs
     */
    public function getHistory($taskId)
    {
        $logs = $this->taskService->getTaskHistory($taskId);
        return response()->json(['logs' => $logs]);
    }

    /**
     * Create task from ticket
     */
    public function createFromTicket($ticketCode, $projId, $remarks, $assignedToCsv, $createdBy)
    {
        $taskCode = $this->taskService->createFromTicket(
            $ticketCode,
            $projId,
            $remarks,
            $assignedToCsv,
            $createdBy
        );

        return $taskCode ? response()->json(['task_id' => $taskCode])
            : response()->json(['error' => 'Failed to create task'], 500);
    }
}
