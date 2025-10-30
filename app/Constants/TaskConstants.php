<?php

namespace App\Constants;

class TaskConstants
{
    // ✅ Numeric status constants
    const STATUS_PENDING = 1;
    const STATUS_IN_PROGRESS = 2;
    const STATUS_COMPLETED = 3;
    const STATUS_ON_HOLD = 4;
    const STATUS_CANCELLED = 5;

    // ✅ Source type strings
    const SOURCE_TICKET = 'TICKET';
    const SOURCE_PROJECT = 'PROJECT';
    const SOURCE_MANUAL = 'MANUAL';

    public static function getStatusMap()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_ON_HOLD => 'On Hold',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public static function getSourceMap()
    {
        return [
            self::SOURCE_TICKET => 'Ticket',
            self::SOURCE_PROJECT => 'Project',
            self::SOURCE_MANUAL => 'Manual',
        ];
    }

    public static function getActionType($status)
    {
        return [
            self::STATUS_PENDING => 'REVERTED',
            self::STATUS_IN_PROGRESS => 'STARTED',
            self::STATUS_COMPLETED => 'COMPLETED',
            self::STATUS_ON_HOLD => 'PAUSED',
            self::STATUS_CANCELLED => 'CANCELLED',
        ][$status] ?? 'UPDATED';
    }
}
