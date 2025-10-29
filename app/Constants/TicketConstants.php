<?php

namespace App\Constants;


class TicketConstants
{
    // Status Constants
    const STATUS_NEW = 1;
    const STATUS_TRIAGED = 2;
    const STATUS_APPROVED = 3;
    const STATUS_IN_PROGRESS = 4;
    const STATUS_RESOLVED = 5;
    const STATUS_CLOSED = 6;
    const STATUS_REJECTED = 7;
    const STATUS_ON_HOLD = 8;
    const STATUS_RETURNED = 9; // Returned to requestor for update

    // Request Type Constants
    const REQUEST_NEW_SYSTEM = 1;
    const REQUEST_MODIFICATION = 2;
    const REQUEST_ENHANCEMENT = 3;
    const REQUEST_ADJUSTMENT = 4;
    const REQUEST_TESTING = 5;
    const REQUEST_PARALLEL_RUN = 6;

    // Workflow Action Types
    const WORKFLOW_ASSESSED = 'ASSESS';
    const WORKFLOW_DH_APPROVED = 'DH_APPROVE';
    const WORKFLOW_DH_REJECTED = 'DH_REJECT';
    const WORKFLOW_OD_APPROVED = 'OD_APPROVE';
    const WORKFLOW_OD_REJECTED = 'OD_REJECT';
    const WORKFLOW_ASSIGNED = 'ASSIGN';
    const WORKFLOW_ACKNOWLEDGED = 'ACKNOWLEDGE';
    const WORKFLOW_RESOLVED = 'RESOLVE';
    const WORKFLOW_CLOSED = 'CLOSE';
    const WORKFLOW_RETURNED = 'RETURN';
    const WORKFLOW_PUT_ON_HOLD = 'PUT_ON_HOLD';
    const WORKFLOW_RESUMED = 'RESUME';
    const WORKFLOW_RESUBMITTED = 'RESUBMIT';
    const WORKFLOW_TEST = 'TEST';
}
