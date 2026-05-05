<?php

use App\Http\Controllers\Api\AudienceController;
use App\Http\Controllers\Api\BroadcastController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ProgramController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public reads
    Route::get('channels', [ChannelController::class, 'index']);
    Route::get('channels/{channel}', [ChannelController::class, 'show']);
    Route::get('channels/{channel}/programs', [ChannelController::class, 'programs']);

    Route::get('programs', [ProgramController::class, 'index']);
    Route::get('programs/{program}', [ProgramController::class, 'show']);

    Route::get('broadcasts', [BroadcastController::class, 'index']);
    Route::get('broadcasts/{broadcast}', [BroadcastController::class, 'show']);

    Route::get('audience/per-channel', [AudienceController::class, 'perChannel']);
    Route::get('audience/top-programs', [AudienceController::class, 'topPrograms']);

    // Authenticated writes (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('broadcasts', [BroadcastController::class, 'store']);
        Route::patch('broadcasts/{broadcast}', [BroadcastController::class, 'update']);
        Route::delete('broadcasts/{broadcast}', [BroadcastController::class, 'destroy']);

        Route::delete('programs/{program}', [ProgramController::class, 'destroy']);
        Route::post('programs/{id}/restore', [ProgramController::class, 'restore']);
    });
});
