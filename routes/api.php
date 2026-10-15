<?php

use App\Http\Controllers\Api\V1\ProviderApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/token', [ProviderApiController::class, 'issueToken'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('tokens/create', [ProviderApiController::class, 'createToken']);

        Route::get('provider/cases', [ProviderApiController::class, 'cases']);
        Route::get('provider/documents', [ProviderApiController::class, 'documents']);

        Route::get('providers', [ProviderApiController::class, 'index']);
        Route::post('providers', [ProviderApiController::class, 'store']);
        Route::get('providers/{id}', [ProviderApiController::class, 'show'])->whereNumber('id');
        Route::get('providers/{id}/cases', [ProviderApiController::class, 'cases'])->whereNumber('id');
        Route::get('providers/{id}/documents', [ProviderApiController::class, 'documents'])->whereNumber('id');
    });
});
