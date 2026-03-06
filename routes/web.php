<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ExportImportController;
use App\Http\Controllers\Api\ReorderController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Web\BilaPageController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\FollowUpPageController;
use App\Http\Controllers\Web\NotePageController;
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
    Route::get('/tasks/kanban', [TaskPageController::class, 'kanban'])->name('tasks.kanban');

    Route::get('/follow-ups', [FollowUpPageController::class, 'index'])->name('follow-ups.index');
    Route::patch('/follow-ups/{followUp}/done', [FollowUpPageController::class, 'markDone'])->name('follow-ups.done');
    Route::patch('/follow-ups/{followUp}/snooze', [FollowUpPageController::class, 'snooze'])->name('follow-ups.snooze');
    Route::post('/follow-ups/{followUp}/convert', [FollowUpPageController::class, 'convertToTask'])->name('follow-ups.convert');

    Route::get('/teams', [TeamPageController::class, 'index'])->name('teams.index');
    Route::get('/teams/{team}', [TeamPageController::class, 'show'])->name('teams.show');
    Route::get('/teams/member/{teamMember}', [TeamPageController::class, 'member'])->name('teams.member');

    Route::get('/notes', [NotePageController::class, 'index'])->name('notes.index');

    Route::get('/bilas', [BilaPageController::class, 'index'])->name('bilas.index');
    Route::get('/bilas/create', [BilaPageController::class, 'create'])->name('bilas.create');
    Route::get('/bilas/{bila}', [BilaPageController::class, 'show'])->name('bilas.show');

    Route::get('/weekly', [WeeklyReflectionController::class, 'index'])->name('weekly.index');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.updateProfile');

    Route::post('/tasks/bulk-update', [TaskPageController::class, 'bulkUpdate'])->name('tasks.bulk-update');
    Route::post('/tasks/move', [TaskPageController::class, 'move'])->name('tasks.move');

    Route::patch('/bilas/{bila}', [BilaPageController::class, 'update'])->name('bilas.update');

    Route::patch('/members/{teamMember}', [TeamPageController::class, 'updateMember'])->name('members.update');

    Route::patch('/weekly/{weeklyReflection}', [WeeklyReflectionController::class, 'update'])->name('weekly.update');

    Route::post('/categories', [SettingsController::class, 'storeCategory'])->name('categories.store');
    Route::delete('/categories/{taskCategory}', [SettingsController::class, 'destroyCategory'])->name('categories.destroy');

    Route::post('/prep-items', [BilaPageController::class, 'storePrepItem'])->name('prep-items.store');
    Route::patch('/prep-items/{bilaPrepItem}', [BilaPageController::class, 'updatePrepItem'])->name('prep-items.update');
    Route::delete('/prep-items/{bilaPrepItem}', [BilaPageController::class, 'destroyPrepItem'])->name('prep-items.destroy');

    Route::post('/reorder', ReorderController::class)->name('reorder');

    Route::get('/settings/export', [ExportImportController::class, 'export'])->name('settings.export');
    Route::post('/settings/import', [ExportImportController::class, 'import'])->name('settings.import');
});
