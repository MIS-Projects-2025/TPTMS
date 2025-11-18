<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Notifications\TicketCreatedNotification;

$app_name = env('APP_NAME', '');


Route::get('/reverb-test', function () {
    return 'Reverb SSL Test - If you see this, certificate is accepted!';
});

Route::get('/test-broadcast/{userId}', function ($userId) {
    $user = \App\Models\NotificationUser::where('emp_id', $userId)->first();

    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    Log::info('🧪 Test: Sending notification to user', [
        'emp_id' => $user->emp_id,
        'email' => $user->email
    ]);

    $notification = new TicketCreatedNotification(
        'TEST-' . time(),
        'test',
        'System Test',
        'This is a test notification to verify broadcasting',
        'Test Project',
        'Test Request'
    );

    $user->notify($notification);

    return response()->json([
        'success' => true,
        'message' => 'Notification sent',
        'channel' => 'users.' . $user->emp_id,
        'check' => 'Look at Reverb logs and browser console'
    ]);
});

Route::redirect('/', "/$app_name");
// Required for private channel authentication
// Broadcast::routes(['middleware' => ['web']]);

// ------------------------------------------------------------------
// 4️⃣ Include other route files
// ------------------------------------------------------------------
require __DIR__ . '/api.php';
require __DIR__ . '/ticketing.php';
require __DIR__ . '/projects.php';
require __DIR__ . '/tasks.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/general.php';

// ------------------------------------------------------------------
// 5️⃣ Fallback
// ------------------------------------------------------------------
Route::fallback(function () {
    return redirect()->to(request()->root());
})->name('404');
