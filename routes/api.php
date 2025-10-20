<?php

use Illuminate\Support\Facades\Route;
use App\Models\NotificationUser;

function getCurrentUser()
{
    $empData = session('emp_data');
    // dd($empData);
    if (!$empData) {
        return null;
    }

    // Use NotificationUser instead of User
    $user = NotificationUser::where('emp_id', $empData['emp_id'])->first();

    // Auto-create if doesn't exist
    if (!$user) {
        $user = NotificationUser::create([
            'emp_id' => $empData['emp_id'],
            'emp_name' => $empData['emp_name'] ?? 'Unknown',
            'emp_dept' => $empData['emp_dept'] ?? 'Unknown',
        ]);
    }

    return $user;
}

// ========== API ROUTES WITH /api PREFIX ==========
Route::prefix('api')->group(function () {

    // Get all unread notifications
    Route::get('/notifications', function () {
        $user = getCurrentUser();
        if (!$user) {
            return response()->json(['error' => 'Not logged in'], 401);
        }

        $notifications = $user->notifications()
            ->whereNull('read_at')
            ->latest()
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'ticket_id' => $notif->data['ticket_id'] ?? null,
                    'message' => $notif->data['message'] ?? '',
                    'type' => $notif->data['type'] ?? '',
                    'project' => $notif->data['project_name'] ?? '',
                    'created_at' => $notif->created_at->format('Y-m-d H:i:s'),
                    'read_at' => $notif->read_at,
                ];
            });

        return response()->json($notifications);
    });

    // Mark single notification as read
    Route::put('/notifications/{id}/read', function ($id) {
        $user = getCurrentUser();
        if (!$user) {
            return response()->json(['error' => 'Not logged in'], 401);
        }

        $notification = $user->notifications()->find($id);
        if ($notification) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['success' => true]);
    });

    // Mark all notifications as read
    Route::put('/notifications/read-all', function () {
        $user = getCurrentUser();
        if (!$user) {
            return response()->json(['error' => 'Not logged in'], 401);
        }

        $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    });

    // Get unread count
    Route::get('/notifications/count', function () {
        $user = getCurrentUser();
        $count = $user ? $user->notifications()->whereNull('read_at')->count() : 0;
        return response()->json(['unread_count' => $count]);
    });

    // Debug: Get session data
    Route::get('/debug/session', function () {
        return response()->json([
            'session_exists' => session()->has('emp_data'),
            'emp_data' => session('emp_data'),
        ]);
    });

    // Debug: Get current user
    Route::get('/debug/user', function () {
        $user = getCurrentUser();
        if (!$user) {
            return response()->json([
                'error' => 'User not found',
                'session_emp_id' => session('emp_data')['emp_id'] ?? null,
            ]);
        }
        return response()->json([
            'emp_id' => $user->emp_id,
            'emp_name' => $user->emp_name,
            'emp_dept' => $user->emp_dept,
            'unread_count' => $user->notifications()->whereNull('read_at')->count(),
        ]);
    });
});
