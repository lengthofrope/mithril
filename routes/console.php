<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use App\Notifications\BilaReminderNotification;
use App\Notifications\FollowUpDueNotification;
use App\Notifications\TaskDeadlineNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $users = User::where('push_enabled', true)->get();

    if ($users->isEmpty()) {
        return;
    }

    $dueFollowUps = FollowUp::whereDate('follow_up_date', today())
        ->where('status', FollowUpStatus::Open)
        ->get();

    foreach ($dueFollowUps as $followUp) {
        foreach ($users as $user) {
            $user->notify(new FollowUpDueNotification($followUp));
        }
    }
})->hourly()->name('notify.follow-ups-due')->withoutOverlapping();

Schedule::call(function (): void {
    $users = User::where('push_enabled', true)->get();

    if ($users->isEmpty()) {
        return;
    }

    $todaysBilas = Bila::whereDate('scheduled_date', today())
        ->with('teamMember')
        ->get();

    foreach ($todaysBilas as $bila) {
        foreach ($users as $user) {
            $user->notify(new BilaReminderNotification($bila));
        }
    }
})->dailyAt('08:00')->name('notify.bilas-today')->withoutOverlapping();

Schedule::call(function (): void {
    $users = User::where('push_enabled', true)->get();

    if ($users->isEmpty()) {
        return;
    }

    $dueTasks = Task::whereDate('deadline', today())
        ->where('status', '!=', TaskStatus::Done)
        ->get();

    foreach ($dueTasks as $task) {
        foreach ($users as $user) {
            $user->notify(new TaskDeadlineNotification($task));
        }
    }
})->dailyAt('08:00')->name('notify.task-deadlines')->withoutOverlapping();
