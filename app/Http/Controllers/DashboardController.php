<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $empData = session('emp_data');
        if (!$empData) {
            return redirect()->route('login');
        }

        $userRoles = $this->getUserRoles($empData);

        $dashboardData = [
            'quickStats' => $this->getQuickStats($empData, $userRoles),
            'recentActivity' => $this->getRecentActivity($empData, $userRoles),
            'notifications' => $this->getNotifications($empData),
            'userRoles' => $userRoles,
            'chartData' => $this->getChartData($empData, $userRoles),
        ];

        // Add role-specific data
        if (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles) || in_array('MIS_MANAGER', $userRoles)) {
            $dashboardData['programmerData'] = $this->getProgrammerData($empData);
        }

        if (in_array('MIS_SUPERVISOR', $userRoles) || in_array('MIS_MANAGER', $userRoles)) {
            $dashboardData['supervisorData'] = $this->getSupervisorData($empData);
        }

        if (in_array('DEPARTMENT_HEAD', $userRoles)) {
            $dashboardData['dhData'] = $this->getDHData($empData);
        }

        if (in_array('OD', $userRoles)) {
            $dashboardData['odData'] = $this->getODData($empData);
        }

        if (in_array('DIRECTOR', $userRoles) || in_array('PRESIDENT', $userRoles)) {
            $dashboardData['executiveData'] = $this->getExecutiveData($empData);
        }

        return Inertia::render('Dashboard', $dashboardData);
    }

    private function getUserRoles($empData)
    {
        $roles = [];

        if ($this->isPresident($empData)) {
            $roles[] = 'PRESIDENT';
        }

        if ($this->isDirector($empData)) {
            $roles[] = 'DIRECTOR';
        }

        if ($this->isMISManager($empData)) {
            $roles[] = 'MIS_MANAGER';
            $roles[] = 'MIS_SUPERVISOR';
            $roles[] = 'PROGRAMMER';
        } elseif ($this->isMISSupervisor($empData)) {
            $roles[] = 'MIS_SUPERVISOR';
            $roles[] = 'PROGRAMMER';
        } elseif ($this->isAssessedByProgrammer($empData)) {
            $roles[] = 'PROGRAMMER';
        }

        if ($this->isODAccount($empData)) {
            $roles[] = 'OD';
        }

        if ($this->isDepartmentHead($empData)) {
            $roles[] = 'DEPARTMENT_HEAD';
        }

        if ($this->isRequestorAccount($empData)) {
            $roles[] = 'REQUESTOR';
        }

        return $roles ?: ['UNKNOWN'];
    }

    private function getQuickStats($empData, $userRoles)
    {
        $empId = $empData['emp_id'];
        $stats = [];

        // Tickets assigned to user (for programmers, supervisors, managers)
        if (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles) || in_array('MIS_MANAGER', $userRoles)) {
            $assignedTickets = DB::table('tickets')
                ->where('ASSIGNED_TO', 'LIKE', "%{$empId}%")
                ->whereIn('STATUS', [2, 3, 4]) // TRIAGED, APPROVED, IN_PROGRESS
                ->count();
            $stats['assigned_tickets'] = $assignedTickets;
        }

        // User's pending tickets (for requestors) - default connection
        if (in_array('REQUESTOR', $userRoles)) {
            $myTickets = DB::table('tickets')
                ->where('EMPLOYID', $empId)
                ->whereIn('STATUS', [1, 2, 3, 4, 8]) // NEW, TRIAGED, APPROVED, IN_PROGRESS, ON_HOLD
                ->count();
            $stats['my_pending_tickets'] = $myTickets;
        }

        // Pending approvals (for DH and OD) - default connection
        if (in_array('DEPARTMENT_HEAD', $userRoles)) {
            $pendingDHApprovals = DB::table('tickets')
                ->where('STATUS', 2) // TRIAGED
                ->count();
            $stats['pending_dh_approvals'] = $pendingDHApprovals;
        }

        if (in_array('OD', $userRoles)) {
            $pendingODApprovals = DB::table('tickets')
                ->where('STATUS', 3) // APPROVED (by DH)
                ->count();
            $stats['pending_od_approvals'] = $pendingODApprovals;
        }

        // Tasks assigned to user - using task connection (only for technical roles)
        if (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles) || in_array('MIS_MANAGER', $userRoles)) {
            $pendingTasks = DB::connection('task')->table('daily_tasks')
                ->where('EMPLOYID', 'LIKE', "%{$empId}%")
                ->whereIn('STATUS', [1, 2, 3, 4]) // PENDING, IN_PROGRESS, etc.
                ->count();
            $stats['pending_tasks'] = $pendingTasks;
        }

        // Executive stats for Director and President
        if (in_array('DIRECTOR', $userRoles) || in_array('PRESIDENT', $userRoles)) {
            $stats['total_tickets'] = DB::table('tickets')->count();
            $stats['total_projects'] = DB::connection('projects')->table('project_list')->count();
            $stats['open_tickets'] = DB::table('tickets')->whereIn('STATUS', [1, 2, 3, 4])->count();
            $stats['completed_tickets'] = DB::table('tickets')->where('STATUS', 5)->count();
        }

        return $stats;
    }

    private function getRecentActivity($empData, $userRoles)
    {
        $empId = $empData['emp_id'];

        // Recent tickets - different queries based on role
        if (in_array('DIRECTOR', $userRoles) || in_array('PRESIDENT', $userRoles)) {
            // Directors and Presidents see all tickets
            $recentTickets = DB::table('tickets')
                ->orderBy('CREATED_AT', 'desc')
                ->limit(10)
                ->get(['TICKET_ID', 'TYPE_OF_REQUEST', 'STATUS', 'CREATED_AT', 'PROJECT_NAME', 'EMPLOYID']);
        } else {
            $recentTickets = DB::table('tickets')
                ->where(function ($query) use ($empId, $userRoles) {
                    $query->where('EMPLOYID', $empId);

                    if (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles) || in_array('MIS_MANAGER', $userRoles)) {
                        $query->orWhere('ASSIGNED_TO', 'LIKE', "%{$empId}%");
                    }
                })
                ->orderBy('CREATED_AT', 'desc')
                ->limit(10)
                ->get(['TICKET_ID', 'TYPE_OF_REQUEST', 'STATUS', 'CREATED_AT', 'PROJECT_NAME']);
        }

        // Recent tasks - only for technical roles
        $recentTasks = collect([]);
        if (in_array('PROGRAMMER', $userRoles) || in_array('MIS_SUPERVISOR', $userRoles) || in_array('MIS_MANAGER', $userRoles)) {
            $recentTasks = DB::connection('task')->table('daily_tasks')
                ->where('EMPLOYID', 'LIKE', "%{$empId}%")
                ->orderBy('CREATED_AT', 'desc')
                ->limit(10)
                ->get(['TASK_ID', 'TASK_TITLE', 'STATUS', 'CREATED_AT', 'SOURCE_TYPE']);
        }

        // Recent project updates
        if (in_array('DIRECTOR', $userRoles) || in_array('PRESIDENT', $userRoles)) {
            // Directors and Presidents see all projects
            $recentProjects = DB::connection('projects')->table('project_list')
                ->orderBy('UPDATED_AT', 'desc')
                ->limit(5)
                ->get(['PROJ_ID', 'PROJ_NAME', 'PROJ_STATUS', 'UPDATED_AT']);
        } else {
            $recentProjects = DB::connection('projects')->table('project_list')
                ->where('ASSIGNED_PROGS', $empId)
                ->orWhere('CREATED_BY', $empId)
                ->orderBy('UPDATED_AT', 'desc')
                ->limit(5)
                ->get(['PROJ_ID', 'PROJ_NAME', 'PROJ_STATUS', 'UPDATED_AT']);
        }

        return [
            'tickets' => $recentTickets,
            'tasks' => $recentTasks,
            'projects' => $recentProjects,
        ];
    }

    private function getNotifications($empData)
    {
        $empId = $empData['emp_id'];

        $notifications = DB::table('notifications')
            ->where('notifiable_id', $empId)
            ->where('read_at', false)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return $notifications;
    }

    private function getChartData($empData, $userRoles)
    {
        $charts = [];

        // Ticket status distribution
        $ticketStatuses = DB::table('tickets')
            ->select('STATUS', DB::raw('COUNT(*) as count'))
            ->groupBy('STATUS')
            ->get();

        $charts['ticketStatus'] = [
            'labels' => $ticketStatuses->map(fn($item) => $this->getStatusLabel($item->STATUS)),
            'data' => $ticketStatuses->pluck('count'),
            'colors' => $ticketStatuses->map(fn($item) => $this->getStatusColor($item->STATUS)),
        ];

        // Project status distribution (for executives)
        if (in_array('DIRECTOR', $userRoles) || in_array('PRESIDENT', $userRoles)) {
            $projectStatuses = DB::connection('projects')->table('project_list')
                ->select('PROJ_STATUS', DB::raw('COUNT(*) as count'))
                ->groupBy('PROJ_STATUS')
                ->get();

            $charts['projectStatus'] = [
                'labels' => $projectStatuses->pluck('PROJ_STATUS'),
                'data' => $projectStatuses->pluck('count'),
            ];
        }

        // Monthly ticket trend
        $monthlyTickets = DB::table('tickets')
            ->select(DB::raw('MONTH(CREATED_AT) as month'), DB::raw('COUNT(*) as count'))
            ->where('CREATED_AT', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $charts['monthlyTrend'] = [
            'labels' => $monthlyTickets->map(fn($item) => date('F', mktime(0, 0, 0, $item->month, 1))),
            'data' => $monthlyTickets->pluck('count'),
        ];

        return $charts;
    }

    private function getProgrammerData($empData)
    {
        $empId = $empData['emp_id'];

        return [
            'assigned_tickets' => DB::table('tickets')
                ->where('ASSIGNED_TO', 'LIKE', "%{$empId}%")
                ->whereIn('STATUS', [2, 3, 4])
                ->orderBy('CREATED_AT', 'desc')
                ->limit(10)
                ->get(),
            'pending_tasks' => DB::connection('task')->table('daily_tasks')
                ->where('EMPLOYID', 'LIKE', "%{$empId}%")
                ->whereIn('STATUS', [1, 2, 3, 4])
                ->orderBy('CREATED_AT', 'desc')
                ->limit(10)
                ->get(),
        ];
    }

    private function getSupervisorData($empData)
    {
        return [
            'team_tickets' => DB::table('tickets')
                ->whereIn('STATUS', [1, 2, 3, 4])
                ->orderBy('CREATED_AT', 'desc')
                ->limit(15)
                ->get(),
            'team_performance' => [
                'resolved_this_week' => DB::table('tickets')
                    ->where('STATUS', 5)
                    ->where('UPDATED_AT', '>=', now()->subWeek())
                    ->count(),
                'pending_assignment' => DB::table('tickets')
                    ->where('STATUS', 2)
                    ->whereNull('ASSIGNED_TO')
                    ->count(),
            ],
        ];
    }

    private function getDHData($empData)
    {
        return [
            'pending_approvals' => DB::table('tickets')
                ->where('STATUS', 2)
                ->orderBy('CREATED_AT', 'desc')
                ->limit(10)
                ->get(),
            'department_stats' => [
                'total_pending' => DB::table('tickets')
                    ->where('STATUS', 2)
                    ->count(),
                'approved_this_month' => DB::table('tickets')
                    ->where('STATUS', 3)
                    ->where('UPDATED_AT', '>=', now()->startOfMonth())
                    ->count(),
            ],
        ];
    }

    private function getODData($empData)
    {
        return [
            'pending_approvals' => DB::table('tickets')
                ->where('STATUS', 3)
                ->orderBy('CREATED_AT', 'desc')
                ->limit(10)
                ->get(),
            'operations_stats' => [
                'total_pending' => DB::table('tickets')
                    ->where('STATUS', 3)
                    ->count(),
                'deployed_this_month' => DB::connection('projects')->table('project_list')
                    ->where('PROJ_STATUS', 'DEPLOYED')
                    ->where('UPDATED_AT', '>=', now()->startOfMonth())
                    ->count(),
            ],
        ];
    }

    private function getExecutiveData($empData)
    {
        $tickets = DB::table('tickets')
            ->select('EMPLOYID')
            ->get();

        $employees = DB::connection('masterlist')
            ->table('employee_masterlist')
            ->select('EMPLOYID', 'DEPARTMENT')
            ->get();
        // Convert tickets to a simple array of EMPLOYIDs
        $ticketEmpIds = $tickets->pluck('EMPLOYID')->toArray();

        // Filter employees who have tickets
        $employeesWithTickets = $employees->filter(function ($emp) use ($ticketEmpIds) {
            return in_array($emp->EMPLOYID, $ticketEmpIds);
        });

        // Group by department
        $departmentBreakdown = $employeesWithTickets
            ->groupBy('DEPARTMENT')
            ->map(fn($group) => count($group))
            ->toArray();

        return [
            'all_tickets' => DB::table('tickets')
                ->orderBy('CREATED_AT', 'desc')
                ->limit(20)
                ->get(),
            'all_projects' => DB::connection('projects')->table('project_list')
                ->orderBy('UPDATED_AT', 'desc')
                ->limit(15)
                ->get(),
            'performance_metrics' => [
                'avg_resolution_time' => 'Calculated metric',
                'sla_compliance' => 'Calculated metric',
                'department_breakdown' => $departmentBreakdown,
            ],


        ];
    }

    // Role detection methods
    private function isPresident($empData)
    {
        $jobTitle = strtoupper($empData['emp_jobtitle']);
        return strpos($jobTitle, 'PRESIDENT') !== false;
    }

    private function isDirector($empData)
    {
        $jobTitle = strtoupper($empData['emp_jobtitle']);
        return strpos($jobTitle, 'DIRECTOR') !== false && !$this->isPresident($empData);
    }

    private function isMISManager($empData)
    {
        $dept = strtoupper($empData['emp_dept']);
        $jobTitle = strtoupper($empData['emp_jobtitle']);
        return $dept === 'MIS' && strpos($jobTitle, 'MANAGER') !== false;
    }

    private function isRequestorAccount($empData)
    {
        return !$this->isAssessedByProgrammer($empData) &&
            !$this->isDepartmentHead($empData) &&
            !$this->isODAccount($empData) &&
            !$this->isMISSupervisor($empData) &&
            !$this->isMISManager($empData) &&
            !$this->isDirector($empData) &&
            !$this->isPresident($empData);
    }

    private function isAssessedByProgrammer($empData)
    {
        $dept = strtoupper($empData['emp_dept']);
        $jobTitle = strtolower($empData['emp_jobtitle']);

        return $dept === 'MIS' && strpos($jobTitle, 'programmer') !== false;
    }

    private function isMISSupervisor($empData)
    {
        $dept = strtoupper($empData['emp_dept']);
        $jobTitle = strtolower($empData['emp_jobtitle']);
        return $dept === 'MIS' && strpos($jobTitle, 'supervisor') !== false && !$this->isMISManager($empData);
    }

    private function isDepartmentHead($empData)
    {
        $userId = $empData['emp_id'];
        $hasApprovalRights = DB::connection('masterlist')->select("
            SELECT COUNT(*) as count FROM employee_masterlist 
            WHERE (APPROVER2 = '{$userId}' OR APPROVER3 = '{$userId}')
        ");

        return $hasApprovalRights[0]->count > 0;
    }

    private function isODAccount($empData)
    {
        return strtoupper($empData['emp_dept']) === 'OPERATIONS' ||
            strtoupper($empData['emp_jobtitle']) === 'OPERATIONS DIRECTOR';
    }

    // Helper methods for chart data
    private function getStatusLabel($status)
    {
        $labels = [
            1 => 'New',
            2 => 'Triaged',
            3 => 'Approved',
            4 => 'In Progress',
            5 => 'Resolved',
            6 => 'Closed',
            7 => 'Rejected',
            8 => 'On Hold',
            9 => 'Returned',
        ];

        return $labels[$status] ?? 'Unknown';
    }

    private function getStatusColor($status)
    {
        $colors = [
            1 => '#3B82F6', // blue
            2 => '#8B5CF6', // purple
            3 => '#10B981', // green
            4 => '#F59E0B', // yellow
            5 => '#EF4444', // red
            6 => '#6B7280', // gray
            7 => '#DC2626', // red
            8 => '#F97316', // orange
            9 => '#6366F1', // indigo
        ];

        return $colors[$status] ?? '#6B7280';
    }
}
