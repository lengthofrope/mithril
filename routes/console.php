<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('analytics:snapshot')
    ->dailyAt('00:15')
    ->name('analytics.daily-snapshot')
    ->withoutOverlapping();

Schedule::command('microsoft:sync-calendars')
    ->everyFiveMinutes()
    ->name('microsoft.sync-calendars')
    ->withoutOverlapping();

Schedule::command('microsoft:sync-availability')
    ->everyFiveMinutes()
    ->name('microsoft.sync-availability')
    ->withoutOverlapping();
