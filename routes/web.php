<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Inertia\Inertia;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Controllers\DashboardController;

$app_name = env('APP_NAME', '');

// ------------------------------------------------------------------
// 1️⃣ Built-in Broadcast Auth Route for Pusher
// ------------------------------------------------------------------
// This automatically registers `/broadcasting/auth`
// and ensures only authenticated users can access private/presence channels.
Broadcast::routes(['middleware' => ['web', AuthMiddleware::class]]);

// ------------------------------------------------------------------
// 2️⃣ Redirect root URL to app name
// ------------------------------------------------------------------
Route::redirect('/', "/$app_name");

// ------------------------------------------------------------------
// 3️⃣ Main app routes (protected by your session middleware)
// ------------------------------------------------------------------
Route::prefix($app_name)
    ->middleware(AuthMiddleware::class)
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });

// ------------------------------------------------------------------
// 4️⃣ Include other route files
// ------------------------------------------------------------------
require __DIR__ . '/api.php';
require __DIR__ . '/ticketing.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/general.php';

// ------------------------------------------------------------------
// 5️⃣ Fallback
// ------------------------------------------------------------------
Route::fallback(function () {
    return redirect()->to(request()->root());
})->name('404');
