<?php

use App\Http\Controllers\Api\V1\ChatCompletionController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProjectApiKeyController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\UsageController;
use App\Http\Middleware\AuthenticateProjectApiKey;
use App\Http\Middleware\EnforcePlanLimits;
use App\Http\Middleware\EnsureRequestId;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureRequestId::class, AuthenticateProjectApiKey::class, EnforcePlanLimits::class])
    ->prefix('v1')
    ->group(function () {
        Route::post('/chat/completions', [ChatCompletionController::class, 'create']);
    });

Route::middleware(['auth:sanctum', EnsureRequestId::class])
    ->prefix('v1')
    ->group(function () {
        Route::post('/orgs', [OrganizationController::class, 'store']);
        Route::get('/orgs/{organization}', [OrganizationController::class, 'show']);

        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);

        Route::post('/projects/{project}/keys', [ProjectApiKeyController::class, 'store']);
        Route::get('/projects/{project}/keys', [ProjectApiKeyController::class, 'index']);
        Route::delete('/projects/{project}/keys/{apiKey}', [ProjectApiKeyController::class, 'destroy']);

        Route::get('/usage/daily', [UsageController::class, 'daily']);
        Route::get('/usage/summary', [UsageController::class, 'summary']);
    });

