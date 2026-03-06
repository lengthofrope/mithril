<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\BilaScheduled;
use App\Events\TaskStatusChanged;
use App\Listeners\CreateFollowUpOnWaiting;
use App\Listeners\ScheduleNextBila;
use Illuminate\Support\Facades\Event;
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
        Event::listen(TaskStatusChanged::class, CreateFollowUpOnWaiting::class);
        Event::listen(BilaScheduled::class, ScheduleNextBila::class);
    }
}
