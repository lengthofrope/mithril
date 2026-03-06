<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\TaskStatus;
use App\Enums\FollowUpStatus;
use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\WeeklyReflection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Handles the weekly reflection page rendering.
 *
 * Auto-generates a summary for the current week and loads past reflections.
 */
class WeeklyReflectionController extends Controller
{
    /**
     * Display the weekly reflection index with the current week's summary and past entries.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $currentReflection = WeeklyReflection::firstOrCreate(
            ['week_start' => $weekStart->toDateString()],
            ['week_end' => $weekEnd->toDateString()]
        );

        $summaryData = $this->buildWeeklySummary($weekStart, $weekEnd);

        $pastReflections = WeeklyReflection::query()
            ->whereDate('week_start', '<', $weekStart->toDateString())
            ->orderByDesc('week_start')
            ->get();

        return view('pages.weekly.index', [
            'title' => 'Weekly Review',
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'currentReflection' => $currentReflection,
            'weekStats' => [
                'tasks_completed' => $summaryData['completed_tasks_count'],
                'tasks_open' => $summaryData['open_tasks_count'],
                'follow_ups_handled' => $summaryData['handled_follow_ups_count'],
            ],
            'pastReflections' => $pastReflections,
        ]);
    }

    /**
     * Build an auto-generated summary of this week's activity.
     *
     * @param Carbon $weekStart
     * @param Carbon $weekEnd
     * @return array<string, mixed>
     */
    private function buildWeeklySummary(Carbon $weekStart, Carbon $weekEnd): array
    {
        $completedTasks = Task::query()
            ->where('status', TaskStatus::Done->value)
            ->whereBetween('updated_at', [$weekStart, $weekEnd])
            ->with(['taskCategory', 'teamMember'])
            ->get();

        $openTasks = Task::query()
            ->whereNotIn('status', [TaskStatus::Done->value])
            ->get();

        $handledFollowUps = FollowUp::query()
            ->where('status', FollowUpStatus::Done->value)
            ->whereBetween('updated_at', [$weekStart, $weekEnd])
            ->with('teamMember')
            ->get();

        $openFollowUps = FollowUp::query()
            ->whereNot('status', FollowUpStatus::Done->value)
            ->get();

        return [
            'completed_tasks' => $completedTasks,
            'completed_tasks_count' => $completedTasks->count(),
            'open_tasks_count' => $openTasks->count(),
            'handled_follow_ups' => $handledFollowUps,
            'handled_follow_ups_count' => $handledFollowUps->count(),
            'open_follow_ups_count' => $openFollowUps->count(),
        ];
    }
}
