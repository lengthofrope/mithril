<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsWidget;
use App\Models\Bila;
use App\Models\CalendarEvent;
use App\Models\Email;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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
        $user = $request->user();
        $userTz = $user->getEffectiveTimezone();
        $greeting = $this->resolveGreeting($userTz);
        $counters = $statsService->buildStats();
        $today = $this->buildTodaySection($userTz);
        $upcoming = $this->buildUpcomingSection($userTz, $user);
        $dashboardWidgets = AnalyticsWidget::forDashboard()->get();

        $isMicrosoftConnected = $request->user()->hasMicrosoftConnection();

        $calendarEvents = $isMicrosoftConnected
            ? CalendarEvent::query()
                ->with('links')
                ->notEndedAt(now($userTz)->utc())
                ->until(now($userTz)->endOfWeek()->utc())
                ->orderBy('start_at')
                ->limit(3)
                ->get()
            : null;

        $flaggedEmails = $isMicrosoftConnected
            ? Email::query()
                ->with('emailLinks')
                ->where('is_flagged', true)
                ->orderByRaw('flag_due_date IS NULL, flag_due_date ASC')
                ->get()
            : null;

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();
        $allCategories = TaskCategory::all();
        $allGroups = TaskGroup::orderBySortOrder()->get();

        return view('pages.dashboard', [
            'title' => 'Dashboard',
            'greeting' => $greeting,
            'counters' => $counters,
            'todayTasks' => $today['tasks_due_today'],
            'todayFollowUps' => $today['overdue_follow_ups'],
            'todayBilas' => $today['bilas_today'],
            'upcomingTasks' => $upcoming['tasks'],
            'upcomingFollowUps' => $upcoming['follow_ups'],
            'upcomingBilas' => $upcoming['bilas'],
            'dashboardWidgets' => $dashboardWidgets,
            'calendarEvents' => $calendarEvents,
            'flaggedEmails' => $flaggedEmails,
            'isMicrosoftConnected' => $isMicrosoftConnected,
            'userTimezone' => $userTz,
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name, 'team_id' => $m->team_id])->all(),
            'categoryOptions' => $allCategories->map(fn (TaskCategory $c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'groups' => $allGroups,
        ]);
    }

    /**
     * Build a time-based greeting string.
     *
     * @param string $timezone
     * @return string
     */
    private function resolveGreeting(string $timezone): string
    {
        $hour = (int) now($timezone)->format('H');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }

    /**
     * Build the today section with tasks due today, overdue follow-ups, and today's bilas.
     *
     * @param string $timezone
     * @return array<string, mixed>
     */
    private function buildTodaySection(string $timezone): array
    {
        $tasksDueToday = Task::whereDate('deadline', now($timezone)->toDateString())
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
            ->whereDate('scheduled_date', now($timezone)->toDateString())
            ->with(['teamMember', 'prepItems'])
            ->orderBy('scheduled_date')
            ->get();

        return [
            'tasks_due_today' => $tasksDueToday,
            'overdue_follow_ups' => $overdueFollowUps,
            'bilas_today' => $bilasToday,
        ];
    }

    /**
     * Build the upcoming section with future items based on user preferences.
     *
     * @param string $timezone
     * @param User $user
     * @return array<string, Collection>
     */
    private function buildUpcomingSection(string $timezone, User $user): array
    {
        $todayDate = now($timezone)->toDateString();

        $upcomingTasks = $user->dashboard_upcoming_tasks
            ? Task::whereDate('deadline', '>', $todayDate)
                ->whereNotIn('status', [TaskStatus::Done->value])
                ->orderBy('deadline')
                ->with(['teamMember', 'taskCategory'])
                ->limit($user->dashboard_upcoming_tasks)
                ->get()
            : new Collection();

        $upcomingFollowUps = $user->dashboard_upcoming_follow_ups
            ? FollowUp::whereDate('follow_up_date', '>', $todayDate)
                ->where('status', '!=', FollowUpStatus::Done->value)
                ->with('teamMember')
                ->orderBy('follow_up_date')
                ->limit($user->dashboard_upcoming_follow_ups)
                ->get()
            : new Collection();

        $upcomingBilas = $user->dashboard_upcoming_bilas
            ? Bila::where('is_done', false)
                ->whereDate('scheduled_date', '>', $todayDate)
                ->with(['teamMember', 'prepItems'])
                ->orderBy('scheduled_date')
                ->limit($user->dashboard_upcoming_bilas)
                ->get()
            : new Collection();

        return [
            'tasks' => $upcomingTasks,
            'follow_ups' => $upcomingFollowUps,
            'bilas' => $upcomingBilas,
        ];
    }
}
