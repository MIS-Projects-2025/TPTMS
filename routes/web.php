<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Inertia\Inertia;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Controllers\DashboardController;

$app_name = env('APP_NAME', '');




Route::redirect('/', "/$app_name");


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
