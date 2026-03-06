<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.dashboard', ['title' => 'Dashboard']);
})->name('dashboard');

Route::get('/tasks', function () {
    return view('pages.tasks.index', ['title' => 'Tasks']);
})->name('tasks.index');

Route::get('/tasks/kanban', function () {
    return view('pages.tasks.kanban', ['title' => 'Kanban']);
})->name('tasks.kanban');

Route::get('/follow-ups', function () {
    return view('pages.follow-ups.index', ['title' => 'Follow-ups']);
})->name('follow-ups.index');

Route::get('/teams', function () {
    return view('pages.teams.index', ['title' => 'Teams']);
})->name('teams.index');

Route::get('/notes', function () {
    return view('pages.notes.index', ['title' => 'Notes']);
})->name('notes.index');

Route::get('/bilas', function () {
    return view('pages.bilas.index', ['title' => "Bila's"]);
})->name('bilas.index');

Route::get('/weekly', function () {
    return view('pages.weekly.index', ['title' => 'Weekly Review']);
})->name('weekly.index');

Route::get('/settings', function () {
    return view('pages.settings.index', ['title' => 'Settings']);
})->name('settings.index');
