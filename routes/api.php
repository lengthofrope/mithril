<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AgreementController;
use App\Http\Controllers\Api\AutoSaveController;
use App\Http\Controllers\Api\CalendarActionController;
use App\Http\Controllers\Api\CounterController;

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

Route::prefix('v1')->middleware(['auth:web', 'throttle:api'])->as('api.')->group(function (): void {
    Route::apiResource('tasks', TaskController::class);
    Route::apiResource('teams', TeamController::class);
    Route::apiResource('team-members', TeamMemberController::class);
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('follow-ups', FollowUpController::class);
    Route::apiResource('bilas', BilaController::class);
    Route::apiResource('agreements', AgreementController::class);

    Route::post('reorder', ReorderController::class);
    Route::post('auto-save', AutoSaveController::class);

    Route::get('counters', CounterController::class)->name('counters');
    Route::get('search', [SearchController::class, 'search']);

    Route::get('export', [ExportImportController::class, 'export']);
    Route::post('import', [ExportImportController::class, 'import']);

    Route::prefix('calendar-events/{calendarEvent}')->as('calendar-events.')->group(function (): void {
        Route::get('prefill/{type}', [CalendarActionController::class, 'prefill'])
            ->name('prefill')
            ->whereIn('type', ['bila', 'task', 'follow-up', 'note']);

        Route::post('create/{type}', [CalendarActionController::class, 'create'])
            ->name('create')
            ->whereIn('type', ['bila', 'task', 'follow-up', 'note']);

        Route::delete('links/{calendarEventLink}', [CalendarActionController::class, 'unlink'])
            ->name('unlink');
    });

});
