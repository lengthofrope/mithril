<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsWidget;
use App\Models\Meeting;
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
     * SQL expression for sorting tasks by priority (urgent first, null last).
     */
    private const PRIORITY_SORT_EXPRESSION = "CASE
        WHEN priority = 'urgent' THEN 0
        WHEN priority = 'high' THEN 1
        WHEN priority = 'normal' THEN 2
        WHEN priority = 'low' THEN 3
        ELSE 4
    END ASC";

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

        $isJiraConnected = $request->user()->hasJiraConnection();
        $isMicrosoftConnected = $request->user()->hasMicrosoftConnection();

        if ($isMicrosoftConnected) {
            $calendarLimit = $user->dashboard_upcoming_meetings ?? 5;
            $windowStart = now($userTz)->utc();
            $windowEnd = now($userTz)->endOfWeek()->utc();

            $timedEvents = CalendarEvent::query()
                ->with('links')
                ->timed()
                ->notEndedAt($windowStart)
                ->until($windowEnd)
                ->orderBy('start_at')
                ->limit($calendarLimit)
                ->get();

            $allDayEvents = CalendarEvent::query()
                ->with('links')
                ->allDay()
                ->notEndedAt($windowStart)
                ->until($windowEnd)
                ->orderBy('start_at')
                ->get();

            $calendarEvents = $timedEvents->concat($allDayEvents)->sortBy('start_at')->values();
        } else {
            $calendarEvents = null;
        }

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
            'todayMeetings' => $today['meetings_today'],
            'upcomingTasks' => $upcoming['tasks'],
            'upcomingFollowUps' => $upcoming['follow_ups'],
            'upcomingMeetings' => $upcoming['meetings'],
            'dashboardWidgets' => $dashboardWidgets,
            'calendarEvents' => $calendarEvents,
            'flaggedEmails' => $flaggedEmails,
            'isJiraConnected' => $isJiraConnected,
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
     * Build the today section with tasks due today, overdue follow-ups, and today's meetings.
     *
     * @param string $timezone
     * @return array<string, mixed>
     */
    private function buildTodaySection(string $timezone): array
    {
        $tasksDueToday = Task::whereDate('deadline', '<=', now($timezone)->toDateString())
            ->whereNotIn('status', [TaskStatus::Done->value])
            ->orderBy('deadline')
            ->orderByRaw(self::PRIORITY_SORT_EXPRESSION)
            ->with(['teamMember', 'taskCategory', 'team'])
            ->get();

        $overdueFollowUps = FollowUp::where(function ($query) {
            $query->overdue()->orWhere(fn ($q) => $q->dueToday());
        })
            ->with('teamMember')
            ->orderBy('follow_up_date')
            ->priorityOrdered()
            ->get();

        $meetingsToday = Meeting::where('is_done', false)
            ->whereDate('scheduled_at', now($timezone)->toDateString())
            ->with(['attendees', 'prepItems'])
            ->orderBy('scheduled_at')
            ->get();

        return [
            'tasks_due_today' => $tasksDueToday,
            'overdue_follow_ups' => $overdueFollowUps,
            'meetings_today' => $meetingsToday,
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

        $taskLimit = $user->dashboard_upcoming_tasks ?? 5;
        $followUpLimit = $user->dashboard_upcoming_follow_ups ?? 5;
        $meetingLimit = $user->dashboard_upcoming_meetings ?? 5;

        $upcomingTasks = $taskLimit > 0
            ? Task::whereDate('deadline', '>', $todayDate)
                ->whereNotIn('status', [TaskStatus::Done->value])
                ->orderBy('deadline')
                ->orderByRaw(self::PRIORITY_SORT_EXPRESSION)
                ->with(['teamMember', 'taskCategory', 'team'])
                ->limit($taskLimit)
                ->get()
            : new Collection();

        $upcomingFollowUps = $followUpLimit > 0
            ? FollowUp::whereDate('follow_up_date', '>', $todayDate)
                ->where('status', '!=', FollowUpStatus::Done->value)
                ->with('teamMember')
                ->orderBy('follow_up_date')
                ->priorityOrdered()
                ->limit($followUpLimit)
                ->get()
            : new Collection();

        $upcomingMeetings = $meetingLimit > 0
            ? Meeting::where('is_done', false)
                ->whereDate('scheduled_at', '>', $todayDate)
                ->with(['attendees', 'prepItems'])
                ->orderBy('scheduled_at')
                ->limit($meetingLimit)
                ->get()
            : new Collection();

        return [
            'tasks' => $upcomingTasks,
            'follow_ups' => $upcomingFollowUps,
            'meetings' => $upcomingMeetings,
        ];
    }
}
