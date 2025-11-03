<?php

namespace App\Constants;

class ProjectConstants
{
    // Project status constants
    const PROJ_STATUS_PLANNING = 1;
    const PROJ_STATUS_TRIAGED = 2;
    const PROJ_STATUS_IN_PROGRESS = 3;
    const PROJ_STATUS_ON_HOLD = 4;
    const PROJ_STATUS_DEPLOYED = 5;
    const PROJ_STATUS_CANCELLED = 6;
    const PROJ_STATUS_INACTIVE = 7;

    // Ticket status constants
    const STATUS_NEW = 1;
    const STATUS_TRIAGED = 2;
    const STATUS_APPROVED = 3;
    const STATUS_IN_PROGRESS = 4;
    const STATUS_RESOLVED = 5;
    const STATUS_CLOSED = 6;
    const STATUS_REJECTED = 7;
    const STATUS_ON_HOLD = 8;
    const STATUS_RETURNED = 9;

    // Request Type Constants
    const REQUEST_NEW_SYSTEM = 1;
    const REQUEST_MODIFICATION = 2;
    const REQUEST_ENHANCEMENT = 3;
    const REQUEST_ADJUSTMENT = 4;
    const REQUEST_TESTING = 5;
    const REQUEST_PARALLEL_RUN = 6;

    public static function getProjectStatusMap()
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

    public static function getRequestTypeLabels()
    {
        return [
            self::REQUEST_NEW_SYSTEM => 'New System',
            self::REQUEST_MODIFICATION => 'Modification',
            self::REQUEST_ENHANCEMENT => 'Enhancement',
            self::REQUEST_ADJUSTMENT => 'Adjustment',
            self::REQUEST_TESTING => 'Testing',
            self::REQUEST_PARALLEL_RUN => 'Parallel Run',
        ];
    }

    public static function getStatusMapping()
    {
        return [
            'planning' => self::PROJ_STATUS_PLANNING,
            'ready' => self::PROJ_STATUS_TRIAGED,
            'triaged' => self::PROJ_STATUS_TRIAGED,
            'in progress' => self::PROJ_STATUS_IN_PROGRESS,
            'on hold' => self::PROJ_STATUS_ON_HOLD,
            'deployed' => self::PROJ_STATUS_DEPLOYED,
            'cancelled' => self::PROJ_STATUS_CANCELLED,
            'inactive' => self::PROJ_STATUS_INACTIVE,
        ];
    }
}
