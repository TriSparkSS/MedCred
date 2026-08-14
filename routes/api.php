<?php

use App\Http\Controllers\Api\V1\ProviderApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/token', [ProviderApiController::class, 'createToken'])
        ->middleware('auth:web');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('provider/cases', [ProviderApiController::class, 'cases']);
        Route::get('provider/documents', [ProviderApiController::class, 'documents']);
    });
});
