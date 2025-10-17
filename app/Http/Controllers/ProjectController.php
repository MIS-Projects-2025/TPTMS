<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    // Project Status Constants
    const PROJ_STATUS_PLANNING = 1;
    const PROJ_STATUS_TRIAGED = 2; // clearer label for triaged
    const PROJ_STATUS_IN_PROGRESS = 3;
    const PROJ_STATUS_ON_HOLD = 4;
    const PROJ_STATUS_DEPLOYED = 5;
    const PROJ_STATUS_CANCELLED = 6;
    const PROJ_STATUS_INACTIVE = 7;

    /**
     * Helper function to get project DB connection
     */
    protected function projectDB()
    {
        return DB::connection('projects');
    }

    /**
     * Helper to get project record by name
     */
    protected function getProjectByName($projectName)
    {
        return $this->projectDB()->table('project_list')
            ->where('PROJ_NAME', $projectName)
            ->first();
    }

    /**
     * Create project from ticket
     * Status: PLANNING (1)
     */
    public function createFromTicket($projectName, $description, $department, $requestorId, $createdBy)
    {
        $projId = $this->projectDB()->table('project_list')->insertGetId([
            'PROJ_NAME' => $projectName ?? 'New Project',
            'PROJ_DESC' => $description,
            'PROJ_DEPT' => $department,
            'PROJ_STATUS' => self::PROJ_STATUS_PLANNING,
            'PROJ_REQUESTOR' => $requestorId,
            'PROJECT_VERSION' => '1.0',
            'DATE_START' => null,
            'ASSIGNED_PROGS' => null,
            'CREATED_BY' => $createdBy,
            'CREATED_AT' => now(),
            'UPDATED_BY' => $createdBy,
            'UPDATED_AT' => now(),
        ]);

        $this->logAction($projId, 'CREATED', 'Project created from ticket', null, $createdBy);

        return $projId;
    }

    /**
     * Update project status to READY (2)
     */
    public function updateToReady($projectName, $approvalType, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_TRIAGED,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, $approvalType, 'Project status updated to Ready', null, $updatedBy);
    }

    /**
     * Update project status to IN_PROGRESS (3)
     */
    public function updateToInProgress($projectName, $assignedPrograms, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_IN_PROGRESS,
                'ASSIGNED_PROGS' => $assignedPrograms,
                'DATE_START' => now(),
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, 'ASSIGNED', 'Project status updated to In Progress', $assignedPrograms, $updatedBy);
    }

    /**
     * Update project status to ON_HOLD (4)
     */
    public function updateToOnHold($projectName, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_ON_HOLD,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, 'ON_HOLD', 'Project status updated to On Hold', null, $updatedBy);
    }

    /**
     * Update project status to DEPLOYED (5)
     */
    public function updateToDeployed($projectName, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_DEPLOYED,
                'DATE_END' => now(),
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, 'DEPLOYED', 'Project status updated to Deployed', null, $updatedBy);
    }

    /**
     * Update project status to CANCELLED (6)
     */
    public function updateToCancelled($projectName, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_CANCELLED,
                'DATE_END' => now(),
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, 'CANCELLED', 'Project status updated to Cancelled', null, $updatedBy);
    }

    /**
     * Update project status to INACTIVE (7)
     */
    public function updateToInactive($projectName, $updatedBy)
    {
        $project = $this->getProjectByName($projectName);
        if (!$project) {
            throw new \Exception("Project not found: $projectName");
        }

        $this->projectDB()->table('project_list')
            ->where('PROJ_ID', $project->PROJ_ID)
            ->update([
                'PROJ_STATUS' => self::PROJ_STATUS_INACTIVE,
                'UPDATED_AT' => now(),
                'UPDATED_BY' => $updatedBy,
            ]);

        $this->logAction($project->PROJ_ID, 'INACTIVE', 'Project status updated to Inactive', null, $updatedBy);
    }

    /**
     * Log project actions in project_logs
     */
    public function logAction($projId, $actionType, $description, $assignedProgs, $actionBy)
    {
        $project = $this->projectDB()->table('project_list')
            ->select('PROJECT_VERSION', 'PROJ_STATUS')
            ->where('PROJ_ID', $projId)
            ->first();

        $this->projectDB()->table('project_logs')->insert([
            'PROJ_ID' => $projId,
            'ACTION_TYPE' => $actionType,
            'DESCRIPTION' => $description,
            'PROJECT_VERSION' => $project->PROJECT_VERSION ?? '1.0',
            'PROJ_STATUS' => $project->PROJ_STATUS ?? null,
            'ASSIGNED_PROGS' => $assignedProgs,
            'ACTION_BY' => $actionBy,
            'UPDATE_AT' => now(),
        ]);
    }

    /**
     * Project status label map
     */
    public function getProjectStatusOptions()
    {
        return [
            self::PROJ_STATUS_PLANNING => 'Planning',
            self::PROJ_STATUS_TRIAGED => 'Ready',
            self::PROJ_STATUS_IN_PROGRESS => 'In Progress',
            self::PROJ_STATUS_ON_HOLD => 'On Hold',
            self::PROJ_STATUS_DEPLOYED => 'Deployed',
            self::PROJ_STATUS_CANCELLED => 'Cancelled',
            self::PROJ_STATUS_INACTIVE => 'Inactive',
        ];
    }
}
