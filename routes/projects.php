<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectController;
use App\Http\Middleware\AuthMiddleware;

$app_name = $app_name ?? env('APP_NAME', 'app');

Route::prefix($app_name)
->middleware(AuthMiddleware::class)
    ->group(function () {

        Route::get('/projects/datatable', [ProjectController::class, 'getProjectsDataTable'])
            ->name('project.list');

        // Add the store route for creating projects
        Route::post('/projects', [ProjectController::class, 'store'])->name('project.store');

        // Excel Import Routes
        Route::post('/projectList/import', [ProjectController::class, 'importExcel'])->name('project.import');
        Route::get('/projectList/template', [ProjectController::class, 'downloadTemplate'])->name('project.template');
        Route::get('/projects/{id}/logs', [ProjectController::class, 'getProjectLogs'])->name('project.logs');
        Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('project.update');
    });
