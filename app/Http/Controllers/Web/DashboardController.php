<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsWidget;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
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
     * @return View
     */
    public function index(Request $request): View
    {
        $greeting = $this->resolveGreeting();
        $counters = $this->buildStats();
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
     * Gather counter statistics for the dashboard header.
     *
     * @return array<string, int>
     */
    private function buildStats(): array
    {
        $openTaskCount = Task::whereNotIn('status', [TaskStatus::Done->value])->count();
        $urgentTaskCount = Task::where('priority', Priority::Urgent->value)
            ->whereNotIn('status', [TaskStatus::Done->value])
            ->count();
        $overdueFollowUpCount = FollowUp::overdue()->count();
        $todayFollowUpCount = FollowUp::dueToday()->count();
        $upcomingBilaCount = Bila::whereBetween(
            'scheduled_date',
            [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]
        )->count();

        return [
            'open_tasks' => $openTaskCount,
            'urgent_tasks' => $urgentTaskCount,
            'overdue_follow_ups' => $overdueFollowUpCount,
            'today_follow_ups' => $todayFollowUpCount,
            'bilas_this_week' => $upcomingBilaCount,
        ];
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

        $overdueFollowUps = FollowUp::overdue()
            ->with('teamMember')
            ->orderBy('follow_up_date')
            ->get();

        $bilasToday = Bila::whereDate('scheduled_date', now()->toDateString())
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
