<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\AuthMiddleware;

$app_name = $app_name ?? env('APP_NAME', 'app');

Route::prefix($app_name)
    ->middleware(AuthMiddleware::class)
    ->group(function () {

        // ========================================
        // TASK ROUTES
        // ========================================

        // Get all tasks (main page)
        Route::get('/tasks', [TaskController::class, 'getTask'])->name('tasks');

        // Task actions
        Route::post('/tasks/{taskId}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::post('/tasks/{taskId}/complete', [TaskController::class, 'quickComplete'])->name('tasks.complete');
        Route::post('/tasks/{taskId}/note', [TaskController::class, 'addNote'])->name('tasks.note');
        Route::get('/tasks/{taskId}/history', [TaskController::class, 'getHistory'])->name('tasks.history');
    });
