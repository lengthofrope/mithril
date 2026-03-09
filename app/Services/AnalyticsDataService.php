<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ChartData;
use App\Enums\DataSource;
use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;

/**
 * Aggregates chart data for each analytics data source.
 *
 * All queries are automatically scoped to the authenticated user via the
 * BelongsToUser global scope present on every model.
 */
class AnalyticsDataService
{
    /**
     * Colour palette shared by category, member, and other multi-series sources.
     *
     * @var list<string>
     */
    private const PALETTE = [
        '#3b82f6',
        '#14b8a6',
        '#6366f1',
        '#ec4899',
        '#f59e0b',
        '#10b981',
        '#8b5cf6',
        '#06b6d4',
    ];

    /**
     * Dispatch the data aggregation to the method matching the given source.
     *
     * @param DataSource $source The analytics data source to resolve.
     * @return ChartData Aggregated labels, series values, and colours.
     */
    public function resolve(DataSource $source): ChartData
    {
        if ($source->isTimeSeries()) {
            throw new \InvalidArgumentException(
                "Time-series source \"{$source->value}\" must be resolved via AnalyticsSnapshotService."
            );
        }

        return match ($source) {
            DataSource::TasksByStatus    => $this->tasksByStatus(),
            DataSource::TasksByPriority  => $this->tasksByPriority(),
            DataSource::TasksByCategory  => $this->tasksByCategory(),
            DataSource::TasksByGroup     => $this->tasksByGroup(),
            DataSource::TasksByMember    => $this->tasksByMember(),
            DataSource::TasksByTeam      => $this->tasksByTeam(),
            DataSource::TasksByDeadline  => $this->tasksByDeadline(),
            DataSource::FollowUpsByStatus  => $this->followUpsByStatus(),
            DataSource::FollowUpsByUrgency => $this->followUpsByUrgency(),
            default => throw new \InvalidArgumentException("Unhandled source: {$source->value}"),
        };
    }

    /**
     * Aggregate all tasks grouped by their status.
     *
     * @return ChartData Labels: Open, In Progress, Waiting, Done.
     */
    private function tasksByStatus(): ChartData
    {
        $counts = Task::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = [
            TaskStatus::Open->value       => ['label' => 'Open',        'color' => '#3b82f6'],
            TaskStatus::InProgress->value => ['label' => 'In Progress',  'color' => '#f59e0b'],
            TaskStatus::Waiting->value    => ['label' => 'Waiting',      'color' => '#a855f7'],
            TaskStatus::Done->value       => ['label' => 'Done',         'color' => '#22c55e'],
        ];

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($statuses as $value => $meta) {
            $labels[] = $meta['label'];
            $series[] = (int) ($counts[$value] ?? 0);
            $colors[] = $meta['color'];
        }

        return new ChartData(labels: $labels, series: $series, colors: $colors);
    }

    /**
     * Aggregate non-done tasks grouped by their priority.
     *
     * @return ChartData Labels: Urgent, High, Normal, Low.
     */
    private function tasksByPriority(): ChartData
    {
        $counts = Task::query()
            ->selectRaw('priority, COUNT(*) as total')
            ->where('status', '!=', TaskStatus::Done->value)
            ->groupBy('priority')
            ->pluck('total', 'priority');

        $priorities = [
            Priority::Urgent->value => ['label' => 'Urgent', 'color' => '#ef4444'],
            Priority::High->value   => ['label' => 'High',   'color' => '#f97316'],
            Priority::Normal->value => ['label' => 'Normal', 'color' => '#3b82f6'],
            Priority::Low->value    => ['label' => 'Low',    'color' => '#9ca3af'],
        ];

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($priorities as $value => $meta) {
            $labels[] = $meta['label'];
            $series[] = (int) ($counts[$value] ?? 0);
            $colors[] = $meta['color'];
        }

        return new ChartData(labels: $labels, series: $series, colors: $colors);
    }

    /**
     * Aggregate non-done tasks grouped by their category.
     *
     * Tasks without a category are grouped under "Uncategorized".
     *
     * @return ChartData Labels derived from category names plus a fallback.
     */
    private function tasksByCategory(): ChartData
    {
        $rows = Task::query()
            ->selectRaw('task_category_id, COUNT(*) as total')
            ->where('status', '!=', TaskStatus::Done->value)
            ->groupBy('task_category_id')
            ->get();

        $categoryNames = TaskCategory::query()
            ->pluck('name', 'id');

        $labels = [];
        $series = [];
        $colors = [];
        $paletteCount = count(self::PALETTE);

        foreach ($rows as $index => $row) {
            $label  = $row->task_category_id !== null
                ? ($categoryNames[$row->task_category_id] ?? 'Uncategorized')
                : 'Uncategorized';

            $labels[] = $label;
            $series[] = (int) $row->total;
            $colors[] = self::PALETTE[$index % $paletteCount];
        }

        return new ChartData(labels: $labels, series: $series, colors: $colors);
    }

    /**
     * Aggregate non-done tasks grouped by their group.
     *
     * Tasks without a group are labeled "Ungrouped" with color #9ca3af.
     * Group colors are taken from the task_groups.color column.
     *
     * @return ChartData Labels derived from group names plus a fallback.
     */
    private function tasksByGroup(): ChartData
    {
        $rows = Task::query()
            ->selectRaw('task_group_id, COUNT(*) as total')
            ->where('status', '!=', TaskStatus::Done->value)
            ->groupBy('task_group_id')
            ->get();

        $groups = TaskGroup::query()
            ->get(['id', 'name', 'color'])
            ->keyBy('id');

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($rows as $row) {
            if ($row->task_group_id !== null && isset($groups[$row->task_group_id])) {
                $group    = $groups[$row->task_group_id];
                $labels[] = $group->name;
                $colors[] = $group->color ?? '#9ca3af';
            } else {
                $labels[] = 'Ungrouped';
                $colors[] = '#9ca3af';
            }

            $series[] = (int) $row->total;
        }

        return new ChartData(labels: $labels, series: $series, colors: $colors);
    }

    /**
     * Aggregate non-done tasks grouped by assigned team member.
     *
     * Tasks without a team member are labeled "Unassigned".
     *
     * @return ChartData Labels derived from team member names plus a fallback.
     */
    private function tasksByMember(): ChartData
    {
        $rows = Task::query()
            ->selectRaw('team_member_id, COUNT(*) as total')
            ->where('status', '!=', TaskStatus::Done->value)
            ->groupBy('team_member_id')
            ->get();

        $members = TeamMember::query()
            ->pluck('name', 'id');

        $labels = [];
        $series = [];
        $colors = [];
        $paletteCount = count(self::PALETTE);

        foreach ($rows as $index => $row) {
            $label = $row->team_member_id !== null
                ? ($members[$row->team_member_id] ?? 'Unassigned')
                : 'Unassigned';

            $labels[] = $label;
            $series[] = (int) $row->total;
            $colors[] = self::PALETTE[$index % $paletteCount];
        }

        return new ChartData(labels: $labels, series: $series, colors: $colors);
    }

    /**
     * Aggregate non-done tasks grouped by team.
     *
     * A task belongs to a team when it is directly assigned via team_id, or
     * when it is assigned to a team member belonging to that team. Tasks
     * matching both conditions on the same team are counted only once.
     * Tasks without a team or team member are labeled "Unassigned".
     *
     * @return ChartData Labels derived from team names plus a fallback.
     */
    private function tasksByTeam(): ChartData
    {
        $tasks = Task::query()
            ->where('status', '!=', TaskStatus::Done->value)
            ->get(['id', 'team_id', 'team_member_id']);

        $teams   = Team::query()->get(['id', 'name', 'color'])->keyBy('id');
        $members = TeamMember::query()->pluck('team_id', 'id');

        $counts = [];
        $unassigned = 0;

        foreach ($tasks as $task) {
            $teamId = $task->team_id;

            if ($teamId === null && $task->team_member_id !== null) {
                $teamId = $members[$task->team_member_id] ?? null;
            }

            if ($teamId === null) {
                $unassigned++;
            } else {
                $counts[$teamId] = ($counts[$teamId] ?? 0) + 1;
            }
        }

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($counts as $teamId => $count) {
            if (isset($teams[$teamId])) {
                $labels[] = $teams[$teamId]->name;
                $colors[] = $teams[$teamId]->color ?? '#9ca3af';
            } else {
                $labels[] = 'Unknown Team';
                $colors[] = '#9ca3af';
            }

            $series[] = $count;
        }

        if ($unassigned > 0) {
            $labels[] = 'Unassigned';
            $series[] = $unassigned;
            $colors[] = '#9ca3af';
        }

        return new ChartData(labels: $labels, series: $series, colors: $colors);
    }

    /**
     * Aggregate non-done tasks into deadline buckets.
     *
     * Buckets: Overdue, Today, This Week, Next Week, Later, No Deadline.
     *
     * @return ChartData Six fixed buckets with counts.
     */
    private function tasksByDeadline(): ChartData
    {
        $today         = now()->startOfDay();
        $endOfWeek     = now()->endOfWeek();
        $startNextWeek = now()->startOfWeek()->addWeek();
        $endNextWeek   = now()->endOfWeek()->addWeek();

        $base = Task::query()->where('status', '!=', TaskStatus::Done->value);

        $overdue  = (clone $base)->whereNotNull('deadline')->whereDate('deadline', '<', $today)->count();
        $dueToday = (clone $base)->whereNotNull('deadline')->whereDate('deadline', $today)->count();
        $thisWeek = (clone $base)->whereNotNull('deadline')
            ->whereDate('deadline', '>', $today)
            ->whereDate('deadline', '<=', $endOfWeek)
            ->count();
        $nextWeek = (clone $base)->whereNotNull('deadline')
            ->whereDate('deadline', '>=', $startNextWeek)
            ->whereDate('deadline', '<=', $endNextWeek)
            ->count();
        $later      = (clone $base)->whereNotNull('deadline')->whereDate('deadline', '>', $endNextWeek)->count();
        $noDeadline = (clone $base)->whereNull('deadline')->count();

        return new ChartData(
            labels: ['Overdue', 'Today', 'This Week', 'Next Week', 'Later', 'No Deadline'],
            series: [$overdue, $dueToday, $thisWeek, $nextWeek, $later, $noDeadline],
            colors: ['#ef4444', '#f97316', '#f59e0b', '#3b82f6', '#9ca3af', '#d1d5db'],
        );
    }

    /**
     * Aggregate all follow-ups grouped by their status.
     *
     * @return ChartData Labels: Open, Snoozed, Done.
     */
    private function followUpsByStatus(): ChartData
    {
        $counts = FollowUp::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = [
            FollowUpStatus::Open->value    => ['label' => 'Open',    'color' => '#3b82f6'],
            FollowUpStatus::Snoozed->value => ['label' => 'Snoozed', 'color' => '#f59e0b'],
            FollowUpStatus::Done->value    => ['label' => 'Done',     'color' => '#22c55e'],
        ];

        $labels = [];
        $series = [];
        $colors = [];

        foreach ($statuses as $value => $meta) {
            $labels[] = $meta['label'];
            $series[] = (int) ($counts[$value] ?? 0);
            $colors[] = $meta['color'];
        }

        return new ChartData(labels: $labels, series: $series, colors: $colors);
    }

    /**
     * Aggregate non-done follow-ups into urgency buckets.
     *
     * Buckets: Overdue, Today, This Week, Later.
     *
     * @return ChartData Four fixed urgency buckets with counts.
     */
    private function followUpsByUrgency(): ChartData
    {
        $today     = now()->startOfDay();
        $endOfWeek = now()->endOfWeek();

        $base = FollowUp::query()->where('status', '!=', FollowUpStatus::Done->value);

        $overdue  = (clone $base)->whereDate('follow_up_date', '<', $today)->count();
        $dueToday = (clone $base)->whereDate('follow_up_date', $today)->count();
        $thisWeek = (clone $base)
            ->whereDate('follow_up_date', '>', $today)
            ->whereDate('follow_up_date', '<=', $endOfWeek)
            ->count();
        $later = (clone $base)->whereDate('follow_up_date', '>', $endOfWeek)->count();

        return new ChartData(
            labels: ['Overdue', 'Today', 'This Week', 'Later'],
            series: [$overdue, $dueToday, $thisWeek, $later],
            colors: ['#ef4444', '#f97316', '#f59e0b', '#9ca3af'],
        );
    }
}
