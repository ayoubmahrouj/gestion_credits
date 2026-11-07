<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackupController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CreditController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\MetaController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::patch('auth/password', [AuthController::class, 'changePassword']);

        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}/password', [UserController::class, 'resetPassword']);
        Route::patch('users/{user}/restore', [UserController::class, 'restore']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        Route::get('meta', [MetaController::class, 'index']);
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('reports/aging', [DashboardController::class, 'aging']);
        Route::get('reports/summary', [ReportController::class, 'summary']);

        Route::get('exports/excel', [ExportController::class, 'excel']);

        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);

        Route::get('backups', [BackupController::class, 'index']);
        Route::post('backups', [BackupController::class, 'store']);
        Route::get('backups/{file}', [BackupController::class, 'download']);
        Route::delete('backups/{file}', [BackupController::class, 'destroy']);

        Route::get('clients/{client}/statement', [ClientController::class, 'statement']);
        Route::patch('clients/{client}/restore', [ClientController::class, 'restore']);
        Route::apiResource('clients', ClientController::class);

        Route::patch('products/{product}/restore', [ProductController::class, 'restore']);
        Route::apiResource('products', ProductController::class);

        Route::patch('brands/{brand}/restore', [BrandController::class, 'restore']);
        Route::apiResource('brands', BrandController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::patch('categories/{category}/restore', [CategoryController::class, 'restore']);
        Route::apiResource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);
        Route::apiResource('credits', CreditController::class)->only(['index', 'show']);
        Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
        Route::apiResource('reminders', ReminderController::class)->only(['index', 'store']);
    });
});
