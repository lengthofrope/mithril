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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Handles weekly reflection CRUD and auto-generated summaries.
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
            ['week_start' => $weekStart],
            ['week_end' => $weekEnd]
        );

        $summaryData = $this->buildWeeklySummary($weekStart, $weekEnd);

        $currentReflection->update([
            'summary' => $this->generateSummaryText($summaryData),
        ]);

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
     * Store a new weekly reflection for a past week.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'week_start' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        WeeklyReflection::firstOrCreate(
            ['week_start' => $weekStart],
            ['week_end' => $weekEnd]
        );

        return redirect()->route('weekly.index');
    }

    /**
     * Update the reflection text on a weekly reflection record.
     *
     * @param Request $request
     * @param WeeklyReflection $weeklyReflection
     * @return JsonResponse
     */
    public function update(Request $request, WeeklyReflection $weeklyReflection): JsonResponse
    {
        $validated = $request->validate([
            'reflection' => ['sometimes', 'nullable', 'string'],
            'summary'    => ['sometimes', 'nullable', 'string'],
        ]);

        $weeklyReflection->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a weekly reflection.
     *
     * @param Request $request
     * @param WeeklyReflection $weeklyReflection
     * @return JsonResponse|RedirectResponse
     */
    public function destroy(Request $request, WeeklyReflection $weeklyReflection): JsonResponse|RedirectResponse
    {
        $weeklyReflection->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('weekly.index');
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

    /**
     * Generate a markdown summary text from the week's activity data.
     *
     * @param array<string, mixed> $summaryData
     * @return string
     */
    private function generateSummaryText(array $summaryData): string
    {
        $lines = [];

        $completed = $summaryData['completed_tasks_count'];
        $open = $summaryData['open_tasks_count'];
        $followUps = $summaryData['handled_follow_ups_count'];
        $openFollowUps = $summaryData['open_follow_ups_count'];

        $lines[] = "**{$completed}** " . ($completed === 1 ? 'task' : 'tasks') . " completed this week.";
        $lines[] = "**{$open}** " . ($open === 1 ? 'task' : 'tasks') . " still open.";
        $lines[] = "**{$followUps}** follow-" . ($followUps === 1 ? 'up' : 'ups') . " handled.";

        if ($openFollowUps > 0) {
            $lines[] = "**{$openFollowUps}** follow-" . ($openFollowUps === 1 ? 'up' : 'ups') . " still pending.";
        }

        if ($summaryData['completed_tasks']->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '### Completed';

            foreach ($summaryData['completed_tasks'] as $task) {
                $label = $task->title;

                if ($task->teamMember) {
                    $label .= " ({$task->teamMember->name})";
                }

                $lines[] = "- {$label}";
            }
        }

        if ($summaryData['handled_follow_ups']->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '### Follow-ups handled';

            foreach ($summaryData['handled_follow_ups'] as $followUp) {
                $label = $followUp->subject;

                if ($followUp->teamMember) {
                    $label .= " ({$followUp->teamMember->name})";
                }

                $lines[] = "- {$label}";
            }
        }

        return implode("\n", $lines);
    }
}
