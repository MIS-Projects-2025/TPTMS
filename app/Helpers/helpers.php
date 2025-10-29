<?php

use App\Models\NotificationUser;

if (!function_exists('getCurrentUser')) {
    function getCurrentUser()
    {
        $empData = session('emp_data');
        if (!$empData) {
            return null;
        }

        return NotificationUser::firstOrCreate(
            ['emp_id' => $empData['emp_id']],
            [
                'emp_name' => $empData['emp_name'] ?? 'Unknown',
                'emp_dept' => $empData['emp_dept'] ?? 'Unknown',
            ]
        );
    }
}
