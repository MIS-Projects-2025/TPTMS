<?php

use App\Http\Controllers\ProjectConstantsController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Models\NotificationUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Controllers\TicketingController;
// ========== PUBLIC API ROUTES (No authentication required) ==========




Route::post('/TPTMS/broadcasting/auth', function (Request $request) {
    try {
        $empData = session('emp_data');
        Log::info('Broadcast Auth Session:', ['emp_data' => $empData]);

        $user = $empData ? \App\Models\NotificationUser::firstOrCreate(
            ['emp_id' => $empData['emp_id']],
            [
                'emp_name' => $empData['emp_name'] ?? 'Unknown',
                'emp_dept' => $empData['emp_dept'] ?? 'Unknown',
            ]
        ) : null;

        if (!$user) {
            Log::error('No user resolved for broadcast auth');
            return response()->json(['error' => 'Unauthorized: session missing'], 403);
        }

        Log::info('Broadcast auth success for user:', ['emp_id' => $user->emp_id]);
        Log::info('Broadcast auth session:', ['emp_data' => session('emp_data')]);
        if (!$user) {
            Log::warning('No user found for broadcast auth');
        }

        $request->setUserResolver(fn() => $user);

        return Broadcast::auth($request);
    } catch (\Throwable $e) {
        Log::error('Broadcast auth exception', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});



// ========== PROTECTED API ROUTES (Protected by AuthMiddleware) ==========
Route::prefix('api')->middleware(AuthMiddleware::class)->group(function () {

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
                    'action_required' => $notif->data['action_required'] ?? null,
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
    Route::get('/tickets/assigned/{empId}', [TicketingController::class, 'getAssignedTickets']);
    Route::get('/tickets/count', [TicketingController::class, 'getTicketsCount'])->name('tickets.count');

    Route::get('/projects/assigned/{empId}', [ProjectController::class, 'getAssignedProjects']);
    Route::get('/projects/handlers/{department}', [ProjectController::class, 'getHandlerOptionsByDepartment'])
        ->name('projects.getHandlers');
    Route::get('/departments', [ProjectController::class, 'getAllDepartments'])
        ->name('projects.getAllDepartments');
    Route::get('/project-constants', [ProjectConstantsController::class, 'index'])
        ->name('api.project-constants');
});
