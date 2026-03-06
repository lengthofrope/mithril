<?php

declare(strict_types=1);

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

    Route::get('/teams', [TeamPageController::class, 'index'])->name('teams.index');
    Route::get('/teams/{team}', [TeamPageController::class, 'show'])->name('teams.show');
    Route::get('/teams/member/{teamMember}', [TeamPageController::class, 'member'])->name('teams.member');

    Route::get('/notes', [NotePageController::class, 'index'])->name('notes.index');

    Route::get('/bilas', [BilaPageController::class, 'index'])->name('bilas.index');
    Route::get('/bilas/{bila}', [BilaPageController::class, 'show'])->name('bilas.show');

    Route::get('/weekly', [WeeklyReflectionController::class, 'index'])->name('weekly.index');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.updateProfile');
});
