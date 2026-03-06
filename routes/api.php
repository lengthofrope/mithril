<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AgreementController;
use App\Http\Controllers\Api\AutoSaveController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\BilaController;
use App\Http\Controllers\Api\ExportImportController;
use App\Http\Controllers\Api\FollowUpController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ReorderController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:web')->group(function (): void {
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('teams', TeamController::class);
    Route::apiResource('team-members', TeamMemberController::class);
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('follow-ups', FollowUpController::class);
    Route::apiResource('bilas', BilaController::class);
    Route::apiResource('agreements', AgreementController::class);

    Route::post('reorder', ReorderController::class);
    Route::post('auto-save', AutoSaveController::class);

    Route::get('search', [SearchController::class, 'search']);

    Route::get('export', [ExportImportController::class, 'export']);
    Route::post('import', [ExportImportController::class, 'import']);

    Route::post('push/subscribe', [PushSubscriptionController::class, 'store']);
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy']);
});
