<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsWidget;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use App\Services\DashboardStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Handles the main dashboard page rendering.
 *
 * Provides a greeting, counter stats, and a curated today section
 * for the team lead's start-of-day overview.
 */
class DashboardController extends Controller
{
    /**
     * Display the dashboard index page.
     *
     * @param Request $request
     * @param DashboardStatsService $statsService
     * @return View
     */
    public function index(Request $request, DashboardStatsService $statsService): View
    {
        $greeting = $this->resolveGreeting();
        $counters = $statsService->buildStats();
        $today = $this->buildTodaySection();
        $dashboardWidgets = AnalyticsWidget::forDashboard()->get();

        return view('pages.dashboard', [
            'title' => 'Dashboard',
            'greeting' => $greeting,
            'counters' => $counters,
            'todayTasks' => $today['tasks_due_today'],
            'todayFollowUps' => $today['overdue_follow_ups'],
            'todayBilas' => $today['bilas_today'],
            'dashboardWidgets' => $dashboardWidgets,
        ]);
    }

    /**
     * Build a time-based greeting string.
     *
     * @return string
     */
    private function resolveGreeting(): string
    {
        $hour = (int) now()->format('H');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Build the today section with tasks due today, overdue follow-ups, and today's bilas.
     *
     * @return array<string, mixed>
     */
    private function buildTodaySection(): array
    {
        $tasksDueToday = Task::whereDate('deadline', now()->toDateString())
            ->whereNotIn('status', [TaskStatus::Done->value])
            ->orderBySortOrder()
            ->with(['teamMember', 'taskCategory'])
            ->get();

        $overdueFollowUps = FollowUp::where(function ($query) {
            $query->overdue()->orWhere(fn ($q) => $q->dueToday());
        })
            ->with('teamMember')
            ->orderBy('follow_up_date')
            ->get();

        $bilasToday = Bila::where('is_done', false)
            ->whereDate('scheduled_date', now()->toDateString())
            ->with(['teamMember', 'prepItems'])
            ->orderBy('scheduled_date')
            ->get();

        return [
            'tasks_due_today' => $tasksDueToday,
            'overdue_follow_ups' => $overdueFollowUps,
            'bilas_today' => $bilasToday,
        ];
    }
}
