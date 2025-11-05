<?php

namespace App\Services;

use App\Repositories\TaskRepository;
use App\Constants\TaskConstants;
use App\Constants\ProjectConstants;
use Illuminate\Support\Facades\Log;
use App\Constants\TicketConstants;

class TaskService
{
    protected $taskRepository;
    protected $ticketService;
    protected $projectService;

    public function __construct(TaskRepository $taskRepository, TicketService $ticketService, ProjectService $projectService)
    {
        $this->taskRepository = $taskRepository;
        $this->ticketService = $ticketService;
        $this->projectService = $projectService;
    }
    public function createTask(array $data)
    {
        return $this->taskRepository->createTask($data);
    }
    public function createBulkTasks(array $data, $createdBy)
    {
        if (empty($data['TASKS']) || !is_array($data['TASKS'])) {
            throw new \InvalidArgumentException('Invalid task data: TASKS array is required.');
        }

        foreach ($data['TASKS'] as $task) {
            $taskId = $this->generateTaskId();

            $taskData = [
                'TASK_ID'          => $taskId,
                'TASK_DATE'        => now()->format('Y-m-d'),
                'EMPLOYID'         => $createdBy,
                'SOURCE_TYPE'      => strtoupper($data['SOURCE_TYPE']),
                'SOURCE_ID'        => $data['SOURCE_ID'] ?? null,
                'TASK_TITLE'       => ucfirst($task['TASK_TITLE']),
                'TASK_DESCRIPTION' => $task['TASK_DESCRIPTION'] ?? null,
                'PRIORITY'         => $data['PRIORITY'] ?? 3,
                'STATUS'           => $data['STATUS'] ?? TaskConstants::STATUS_PENDING,
                'CREATED_BY'       => $createdBy,
                'CREATED_AT'       => now(),
            ];

            // ✅ Create task
            $this->taskRepository->createTask($taskData);

            // ✅ Log task creation
            $this->taskRepository->logAction([
                'TASK_ID'     => $taskId,
                'ACTION_TYPE' => 'Created',
                'DESCRIPTION' => 'Task added via modal',
                'CREATED_BY'  => $createdBy,
                'CREATED_AT'  => now(),
            ]);

            // ✅ If linked to a project, update project status and log it
            if (strtolower($data['SOURCE_TYPE']) === 'project' && !empty($data['SOURCE_ID'])) {
                try {
                    // In TaskService
                    $this->projectService->updateProjectStatusById(
                        $data['SOURCE_ID'],
                        ProjectConstants::PROJ_STATUS_IN_PROGRESS,
                        $createdBy,
                        [],
                        NULL,
                        $taskId
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to update project status from task creation', [
                        'project_id' => $data['SOURCE_ID'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }


    /**
     * Get all tasks for user (from session)
     */
    public function getTasksForUser($empId)
    {
        return $this->taskRepository->getTasksForUser($empId);
    }

    /**
     * Get existing tasks for specific employee
     */
    public function getExistingTasks($empId)
    {
        return $this->taskRepository->getExistingTasks($empId);
    }

    /**
     * Get task history/logs
     */
    public function getTaskHistory($taskId)
    {
        $logs = $this->taskRepository->getTaskLogs($taskId);
        $statusMap = TaskConstants::getStatusMap();

        return $logs->map(function ($log) use ($statusMap) {
            return [
                'action_type' => $log->ACTION_TYPE,
                'description' => $log->DESCRIPTION,
                'old_status' => $statusMap[$log->OLD_STATUS] ?? null,
                'new_status' => $statusMap[$log->NEW_STATUS] ?? null,
                'created_by' => $log->CREATED_BY,
                'created_at' => $log->CREATED_AT,
            ];
        });
    }

    /**
     * Format task for frontend
     */

    public function formatTask($task)
    {
        $statusMap = TaskConstants::getStatusMap();
        $sourceMap = TaskConstants::getSourceMap();
        $sourceName = null;

        // ✅ If task is from a project, get the project name
        if (strtolower($task->SOURCE_TYPE) === 'project' && !empty($task->SOURCE_ID)) {
            $project = $this->projectService->getProjectById($task->SOURCE_ID);
            $sourceName = $project->PROJ_NAME ?? null;
        }

        // ✅ If task is from a ticket, get TYPE_OF_REQUEST (mapped using TicketConstants)
        if (strtolower($task->SOURCE_TYPE) === 'ticket' && !empty($task->SOURCE_ID)) {
            $ticket = $this->ticketService->getTicketByCode($task->SOURCE_ID);

            if ($ticket) {
                $typeMap = [
                    TicketConstants::REQUEST_NEW_SYSTEM     => 'New System',
                    TicketConstants::REQUEST_MODIFICATION   => 'Modification',
                    TicketConstants::REQUEST_ENHANCEMENT    => 'Enhancement',
                    TicketConstants::REQUEST_ADJUSTMENT     => 'Adjustment',
                    TicketConstants::REQUEST_TESTING        => 'Testing',
                    TicketConstants::REQUEST_PARALLEL_RUN   => 'Parallel Run',
                ];

                $sourceName = $typeMap[$ticket->TYPE_OF_REQUEST] ?? 'Unknown Request Type';
            }
        }

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
            'source_name' => $sourceName, // ✅ Now shows TYPE_OF_REQUEST (human-readable)
            'priority' => $task->PRIORITY ?? 3,
            'created_by' => $task->CREATED_BY,
            'created_at' => $task->CREATED_AT,
            'updated_at' => $task->UPDATED_AT,
            'employee_ids' => explode(',', $task->EMPLOYID),
            'progress_notes' => $task->PROGRESS_NOTES,
        ];
    }


    /**
     * Complete task with status update
     */
    public function completeTask($taskId, $newStatus, $remarks, $empData)
    {
        $task = $this->taskRepository->findTask($taskId);
        if (!$task) {
            throw new \Exception('Task not found');
        }

        if ($task->STATUS == $newStatus) {
            throw new \Exception('Task already has this status');
        }

        $oldStatus = $task->STATUS;

        // Update task status
        $this->taskRepository->updateTaskStatus($taskId, $newStatus, $empData['emp_id']);

        // Log action for all assigned employees
        $this->logTaskAction(
            $taskId,
            $oldStatus,
            $newStatus,
            $remarks ?? $this->getDefaultDescription($oldStatus, $newStatus),
            $task->EMPLOYID,
            $empData['emp_id']
        );

        // Auto-resolve ticket if applicable
        if ($task->SOURCE_TYPE === TaskConstants::SOURCE_TICKET) {
            $this->handleTicketAutoResolution($task->SOURCE_ID, $empData);
        }
        // ✅ If task is linked to a project, check if all tasks are completed
        if (strtolower($task->SOURCE_TYPE) === 'project' && !empty($task->SOURCE_ID)) {
            $remainingTasks = $this->taskRepository->countRemainingTasks(
                'project',
                $task->SOURCE_ID,
                [TaskConstants::STATUS_IN_PROGRESS, TaskConstants::STATUS_PENDING]
            );

            // If no more tasks are pending or in progress, mark project as completed
            if ($remainingTasks === 0) {
                try {
                    $this->projectService->updateProjectStatusById(
                        $task->SOURCE_ID,
                        ProjectConstants::PROJ_STATUS_DEPLOYED,
                        $empData['emp_id'],
                        [],
                        NULL,
                        $taskId
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to auto-complete project after final task completion', [
                        'project_id' => $task->SOURCE_ID,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $this->taskRepository->findTask($taskId);
    }

    /**
     * Add progress note to task
     */
    public function addProgressNote($taskId, $note, $empData)
    {
        $task = $this->taskRepository->findTask($taskId);
        if (!$task) {
            throw new \Exception('Task not found');
        }

        $this->taskRepository->updateProgressNotes($taskId, $note);

        // Log note for all employees
        $this->logTaskAction(
            $taskId,
            $task->STATUS,
            $task->STATUS,
            $note,
            $task->EMPLOYID,
            $empData['emp_id'],
            'NOTE_ADDED'
        );

        return true;
    }

    /**
     * Create task from ticket
     */
    public function createFromTicket($ticketCode, $projId, $remarks, $assignedToCsv, $createdBy)
    {
        try {
            $taskCode = $this->generateTaskId();

            $taskData = [
                'TASK_ID' => $taskCode,
                'TASK_DATE' => now()->format('Y-m-d'),
                'EMPLOYID' => $assignedToCsv,
                'SOURCE_TYPE' => TaskConstants::SOURCE_TICKET,
                'SOURCE_ID' => $ticketCode,
                'TASK_TITLE' => 'Ticket: ' . $ticketCode,
                'TASK_DESCRIPTION' => $remarks ?? 'Assigned for work',
                'PRIORITY' => 3,
                'STATUS' => TaskConstants::STATUS_PENDING,
                'CREATED_BY' => $createdBy,
                'CREATED_AT' => now(),
                'UPDATED_AT' => now(),
            ];

            $this->taskRepository->createTask($taskData);

            return $taskCode;
        } catch (\Exception $e) {
            Log::error('createFromTicket() failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Log task action for all assigned employees
     */
    private function logTaskAction($taskId, $oldStatus, $newStatus, $description, $assignedTo, $createdBy, $actionType = null)
    {
        if (!$actionType) {
            $actionType = TaskConstants::getActionType($newStatus);
        }

        $assignedEmployees = explode(',', $assignedTo);
        foreach ($assignedEmployees as $empId) {
            $this->taskRepository->logAction([
                'TASK_ID' => $taskId,
                'ACTION_TYPE' => $actionType,
                'DESCRIPTION' => $description,
                'OLD_STATUS' => $oldStatus,
                'NEW_STATUS' => $newStatus,
                'ASSIGNED_TO' => $empId,
                'CREATED_BY' => $createdBy,
                'CREATED_AT' => now(),
            ]);
        }
    }

    /**
     * Handle ticket auto-resolution when all tasks are completed
     */
    private function handleTicketAutoResolution($ticketId, $empData)
    {
        $remainingTasks = $this->taskRepository->countRemainingTasks(
            TaskConstants::SOURCE_TICKET,
            $ticketId
        );

        if ($remainingTasks === 0) {
            $this->ticketService->resolveTicket(
                $ticketId,
                $empData,
                'All related tasks completed'
            );
        }
    }

    /**
     * Generate unique task ID
     */
    private function generateTaskId()
    {
        $latest = $this->taskRepository->getLatestTaskId(); // e.g., "TSK-005"
        $lastNum = $latest ? intval(substr($latest, 4)) : 0;
        return 'TSK-' . sprintf('%03d', $lastNum + 1);
    }


    /**
     * Get default description for status change
     */
    private function getDefaultDescription($oldStatus, $newStatus)
    {
        $statusMap = TaskConstants::getStatusMap();
        return "Status changed from {$statusMap[$oldStatus]} to {$statusMap[$newStatus]}";
    }
}
