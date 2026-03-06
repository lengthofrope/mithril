<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('tasks', \App\Http\Controllers\Api\TaskController::class);
    Route::apiResource('teams', \App\Http\Controllers\Api\TeamController::class);
    Route::apiResource('team-members', \App\Http\Controllers\Api\TeamMemberController::class);
    Route::apiResource('notes', \App\Http\Controllers\Api\NoteController::class);
    Route::apiResource('follow-ups', \App\Http\Controllers\Api\FollowUpController::class);
    Route::apiResource('bilas', \App\Http\Controllers\Api\BilaController::class);
    Route::apiResource('agreements', \App\Http\Controllers\Api\AgreementController::class);

    Route::post('reorder', \App\Http\Controllers\Api\ReorderController::class);
    Route::post('auto-save', \App\Http\Controllers\Api\AutoSaveController::class);
});
