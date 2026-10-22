<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:3,1');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('study', [AuthController::class, 'switchStudy']);
        Route::middleware('study.access')->group(function () {
            Route::get('companies', [CompanyController::class, 'index']);
            Route::post('companies', [CompanyController::class, 'store']);
            Route::get('companies/{company}', [CompanyController::class, 'show']);
            Route::put('companies/{company}', [CompanyController::class, 'update']);
            Route::post('companies/{company}/archive', [CompanyController::class, 'archive']);
            Route::get('team', [CompanyController::class, 'team']);
            Route::get('activity', [CompanyController::class, 'activity']);
            Route::get('works', [WorkController::class, 'index']);
            Route::post('works', [WorkController::class, 'store']);
            Route::patch('works/{work}', [WorkController::class, 'update']);
            Route::put('works/{work}/checklist', [WorkController::class, 'checklist']);
            Route::get('templates', [WorkController::class, 'templates']);
            Route::post('templates', [WorkController::class, 'saveTemplate']);
            Route::post('engagements', [WorkController::class, 'engage']);
            Route::get('tasks', [WorkController::class, 'tasks']);
            Route::post('tasks', [WorkController::class, 'saveTask']);
            Route::patch('tasks/{task}', [WorkController::class, 'updateTask']);
            Route::get('documents', [DocumentController::class, 'index']);
            Route::post('documents', [DocumentController::class, 'store']);
            Route::post('documents/{document}/upload', [DocumentController::class, 'upload'])->middleware('throttle:30,1');
            Route::patch('documents/{document}', [DocumentController::class, 'review']);
            Route::get('files/{file}', [DocumentController::class, 'download']);
            Route::get('charges', [BillingController::class, 'index']);
            Route::post('charges', [BillingController::class, 'store']);
            Route::post('charges/{charge}/payments', [BillingController::class, 'payment']);
            Route::get('reports', [ReportController::class, 'index']);
            Route::post('reports', [ReportController::class, 'store']);
            Route::post('reports/{report}/publish', [ReportController::class, 'publish']);
            Route::get('conversations', [ConversationController::class, 'index']);
            Route::post('conversations', [ConversationController::class, 'store']);
            Route::post('conversations/{conversation}/messages', [ConversationController::class, 'message']);
            Route::patch('conversations/{conversation}', [ConversationController::class, 'update']);
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
            Route::patch('members/{member}', [SettingsController::class, 'member']);
            Route::get('settings', [SettingsController::class, 'index']);
            Route::put('settings', [SettingsController::class, 'update']);
            Route::post('members', [SettingsController::class, 'user']);
        });
    });
});
Route::get('reset-password/{token}', fn () => view('app'))->name('password.reset');
Route::get('/{any?}',fn () => view('app'))->where('any','^(?!api(?:/|$)|sanctum(?:/|$)).*')->name('login');
