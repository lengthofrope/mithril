<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Available analytics data sources for dashboard widgets.
 */
enum DataSource: string
{
    case TasksByStatus = 'tasks_by_status';
    case TasksByPriority = 'tasks_by_priority';
    case TasksByCategory = 'tasks_by_category';
    case TasksByGroup = 'tasks_by_group';
    case TasksByMember = 'tasks_by_member';
    case TasksByTeam = 'tasks_by_team';
    case TasksByDeadline = 'tasks_by_deadline';
    case FollowUpsByStatus = 'follow_ups_by_status';
    case FollowUpsByUrgency = 'follow_ups_by_urgency';
    case TasksOverTime = 'tasks_over_time';
    case TaskActivity = 'task_activity';
    case FollowUpsOverTime = 'follow_ups_over_time';

    /**
     * Returns the human-readable display label for this data source.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::TasksByStatus   => 'Tasks by Status',
            self::TasksByPriority => 'Tasks by Priority',
            self::TasksByCategory => 'Tasks by Category',
            self::TasksByGroup    => 'Tasks by Group',
            self::TasksByMember   => 'Tasks by Team Member',
            self::TasksByTeam     => 'Tasks by Team',
            self::TasksByDeadline => 'Deadline Overview',
            self::FollowUpsByStatus  => 'Follow-ups by Status',
            self::FollowUpsByUrgency => 'Follow-ups by Urgency',
            self::TasksOverTime      => 'Tasks Over Time',
            self::TaskActivity       => 'Task Activity',
            self::FollowUpsOverTime  => 'Follow-ups Over Time',
        };
    }

    /**
     * Returns the chart types that are valid for this data source.
     *
     * @return ChartType[]
     */
    public function allowedChartTypes(): array
    {
        return match ($this) {
            self::TasksByStatus,
            self::TasksByPriority,
            self::TasksByCategory,
            self::TasksByGroup,
            self::TasksByTeam,
            self::FollowUpsByStatus => [
                ChartType::Donut,
                ChartType::Bar,
                ChartType::BarHorizontal,
            ],
            self::TasksByMember => [
                ChartType::Bar,
                ChartType::BarHorizontal,
            ],
            self::TasksByDeadline,
            self::FollowUpsByUrgency => [
                ChartType::Bar,
                ChartType::BarHorizontal,
                ChartType::StackedBar,
            ],
            self::TasksOverTime,
            self::TaskActivity,
            self::FollowUpsOverTime => [
                ChartType::Line,
            ],
        };
    }

    /**
     * Returns whether this data source produces time-series data.
     *
     * @return bool
     */
    public function isTimeSeries(): bool
    {
        return match ($this) {
            self::TasksOverTime,
            self::TaskActivity,
            self::FollowUpsOverTime => true,
            default => false,
        };
    }
}
