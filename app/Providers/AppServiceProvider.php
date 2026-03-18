<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\MeetingScheduled;
use App\Events\TaskStatusChanged;
use App\Listeners\CreateFollowUpOnWaiting;
use App\Listeners\CreateRecurringTaskOccurrence;
use App\Listeners\ScheduleNextMeeting;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Task;
use App\Observers\ActivityObserver;
use App\Observers\TaskObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Primary application service provider.
 *
 * Registers event-to-listener mappings and bootstraps application services.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        Task::observe(ActivityObserver::class);
        FollowUp::observe(ActivityObserver::class);
        Meeting::observe(ActivityObserver::class);

        Event::listen(TaskStatusChanged::class, CreateFollowUpOnWaiting::class);
        Event::listen(TaskStatusChanged::class, CreateRecurringTaskOccurrence::class);
        Event::listen(MeetingScheduled::class, ScheduleNextMeeting::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
