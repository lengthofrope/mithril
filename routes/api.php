<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AgreementController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AutoSaveController;
use App\Http\Controllers\Api\CalendarActionController;
use App\Http\Controllers\Api\CounterController;
use App\Http\Controllers\Api\EmailActionController;

use App\Http\Controllers\Api\BilaController;
use App\Http\Controllers\Api\ExportImportController;
use App\Http\Controllers\Api\JiraActionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\JiraIssueController;
use App\Http\Controllers\Api\SystemNotificationController;
use App\Http\Controllers\Api\FollowUpController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NoteTagController;
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

    Route::put('notes/{note}/tags', [NoteTagController::class, 'sync'])->name('notes.tags.sync');

    Route::post('reorder', ReorderController::class);
    Route::post('auto-save', AutoSaveController::class);

    Route::prefix('{type}/{id}/activities')
        ->whereIn('type', ['tasks', 'follow-ups', 'notes', 'bilas'])
        ->group(function (): void {
            Route::post('/', [ActivityController::class, 'store']);
            Route::patch('{activity}', [ActivityController::class, 'update']);
            Route::delete('{activity}', [ActivityController::class, 'destroy']);
        });

    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    Route::get('counters', CounterController::class)->name('counters');
    Route::get('search', [SearchController::class, 'search']);

    Route::get('export', [ExportImportController::class, 'export']);
    Route::post('import', [ExportImportController::class, 'import']);

    Route::prefix('sync')->as('sync.')->group(function (): void {
        Route::post('jira', [SyncController::class, 'jira'])->name('jira');
        Route::post('calendar', [SyncController::class, 'calendar'])->name('calendar');
        Route::post('emails', [SyncController::class, 'emails'])->name('emails');
        Route::get('{type}/status', [SyncController::class, 'status'])
            ->name('status')
            ->whereIn('type', ['jira', 'calendar', 'emails']);
    });

    Route::prefix('emails')->as('emails.')->group(function (): void {
        Route::get('/', [EmailActionController::class, 'index'])->name('index');
        Route::get('dashboard', [EmailActionController::class, 'dashboard'])->name('dashboard');

        Route::prefix('{email}')->group(function (): void {
            Route::get('prefill/{type}', [EmailActionController::class, 'prefill'])
                ->name('prefill')
                ->whereIn('type', ['task', 'follow-up', 'note', 'bila']);

            Route::post('create/{type}', [EmailActionController::class, 'create'])
                ->name('create')
                ->whereIn('type', ['task', 'follow-up', 'note', 'bila']);

            Route::delete('links/{emailLink}', [EmailActionController::class, 'unlink'])->name('unlink');
        });
    });

    Route::prefix('jira-issues')->as('jira-issues.')->group(function (): void {
        Route::get('/', [JiraIssueController::class, 'index'])->name('index');
        Route::get('dashboard', [JiraIssueController::class, 'dashboard'])->name('dashboard');
        Route::patch('{jiraIssue}/dismiss', [JiraIssueController::class, 'dismiss'])->name('dismiss');
        Route::patch('{jiraIssue}/undismiss', [JiraIssueController::class, 'undismiss'])->name('undismiss');

        Route::get('{jiraIssue}/prefill/{type}', [JiraActionController::class, 'prefill'])
            ->name('prefill')
            ->whereIn('type', ['task', 'follow-up', 'note', 'bila']);

        Route::post('{jiraIssue}/create/{type}', [JiraActionController::class, 'create'])
            ->name('create')
            ->whereIn('type', ['task', 'follow-up', 'note', 'bila']);

        Route::delete('{jiraIssue}/links/{jiraIssueLink}', [JiraActionController::class, 'unlink'])
            ->name('unlink');
    });

    Route::patch('system-notifications/{systemNotification}/dismiss', [SystemNotificationController::class, 'dismiss'])
        ->name('system-notifications.dismiss');

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
