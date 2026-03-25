<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\TaskStatus;
use App\Enums\FollowUpStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\WeeklyReflection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Handles weekly reflection CRUD, auto-generated summaries, and chart data.
 */
class WeeklyReflectionController extends Controller
{
    /**
     * Colour palette consistent with the analytics dashboard.
     *
     * @var list<string>
     */
    private const PALETTE = [
        '#22c55e', // green  — completed / done
        '#f59e0b', // amber  — open / pending
        '#3b82f6', // blue   — follow-ups
        '#a855f7', // purple — meetings
        '#14b8a6', // teal   — notes
    ];

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
                'meetings_held' => $summaryData['meetings_held_count'],
                'notes_written' => $summaryData['notes_written_count'],
            ],
            'chartData' => $this->buildChartData($summaryData),
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
     * Build weekly activity data including tasks, follow-ups, meetings, and notes.
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

        $meetingsHeld = Meeting::query()
            ->where('is_done', true)
            ->whereBetween('updated_at', [$weekStart, $weekEnd])
            ->with('attendees')
            ->get();

        $notesWritten = Note::query()
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();

        return [
            'completed_tasks' => $completedTasks,
            'completed_tasks_count' => $completedTasks->count(),
            'open_tasks_count' => $openTasks->count(),
            'handled_follow_ups' => $handledFollowUps,
            'handled_follow_ups_count' => $handledFollowUps->count(),
            'open_follow_ups_count' => $openFollowUps->count(),
            'meetings_held' => $meetingsHeld,
            'meetings_held_count' => $meetingsHeld->count(),
            'notes_written_count' => $notesWritten->count(),
        ];
    }

    /**
     * Build chart data arrays for the donut and horizontal bar charts.
     *
     * @param array<string, mixed> $summaryData
     * @return array<string, array<string, mixed>>
     */
    private function buildChartData(array $summaryData): array
    {
        return [
            'donut' => [
                'labels' => ['Completed', 'Open'],
                'series' => [
                    $summaryData['completed_tasks_count'],
                    $summaryData['open_tasks_count'],
                ],
                'colors' => [self::PALETTE[0], self::PALETTE[1]],
            ],
            'bar' => [
                'labels' => ['Tasks done', 'Follow-ups', 'Meetings', 'Notes'],
                'series' => [
                    $summaryData['completed_tasks_count'],
                    $summaryData['handled_follow_ups_count'],
                    $summaryData['meetings_held_count'],
                    $summaryData['notes_written_count'],
                ],
                'colors' => [
                    self::PALETTE[0],
                    self::PALETTE[2],
                    self::PALETTE[3],
                    self::PALETTE[4],
                ],
            ],
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
        $meetings = $summaryData['meetings_held_count'];
        $notes = $summaryData['notes_written_count'];

        $lines[] = "**{$completed}** " . ($completed === 1 ? 'task' : 'tasks') . " completed this week.";
        $lines[] = "**{$open}** " . ($open === 1 ? 'task' : 'tasks') . " still open.";
        $lines[] = "**{$followUps}** follow-" . ($followUps === 1 ? 'up' : 'ups') . " handled.";

        if ($openFollowUps > 0) {
            $lines[] = "**{$openFollowUps}** follow-" . ($openFollowUps === 1 ? 'up' : 'ups') . " still pending.";
        }

        if ($meetings > 0) {
            $lines[] = "**{$meetings}** " . ($meetings === 1 ? 'meeting' : 'meetings') . " held.";
        }

        if ($notes > 0) {
            $lines[] = "**{$notes}** " . ($notes === 1 ? 'note' : 'notes') . " written.";
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
                $label = $followUp->description;

                if ($followUp->teamMember) {
                    $label .= " ({$followUp->teamMember->name})";
                }

                $lines[] = "- {$label}";
            }
        }

        if ($summaryData['meetings_held']->isNotEmpty()) {
            $lines[] = '';
            $lines[] = '### Meetings held';

            foreach ($summaryData['meetings_held'] as $meeting) {
                $attendee = $meeting->attendees->first();
                $label = $attendee ? $attendee->name : $meeting->title;

                $lines[] = "- {$label}";
            }
        }

        return implode("\n", $lines);
    }
}
