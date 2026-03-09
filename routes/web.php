<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ExportImportController;
use App\Http\Controllers\Api\ReorderController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\AnalyticsPageController;
use App\Http\Controllers\Web\BilaPageController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FollowUpPageController;
use App\Http\Controllers\Web\NotePageController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\TaskPageController;
use App\Http\Controllers\Web\TeamPageController;
use App\Http\Controllers\Web\WeeklyReflectionController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLoginForm'])
    ->name('login')
    ->middleware('guest');

Route::post('/login', [LoginController::class, 'login'])
    ->middleware('guest');

Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tasks', [TaskPageController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskPageController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/kanban', [TaskPageController::class, 'kanban'])->name('tasks.kanban');
    Route::get('/tasks/{task}', [TaskPageController::class, 'show'])->name('tasks.show');

    Route::get('/follow-ups', [FollowUpPageController::class, 'index'])->name('follow-ups.index');
    Route::post('/follow-ups', [FollowUpPageController::class, 'store'])->name('follow-ups.store');
    Route::get('/follow-ups/{followUp}', [FollowUpPageController::class, 'show'])->name('follow-ups.show');
    Route::patch('/follow-ups/{followUp}/done', [FollowUpPageController::class, 'markDone'])->name('follow-ups.done');
    Route::patch('/follow-ups/{followUp}/snooze', [FollowUpPageController::class, 'snooze'])->name('follow-ups.snooze');
    Route::post('/follow-ups/{followUp}/convert', [FollowUpPageController::class, 'convertToTask'])->name('follow-ups.convert');
    Route::delete('/follow-ups/{followUp}', [FollowUpPageController::class, 'destroy'])->name('follow-ups.destroy');

    Route::get('/teams', [TeamPageController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamPageController::class, 'store'])->name('teams.store');
    Route::get('/teams/member/{teamMember}', [TeamPageController::class, 'member'])->name('teams.member');
    Route::delete('/teams/member/{teamMember}', [TeamPageController::class, 'destroyMember'])->name('teams.member.destroy');
    Route::get('/teams/{team}', [TeamPageController::class, 'show'])->name('teams.show');
    Route::patch('/teams/{team}', [TeamPageController::class, 'update'])->name('teams.update');
    Route::delete('/teams/{team}', [TeamPageController::class, 'destroy'])->name('teams.destroy');
    Route::post('/teams/{team}/members', [TeamPageController::class, 'storeMember'])->name('teams.members.store');

    Route::get('/notes', [NotePageController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NotePageController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}', [NotePageController::class, 'show'])->name('notes.show');
    Route::patch('/notes/{note}', [NotePageController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [NotePageController::class, 'destroy'])->name('notes.destroy');

    Route::get('/bilas', [BilaPageController::class, 'index'])->name('bilas.index');
    Route::post('/bilas', [BilaPageController::class, 'store'])->name('bilas.store');
    Route::get('/bilas/{bila}', [BilaPageController::class, 'show'])->name('bilas.show');
    Route::delete('/bilas/{bila}', [BilaPageController::class, 'destroy'])->name('bilas.destroy');

    Route::get('/weekly', [WeeklyReflectionController::class, 'index'])->name('weekly.index');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/tasks', [SettingsController::class, 'tasks'])->name('settings.tasks');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.updateProfile');

    Route::post('/tasks/bulk-update', [TaskPageController::class, 'bulkUpdate'])->name('tasks.bulk-update');
    Route::post('/tasks/move', [TaskPageController::class, 'move'])->name('tasks.move');

    Route::patch('/bilas/{bila}', [BilaPageController::class, 'update'])->name('bilas.update');

    Route::patch('/members/{teamMember}', [TeamPageController::class, 'updateMember'])->name('members.update');

    Route::patch('/weekly/{weeklyReflection}', [WeeklyReflectionController::class, 'update'])->name('weekly.update');

    Route::post('/categories', [SettingsController::class, 'storeCategory'])->name('categories.store');
    Route::delete('/categories/{taskCategory}', [SettingsController::class, 'destroyCategory'])->name('categories.destroy');

    Route::post('/task-groups', [SettingsController::class, 'storeTaskGroup'])->name('task-groups.store');
    Route::delete('/task-groups/{taskGroup}', [SettingsController::class, 'destroyTaskGroup'])->name('task-groups.destroy');

    Route::post('/prep-items', [BilaPageController::class, 'storePrepItem'])->name('prep-items.store');
    Route::patch('/prep-items/{bilaPrepItem}', [BilaPageController::class, 'updatePrepItem'])->name('prep-items.update');
    Route::delete('/prep-items/{bilaPrepItem}', [BilaPageController::class, 'destroyPrepItem'])->name('prep-items.destroy');

    Route::get('/analytics', [AnalyticsPageController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/widget-data', [AnalyticsPageController::class, 'widgetData'])->name('analytics.widget-data');
    Route::post('/analytics/widgets', [AnalyticsPageController::class, 'store'])->name('analytics.widgets.store');
    Route::patch('/analytics/widgets/{analyticsWidget}', [AnalyticsPageController::class, 'update'])->name('analytics.widgets.update');
    Route::delete('/analytics/widgets/{analyticsWidget}', [AnalyticsPageController::class, 'destroy'])->name('analytics.widgets.destroy');

    Route::post('/reorder', ReorderController::class)->name('reorder');

    Route::get('/settings/export', [ExportImportController::class, 'export'])->name('settings.export');
    Route::post('/settings/import', [ExportImportController::class, 'import'])->name('settings.import');
});
