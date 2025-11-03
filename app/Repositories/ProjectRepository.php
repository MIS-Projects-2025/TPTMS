<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use App\Constants\ProjectConstants;

class ProjectRepository
{
    protected $connection;

    public function __construct()
    {
        $this->connection = DB::connection('projects');
    }

    public function getBaseQuery()
    {
        return $this->connection->table('project_list')->whereNull('DELETED_AT');
    }

    public function getProjects($filters = [], $pagination = [])
    {
        $query = $this->getBaseQuery();

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('PROJ_NAME', 'like', "%{$filters['search']}%")
                    ->orWhere('PROJ_DESC', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['department'])) {
            $query->where('PROJ_DEPT', $filters['department']);
        }

        if (!empty($filters['status_ids'])) {
            $query->whereIn('PROJ_STATUS', $filters['status_ids']);
        }

        // Apply sorting
        $columnMap = [
            'name' => 'PROJ_NAME',
            'department' => 'PROJ_DEPT',
            'assigned' => 'ASSIGNED_PROGS',
            'status' => 'PROJ_STATUS',
            'created_at' => 'CREATED_AT',
        ];

        $sortField = $columnMap[$filters['sortField'] ?? ''] ?? 'CREATED_AT';
        $sortOrder = $filters['sortOrder'] ?? 'desc';

        $query->orderBy($sortField, $sortOrder);

        // Get total count
        $total = $query->count();

        // Apply pagination
        $page = $pagination['page'] ?? 1;
        $pageSize = $pagination['pageSize'] ?? 10;

        $projects = $query->forPage($page, $pageSize)->get();

        return [
            'data' => $projects,
            'total' => $total,
            'last_page' => ceil($total / $pageSize)
        ];
    }

    public function getProjectByName($projectName)
    {
        return $this->getBaseQuery()->where('PROJ_NAME', $projectName)->first();
    }

    public function getProjectById($projectId)
    {
        return $this->getBaseQuery()->where('PROJ_ID', $projectId)->first();
    }

    public function createProject(array $projectData)
    {
        return $this->connection->table('project_list')->insertGetId($projectData);
    }

    public function updateProject($projectId, array $updateData)
    {
        return $this->connection->table('project_list')
            ->where('PROJ_ID', $projectId)
            ->update($updateData);
    }

    public function getProjectLogs($projectId, $perPage = 10)
    {
        return $this->connection->table('project_logs')
            ->where('PROJ_ID', $projectId)
            ->orderBy('UPDATE_AT', 'desc')
            ->paginate($perPage);
    }

    public function logAction(array $logData)
    {
        return $this->connection->table('project_logs')->insert($logData);
    }

    public function getDepartments()
    {
        return $this->getBaseQuery()
            ->distinct()
            ->pluck('PROJ_DEPT')
            ->toArray();
    }

    public function getStatusCounts()
    {
        return [
            'all' => $this->getBaseQuery()->count(),
            'active' => $this->getBaseQuery()
                ->whereIn('PROJ_STATUS', [
                    ProjectConstants::PROJ_STATUS_PLANNING,
                    ProjectConstants::PROJ_STATUS_TRIAGED,
                    ProjectConstants::PROJ_STATUS_IN_PROGRESS
                ])->count(),
            'completed' => $this->getBaseQuery()
                ->where('PROJ_STATUS', ProjectConstants::PROJ_STATUS_DEPLOYED)
                ->count(),
            'on_hold' => $this->getBaseQuery()
                ->where('PROJ_STATUS', ProjectConstants::PROJ_STATUS_ON_HOLD)
                ->count(),
        ];
    }

    public function getAssignedProjects($empId)
    {
        return $this->connection->select('
            SELECT 
                PROJ_ID as value,
                CONCAT(PROJ_NAME, " (", PROJ_DEPT, ")") as label,
                PROJ_NAME,
                TARGET_DEADLINE
            FROM project_list 
            WHERE FIND_IN_SET(?, ASSIGNED_PROGS) > 0
            ORDER BY PROJ_NAME ASC
        ', [$empId]);
    }

    public function findProjectForImport($projId = null, $projName = null)
    {
        $query = $this->getBaseQuery();

        if (!empty($projId)) {
            return $query->where('PROJ_ID', $projId)->first();
        }

        if (!empty($projName)) {
            return $query->where('PROJ_NAME', $projName)->first();
        }

        return null;
    }

    /**
     * Bulk insert projects
     */
    public function bulkInsertProjects(array $projects)
    {
        return $this->connection->table('project_list')->insert($projects);
    }

    /**
     * Bulk update projects
     */
    public function bulkUpdateProjects(array $updates)
    {
        $results = [];
        foreach ($updates as $update) {
            $results[] = $this->connection->table('project_list')
                ->where('PROJ_ID', $update['PROJ_ID'])
                ->update($update['data']);
        }
        return $results;
    }

    // NEW METHODS ADDED FOR TICKET OPERATIONS

    /**
     * Get project tickets based on status
     */
    public function getProjectTickets($projectName, $isDeployed = false)
    {
        $query = DB::table('tickets')
            ->where('PROJECT_NAME', $projectName)
            ->whereNull('DELETED_AT');

        if ($isDeployed) {
            // For deployed projects, get only the latest ticket
            $query->orderBy('CREATED_AT', 'desc')->limit(1);
        } else {
            // For non-deployed projects, get active tickets
            $query->whereNull('CLOSED_AT')
                ->orderBy('CREATED_AT', 'desc');
        }

        return $query->get();
    }

    /**
     * Get ticket assignment log
     */
    public function getTicketAssignmentLog($ticketId)
    {
        return DB::table('ticket_workflow')
            ->where('TICKET_ID', $ticketId)
            ->where('ACTION_TYPE', 'ASSIGNED')
            ->orderBy('ACTION_AT', 'asc')
            ->first();
    }

    /**
     * Count open tickets for a project
     */
    public function countOpenTickets($projectName)
    {
        return DB::table('tickets')
            ->where('PROJECT_NAME', $projectName)
            ->whereNull('DELETED_AT')
            ->whereNull('CLOSED_AT')
            ->count();
    }

    /**
     * Count closed tickets for a project
     */
    public function countClosedTickets($projectName)
    {
        return DB::table('tickets')
            ->where('PROJECT_NAME', $projectName)
            ->where('STATUS', ProjectConstants::STATUS_CLOSED)
            ->whereNull('DELETED_AT')
            ->count();
    }

    /**
     * Get the latest closed ticket for a project
     */
    public function getLatestClosedTicket($projectName)
    {
        return DB::table('tickets')
            ->where('PROJECT_NAME', $projectName)
            ->where('STATUS', ProjectConstants::STATUS_CLOSED)
            ->whereNull('DELETED_AT')
            ->orderBy('CLOSED_AT', 'desc')
            ->first();
    }

    /**
     * Get employee details from masterlist
     */
    public function getEmployeesByIds($employeeIds)
    {
        return DB::connection('masterlist')
            ->table('employee_masterlist')
            ->whereIn('EMPLOYID', $employeeIds)
            ->select('EMPLOYID', 'FIRSTNAME', 'MIDDLE_INITIAL', 'LASTNAME')
            ->get();
    }
}
