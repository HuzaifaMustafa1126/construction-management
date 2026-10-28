<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdministratorPasswordController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyRegistrationController;
use App\Http\Controllers\Api\DailySiteReportController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\InventoryReportController;
use App\Http\Controllers\Api\InventoryTransferController;
use App\Http\Controllers\Api\MaterialCategoryController;
use App\Http\Controllers\Api\MaterialConsumptionController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\MaterialRequestController;
use App\Http\Controllers\Api\MaterialDeliveryController;
use App\Http\Controllers\Api\MaterialStockAdjustmentController;
use App\Http\Controllers\Api\MeasurementUnitController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PlatformCompanyController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectInventoryController;
use App\Http\Controllers\Api\ProjectTeamController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SitePhotoController;
use App\Http\Controllers\Api\SupplierController;
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
    Route::post('/administrator/password/setup', [AdministratorPasswordController::class, 'store'])
        ->middleware('throttle:5,1');
    Route::get('/verify-email/{id}/{hash}', [CompanyRegistrationController::class, 'verify'])->middleware('throttle:company-verification')->name('verification.verify');
    Route::post('/resend-verification', [CompanyRegistrationController::class, 'resend'])->middleware('throttle:company-verification-resend');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::prefix('platform')->middleware('platform.admin')->group(function () {
            Route::get('/dashboard', [PlatformCompanyController::class, 'dashboard']);
            Route::get('/companies', [PlatformCompanyController::class, 'index']);
            Route::post('/companies', [PlatformCompanyController::class, 'store']);
            Route::get('/companies/{company}', [PlatformCompanyController::class, 'show']);
            Route::patch('/companies/{company}', [PlatformCompanyController::class, 'update']);
            Route::patch('/companies/{company}/status', [PlatformCompanyController::class, 'status']);
            Route::patch('/companies/{company}/administrators/{user}/status', [PlatformCompanyController::class, 'administratorStatus']);
        });

        Route::middleware('account.access')->group(function () {
            Route::put('/me', [ProfileController::class, 'update']);
            Route::put('/me/password', [ProfileController::class, 'password'])->middleware('throttle:5,1');
            Route::middleware('company.context')->group(function () {
                Route::get('/company', [CompanyController::class, 'show']);
                Route::match(['put', 'patch'], '/company', [CompanyController::class, 'update']);
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
                Route::get('/projects/{id}/inventory-reports/overview', [InventoryReportController::class, 'overview']);
                Route::get('/projects/{id}/inventory-reports/current-stock', [InventoryReportController::class, 'currentStock']);
                Route::get('/projects/{id}/inventory-reports/alerts', [InventoryReportController::class, 'alerts']);
                Route::get('/projects/{id}/inventory-reports/movements', [InventoryReportController::class, 'movements']);
                Route::get('/projects/{id}/inventory-reports/received-vs-consumed', [InventoryReportController::class, 'receivedVsConsumed']);
                Route::get('/projects/{id}/inventory-reports/wastage', [InventoryReportController::class, 'wastage']);
                Route::get('/projects/{id}/material-deliveries', [MaterialDeliveryController::class, 'index']);
                Route::post('/projects/{id}/material-deliveries', [MaterialDeliveryController::class, 'store']);
                Route::get('/projects/{id}/material-deliveries/{deliveryId}', [MaterialDeliveryController::class, 'show']);
                Route::patch('/projects/{id}/material-deliveries/{deliveryId}', [MaterialDeliveryController::class, 'update']);
                Route::post('/projects/{id}/material-deliveries/{deliveryId}/receive', [MaterialDeliveryController::class, 'receive']);
                Route::post('/projects/{id}/material-deliveries/{deliveryId}/cancel', [MaterialDeliveryController::class, 'cancel']);
                Route::get('/projects/{id}/material-consumptions/summary', [MaterialConsumptionController::class, 'summary']);
                Route::get('/projects/{id}/material-consumptions', [MaterialConsumptionController::class, 'index']);
                Route::post('/projects/{id}/material-consumptions', [MaterialConsumptionController::class, 'store']);
                Route::get('/projects/{id}/material-consumptions/{consumptionId}', [MaterialConsumptionController::class, 'show']);
                Route::patch('/projects/{id}/material-consumptions/{consumptionId}', [MaterialConsumptionController::class, 'update']);
                Route::post('/projects/{id}/material-consumptions/{consumptionId}/post', [MaterialConsumptionController::class, 'post']);
                Route::post('/projects/{id}/material-consumptions/{consumptionId}/cancel', [MaterialConsumptionController::class, 'cancel']);
                Route::get('/projects/{id}/material-consumptions/{consumptionId}/transactions', [MaterialConsumptionController::class, 'transactions']);
                Route::get('/projects/{id}/stock-adjustments/history', [MaterialStockAdjustmentController::class, 'history']);
                Route::get('/projects/{id}/stock-adjustments/summary', [MaterialStockAdjustmentController::class, 'summary']);
                Route::get('/projects/{id}/stock-adjustments', [MaterialStockAdjustmentController::class, 'index']);
                Route::post('/projects/{id}/stock-adjustments', [MaterialStockAdjustmentController::class, 'store']);
                Route::get('/projects/{id}/stock-adjustments/{adjustmentId}', [MaterialStockAdjustmentController::class, 'show']);
                Route::match(['put', 'patch'], '/projects/{id}/stock-adjustments/{adjustmentId}', [MaterialStockAdjustmentController::class, 'update']);
                Route::post('/projects/{id}/stock-adjustments/{adjustmentId}/post', [MaterialStockAdjustmentController::class, 'post']);
                Route::post('/projects/{id}/stock-adjustments/{adjustmentId}/cancel', [MaterialStockAdjustmentController::class, 'cancel']);
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
                Route::get('/material-requests', [MaterialRequestController::class, 'index']);
                Route::post('/material-requests', [MaterialRequestController::class, 'store']);
                Route::get('/material-requests/{requestId}', [MaterialRequestController::class, 'show']);
                Route::patch('/material-requests/{requestId}', [MaterialRequestController::class, 'update']);
                Route::post('/material-requests/{requestId}/submit', [MaterialRequestController::class, 'submit']);
                Route::post('/material-requests/{requestId}/approve', [MaterialRequestController::class, 'approve']);
                Route::post('/material-requests/{requestId}/reject', [MaterialRequestController::class, 'reject']);
                Route::post('/material-requests/{requestId}/cancel', [MaterialRequestController::class, 'cancel']);
                Route::post('/material-requests/{requestId}/issue', [MaterialRequestController::class, 'issue']);
                Route::post('/material-requests/{requestId}/return', [MaterialRequestController::class, 'returnStock']);
                Route::get('/suppliers', [SupplierController::class, 'index']);
                Route::post('/suppliers', [SupplierController::class, 'store']);
                Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
                Route::match(['put', 'patch'], '/suppliers/{id}', [SupplierController::class, 'update']);
                Route::patch('/suppliers/{id}/status', [SupplierController::class, 'status']);
                Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);
                Route::get('/inventory-reports/overview', [InventoryReportController::class, 'companyOverview']);
                Route::get('/inventory-reports/projects', [InventoryReportController::class, 'companyProjects']);
                Route::get('/inventory-reports/alerts', [InventoryReportController::class, 'companyAlerts']);
                Route::post('/inventory-transfers', [InventoryTransferController::class, 'store']);
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
