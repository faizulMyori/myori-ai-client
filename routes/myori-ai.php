<?php

use Illuminate\Support\Facades\Route;
use Myori\AiClient\Http\Controllers\ChatController;
use Myori\AiClient\Http\Controllers\ToolController;

Route::prefix(config('myori-ai.route_prefix', 'myori-ai'))->name('myori-ai.')->group(function () {
    // Called by the gateway only: every request must carry its signature.
    Route::middleware('myori-ai.signed')->group(function () {
        Route::post('tools', [ToolController::class, 'index'])->name('tools.index');
        Route::post('tools/{tool}', [ToolController::class, 'call'])->name('tools.call');
    });

    // Called by this system's own pages for the signed-in user.
    Route::post('chat', ChatController::class)
        ->middleware(config('myori-ai.chat_middleware', ['web', 'auth', 'throttle:20,1']))
        ->name('chat');
});
