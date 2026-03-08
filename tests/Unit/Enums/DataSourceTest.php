<?php

declare(strict_types=1);

use App\Enums\ChartType;
use App\Enums\DataSource;

test('data source enum has exactly 11 cases', function () {
    $cases = DataSource::cases();

    expect($cases)->toHaveCount(11);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('TasksByStatus')
        ->toContain('TasksByPriority')
        ->toContain('TasksByCategory')
        ->toContain('TasksByGroup')
        ->toContain('TasksByMember')
        ->toContain('TasksByDeadline')
        ->toContain('FollowUpsByStatus')
        ->toContain('FollowUpsByUrgency')
        ->toContain('TasksOverTime')
        ->toContain('TaskActivity')
        ->toContain('FollowUpsOverTime');
});

test('data source case tasks by status has correct string value', function () {
    expect(DataSource::TasksByStatus->value)->toBe('tasks_by_status');
});

test('data source case tasks by priority has correct string value', function () {
    expect(DataSource::TasksByPriority->value)->toBe('tasks_by_priority');
});

test('data source case tasks by category has correct string value', function () {
    expect(DataSource::TasksByCategory->value)->toBe('tasks_by_category');
});

test('data source case tasks by group has correct string value', function () {
    expect(DataSource::TasksByGroup->value)->toBe('tasks_by_group');
});

test('data source case tasks by member has correct string value', function () {
    expect(DataSource::TasksByMember->value)->toBe('tasks_by_member');
});

test('data source case tasks by deadline has correct string value', function () {
    expect(DataSource::TasksByDeadline->value)->toBe('tasks_by_deadline');
});

test('data source case follow ups by status has correct string value', function () {
    expect(DataSource::FollowUpsByStatus->value)->toBe('follow_ups_by_status');
});

test('data source case follow ups by urgency has correct string value', function () {
    expect(DataSource::FollowUpsByUrgency->value)->toBe('follow_ups_by_urgency');
});

test('data source label returns Tasks by Status for tasks by status', function () {
    expect(DataSource::TasksByStatus->label())->toBe('Tasks by Status');
});

test('data source label returns Tasks by Priority for tasks by priority', function () {
    expect(DataSource::TasksByPriority->label())->toBe('Tasks by Priority');
});

test('data source label returns Tasks by Category for tasks by category', function () {
    expect(DataSource::TasksByCategory->label())->toBe('Tasks by Category');
});

test('data source label returns Tasks by Group for tasks by group', function () {
    expect(DataSource::TasksByGroup->label())->toBe('Tasks by Group');
});

test('data source label returns Tasks by Team Member for tasks by member', function () {
    expect(DataSource::TasksByMember->label())->toBe('Tasks by Team Member');
});

test('data source label returns Deadline Overview for tasks by deadline', function () {
    expect(DataSource::TasksByDeadline->label())->toBe('Deadline Overview');
});

test('data source label returns Follow-ups by Status for follow ups by status', function () {
    expect(DataSource::FollowUpsByStatus->label())->toBe('Follow-ups by Status');
});

test('data source label returns Follow-ups by Urgency for follow ups by urgency', function () {
    expect(DataSource::FollowUpsByUrgency->label())->toBe('Follow-ups by Urgency');
});

test('data source tasks by status allows donut bar and bar horizontal chart types', function () {
    $allowed = DataSource::TasksByStatus->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Donut)
        ->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toHaveCount(3);
});

test('data source tasks by priority allows donut bar and bar horizontal chart types', function () {
    $allowed = DataSource::TasksByPriority->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Donut)
        ->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toHaveCount(3);
});

test('data source tasks by category allows donut bar and bar horizontal chart types', function () {
    $allowed = DataSource::TasksByCategory->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Donut)
        ->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toHaveCount(3);
});

test('data source tasks by group allows donut bar and bar horizontal chart types', function () {
    $allowed = DataSource::TasksByGroup->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Donut)
        ->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toHaveCount(3);
});

test('data source follow ups by status allows donut bar and bar horizontal chart types', function () {
    $allowed = DataSource::FollowUpsByStatus->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Donut)
        ->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toHaveCount(3);
});

test('data source tasks by member allows only bar and bar horizontal chart types', function () {
    $allowed = DataSource::TasksByMember->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toHaveCount(2);
});

test('data source tasks by deadline allows bar bar horizontal and stacked bar chart types', function () {
    $allowed = DataSource::TasksByDeadline->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toContain(ChartType::StackedBar)
        ->toHaveCount(3);
});

test('data source follow ups by urgency allows bar bar horizontal and stacked bar chart types', function () {
    $allowed = DataSource::FollowUpsByUrgency->allowedChartTypes();

    expect($allowed)->toContain(ChartType::Bar)
        ->toContain(ChartType::BarHorizontal)
        ->toContain(ChartType::StackedBar)
        ->toHaveCount(3);
});

test('data source tasks by member does not allow donut chart type', function () {
    expect(DataSource::TasksByMember->allowedChartTypes())->not->toContain(ChartType::Donut);
});

test('data source tasks by deadline does not allow donut chart type', function () {
    expect(DataSource::TasksByDeadline->allowedChartTypes())->not->toContain(ChartType::Donut);
});

test('data source follow ups by urgency does not allow donut chart type', function () {
    expect(DataSource::FollowUpsByUrgency->allowedChartTypes())->not->toContain(ChartType::Donut);
});

test('data source tasks over time has correct string value', function () {
    expect(DataSource::TasksOverTime->value)->toBe('tasks_over_time');
});

test('data source task activity has correct string value', function () {
    expect(DataSource::TaskActivity->value)->toBe('task_activity');
});

test('data source follow ups over time has correct string value', function () {
    expect(DataSource::FollowUpsOverTime->value)->toBe('follow_ups_over_time');
});

test('data source tasks over time label', function () {
    expect(DataSource::TasksOverTime->label())->toBe('Tasks Over Time');
});

test('data source task activity label', function () {
    expect(DataSource::TaskActivity->label())->toBe('Task Activity');
});

test('data source follow ups over time label', function () {
    expect(DataSource::FollowUpsOverTime->label())->toBe('Follow-ups Over Time');
});

test('data source time-series sources only allow line chart type', function () {
    $timeSeriesSources = [DataSource::TasksOverTime, DataSource::TaskActivity, DataSource::FollowUpsOverTime];

    foreach ($timeSeriesSources as $source) {
        $allowed = $source->allowedChartTypes();
        expect($allowed)->toHaveCount(1)
            ->and($allowed[0])->toBe(ChartType::Line);
    }
});

test('data source isTimeSeries returns true for time-series sources', function () {
    expect(DataSource::TasksOverTime->isTimeSeries())->toBeTrue();
    expect(DataSource::TaskActivity->isTimeSeries())->toBeTrue();
    expect(DataSource::FollowUpsOverTime->isTimeSeries())->toBeTrue();
});

test('data source isTimeSeries returns false for point-in-time sources', function () {
    expect(DataSource::TasksByStatus->isTimeSeries())->toBeFalse();
    expect(DataSource::TasksByPriority->isTimeSeries())->toBeFalse();
    expect(DataSource::TasksByCategory->isTimeSeries())->toBeFalse();
});
