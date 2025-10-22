<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\AuthMiddleware;

$app_name = $app_name ?? env('APP_NAME', 'app');

Route::prefix($app_name)
    ->middleware(AuthMiddleware::class) // ✅ Middleware applied here
    ->group(function () {

        Route::get('/tasks', [TaskController::class, 'getTask'])->name('tasks');
    });
