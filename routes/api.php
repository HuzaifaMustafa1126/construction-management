<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DailySiteReportController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\MaterialCategoryController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\MeasurementUnitController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectInventoryController;
use App\Http\Controllers\Api\ProjectTeamController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SitePhotoController;
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
                Route::get('/projects/{id}/inventory/overview', [ProjectInventoryController::class, 'overview']);
                Route::get('/projects/{id}/inventory', [ProjectInventoryController::class, 'index']);
                Route::post('/projects/{id}/inventory', [ProjectInventoryController::class, 'store']);
                Route::get('/projects/{id}/inventory/{inventoryId}', [ProjectInventoryController::class, 'show']);
                Route::patch('/projects/{id}/inventory/{inventoryId}', [ProjectInventoryController::class, 'update']);
                Route::post('/projects/{id}/inventory/{inventoryId}/opening-stock', [ProjectInventoryController::class, 'openingStock']);
                Route::get('/projects/{id}/inventory/{inventoryId}/transactions', [ProjectInventoryController::class, 'transactions']);
                Route::get('/projects/{id}/daily-reports', [DailySiteReportController::class, 'index']);
                Route::post('/projects/{id}/daily-reports', [DailySiteReportController::class, 'store']);
                Route::get('/projects/{id}/daily-reports/{reportId}', [DailySiteReportController::class, 'show']);
                Route::match(['put', 'patch'], '/projects/{id}/daily-reports/{reportId}', [DailySiteReportController::class, 'update']);
                Route::post('/projects/{id}/daily-reports/{reportId}/submit', [DailySiteReportController::class, 'submit']);
                Route::post('/projects/{id}/daily-reports/{reportId}/review', [DailySiteReportController::class, 'review']);
                Route::patch('/projects/{id}/daily-reports/{reportId}/visibility', [DailySiteReportController::class, 'visibility']);
                Route::delete('/projects/{id}/daily-reports/{reportId}', [DailySiteReportController::class, 'destroy']);
                Route::get('/projects/{id}/daily-reports/{reportId}/photos', [SitePhotoController::class, 'reportPhotos']);
                Route::get('/projects/{id}/photos', [SitePhotoController::class, 'index']);
                Route::post('/projects/{id}/photos', [SitePhotoController::class, 'store']);
                Route::post('/projects/{id}/photos/batch', [SitePhotoController::class, 'batch']);
                Route::get('/projects/{id}/photos/{photoId}', [SitePhotoController::class, 'show']);
                Route::get('/projects/{id}/photos/{photoId}/file', [SitePhotoController::class, 'file']);
                Route::match(['put', 'patch'], '/projects/{id}/photos/{photoId}', [SitePhotoController::class, 'update']);
                Route::post('/projects/{id}/photos/{photoId}/review', [SitePhotoController::class, 'review']);
                Route::patch('/projects/{id}/photos/{photoId}/visibility', [SitePhotoController::class, 'visibility']);
                Route::delete('/projects/{id}/photos/{photoId}', [SitePhotoController::class, 'destroy']);
                Route::get('/permissions', [PermissionController::class, 'index']);
                Route::get('/measurement-units', [MeasurementUnitController::class, 'index']);
                Route::post('/measurement-units', [MeasurementUnitController::class, 'store']);
                Route::get('/measurement-units/{id}', [MeasurementUnitController::class, 'show']);
                Route::match(['put', 'patch'], '/measurement-units/{id}', [MeasurementUnitController::class, 'update']);
                Route::delete('/measurement-units/{id}', [MeasurementUnitController::class, 'destroy']);
                Route::get('/material-categories', [MaterialCategoryController::class, 'index']);
                Route::post('/material-categories', [MaterialCategoryController::class, 'store']);
                Route::get('/material-categories/{id}', [MaterialCategoryController::class, 'show']);
                Route::match(['put', 'patch'], '/material-categories/{id}', [MaterialCategoryController::class, 'update']);
                Route::patch('/material-categories/{id}/status', [MaterialCategoryController::class, 'status']);
                Route::delete('/material-categories/{id}', [MaterialCategoryController::class, 'destroy']);
                Route::get('/materials', [MaterialController::class, 'index']);
                Route::post('/materials', [MaterialController::class, 'store']);
                Route::get('/materials/{id}', [MaterialController::class, 'show']);
                Route::match(['put', 'patch'], '/materials/{id}', [MaterialController::class, 'update']);
                Route::patch('/materials/{id}/status', [MaterialController::class, 'status']);
                Route::delete('/materials/{id}', [MaterialController::class, 'destroy']);
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
