<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTeamController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserDepartmentController;
use App\Http\Controllers\Api\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'Construction ERP API is running',
            'version' => 'v1',
        ]);
    });

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('account.access')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);

            Route::middleware('company.context')->group(function () {
                Route::get('/company', [CompanyController::class, 'show']);
                Route::match(['put', 'patch'], '/company', [CompanyController::class, 'update']);
                Route::put('/me', [ProfileController::class, 'update']);
                Route::put('/me/password', [ProfileController::class, 'password']);
                Route::get('/users', [UserController::class, 'index']);
                Route::post('/users', [UserController::class, 'store']);
                Route::get('/users/{id}', [UserController::class, 'show']);
                Route::match(['put', 'patch'], '/users/{id}', [UserController::class, 'update']);
                Route::patch('/users/{id}/status', [UserController::class, 'status']);
                Route::delete('/users/{id}', [UserController::class, 'destroy']);
                Route::put('/users/{id}/roles', [UserRoleController::class, 'update']);
                Route::put('/users/{id}/department', [UserDepartmentController::class, 'update']);
                Route::get('/departments', [DepartmentController::class, 'index']);
                Route::post('/departments', [DepartmentController::class, 'store']);
                Route::get('/departments/{id}', [DepartmentController::class, 'show']);
                Route::match(['put', 'patch'], '/departments/{id}', [DepartmentController::class, 'update']);
                Route::patch('/departments/{id}/status', [DepartmentController::class, 'status']);
                Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);
                Route::get('/departments/{id}/users', [DepartmentController::class, 'users']);
                Route::get('/projects', [ProjectController::class, 'index']);
                Route::post('/projects', [ProjectController::class, 'store']);
                Route::get('/projects/{id}', [ProjectController::class, 'show']);
                Route::match(['put', 'patch'], '/projects/{id}', [ProjectController::class, 'update']);
                Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
                Route::patch('/projects/{id}/status', [ProjectController::class, 'status']);
                Route::patch('/projects/{id}/progress', [ProjectController::class, 'progress']);
                Route::get('/projects/{id}/team', [ProjectTeamController::class, 'show']);
                Route::put('/projects/{id}/team', [ProjectTeamController::class, 'update']);
                Route::get('/projects/{id}/overview', [ProjectController::class, 'overview']);
                Route::get('/permissions', [PermissionController::class, 'index']);
                Route::apiResource('roles', RoleController::class)->except(['edit']);
            });
        });
    });
});

// Legacy API endpoints retained for backward compatibility.
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('account.access')->group(function () {
        Route::get('/user', [AuthController::class, 'me']);

        Route::middleware('company.context')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
        });
    });
});
