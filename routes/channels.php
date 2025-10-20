<?php

use Illuminate\Support\Facades\Broadcast;

// For individual user notifications
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->emp_id === (int) $id;
});

// For employee-specific notifications
// Broadcast::channel('employee.{id}', function ($user, $id) {
//     return (int) $user->emp_id === (int) $id;
// });

// For programmer notifications
Broadcast::channel('programmers', function ($user) {
    return strtoupper($user->emp_dept) === 'MIS';
});

// For department head notifications
Broadcast::channel('department-heads', function ($user) {
    $isHead = \Illuminate\Support\Facades\DB::connection('masterlist')
        ->select("
            SELECT COUNT(*) as count FROM employee_masterlist 
            WHERE EMPLOYID = ? 
            AND (APPROVER2 IS NOT NULL OR APPROVER3 IS NOT NULL)
        ", [$user->emp_id]);

    return $isHead[0]->count > 0;
});

// For OD notifications
Broadcast::channel('operations-director', function ($user) {
    return strtoupper($user->emp_dept) === 'OPERATIONS'
        || strtoupper($user->emp_jobtitle) === 'OPERATIONS DIRECTOR';
});

// For supervisor notifications
Broadcast::channel('supervisors', function ($user) {
    return strtoupper($user->emp_dept) === 'MIS';
});
