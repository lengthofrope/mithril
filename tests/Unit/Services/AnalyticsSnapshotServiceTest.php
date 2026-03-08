<?php

declare(strict_types=1);

use App\DataTransferObjects\TimeSeriesChartData;
use App\Models\AnalyticsSnapshot;
use App\Models\User;
use App\Services\AnalyticsSnapshotService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-08 12:00:00'));

    $this->user    = User::factory()->create();
    $this->service = new AnalyticsSnapshotService();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('AnalyticsSnapshotService', function (): void {
    describe('tasksOverTime', function (): void {
        it('returns a TimeSeriesChartData instance', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, '7d');

            expect($result)->toBeInstanceOf(TimeSeriesChartData::class);
        });

        it('returns 4 series for Open, In Progress, Waiting, Done', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, '7d');

            expect($result->series)->toHaveCount(4);
            $names = array_column($result->series, 'name');
            expect($names)->toBe(['Open', 'In Progress', 'Waiting', 'Done']);
        });

        it('returns 4 colors', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, '7d');

            expect($result->colors)->toHaveCount(4);
        });

        it('returns 7 labels for 7d range', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, '7d');

            expect($result->labels)->toHaveCount(7);
            expect($result->labels[0])->toBe('2026-03-02');
            expect($result->labels[6])->toBe('2026-03-08');
        });

        it('returns 30 labels for 30d range', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, '30d');

            expect($result->labels)->toHaveCount(30);
        });

        it('returns 90 labels for 90d range', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, '90d');

            expect($result->labels)->toHaveCount(90);
        });

        it('fills snapshot data into the correct series positions', function (): void {
            AnalyticsSnapshot::factory()->create([
                'user_id'       => $this->user->id,
                'snapshot_date' => '2026-03-05',
                'metric'        => 'tasks_status_open',
                'value'         => 10,
            ]);
            AnalyticsSnapshot::factory()->create([
                'user_id'       => $this->user->id,
                'snapshot_date' => '2026-03-05',
                'metric'        => 'tasks_status_done',
                'value'         => 3,
            ]);

            $result = $this->service->tasksOverTime($this->user->id, '7d');

            $openSeries = $result->series[0]['data'];
            $doneSeries = $result->series[3]['data'];

            $dateIndex = array_search('2026-03-05', $result->labels);
            expect($openSeries[$dateIndex])->toBe(10);
            expect($doneSeries[$dateIndex])->toBe(3);
        });

        it('defaults to zero for dates without snapshots', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, '7d');

            foreach ($result->series as $series) {
                foreach ($series['data'] as $value) {
                    expect($value)->toBe(0);
                }
            }
        });

        it('only includes snapshots for the requested user', function (): void {
            $otherUser = User::factory()->create();
            AnalyticsSnapshot::factory()->create([
                'user_id'       => $otherUser->id,
                'snapshot_date' => '2026-03-05',
                'metric'        => 'tasks_status_open',
                'value'         => 99,
            ]);

            $result = $this->service->tasksOverTime($this->user->id, '7d');

            $openSeries = $result->series[0]['data'];
            expect(array_sum($openSeries))->toBe(0);
        });
    });

    describe('taskActivity', function (): void {
        it('returns a TimeSeriesChartData instance', function (): void {
            $result = $this->service->taskActivity($this->user->id, '7d');

            expect($result)->toBeInstanceOf(TimeSeriesChartData::class);
        });

        it('returns 2 series for Created and Completed', function (): void {
            $result = $this->service->taskActivity($this->user->id, '7d');

            expect($result->series)->toHaveCount(2);
            $names = array_column($result->series, 'name');
            expect($names)->toBe(['Created', 'Completed']);
        });

        it('computes daily deltas from absolute totals', function (): void {
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-04', 'metric' => 'tasks_total', 'value' => 10]);
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-05', 'metric' => 'tasks_total', 'value' => 13]);
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-04', 'metric' => 'tasks_status_done', 'value' => 2]);
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-05', 'metric' => 'tasks_status_done', 'value' => 4]);

            $result = $this->service->taskActivity($this->user->id, '7d');

            $createdSeries   = $result->series[0]['data'];
            $completedSeries = $result->series[1]['data'];

            $dayIndex4 = array_search('2026-03-04', $result->labels);
            $dayIndex5 = array_search('2026-03-05', $result->labels);

            expect($createdSeries[$dayIndex5])->toBe(3);
            expect($completedSeries[$dayIndex5])->toBe(2);
        });

        it('clamps negative deltas to zero', function (): void {
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-04', 'metric' => 'tasks_total', 'value' => 10]);
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-05', 'metric' => 'tasks_total', 'value' => 8]);
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-06', 'metric' => 'tasks_total', 'value' => 12]);

            $result = $this->service->taskActivity($this->user->id, '7d');

            $createdSeries = $result->series[0]['data'];
            $dayIndex5     = array_search('2026-03-05', $result->labels);

            expect($dayIndex5)->not->toBeFalse();
            expect($createdSeries[$dayIndex5])->toBe(0);
        });

        it('returns zero as first day delta', function (): void {
            AnalyticsSnapshot::factory()->create(['user_id' => $this->user->id, 'snapshot_date' => '2026-03-02', 'metric' => 'tasks_total', 'value' => 5]);

            $result = $this->service->taskActivity($this->user->id, '7d');

            $createdSeries = $result->series[0]['data'];
            expect($createdSeries[0])->toBe(0);
        });
    });

    describe('followUpsOverTime', function (): void {
        it('returns a TimeSeriesChartData instance', function (): void {
            $result = $this->service->followUpsOverTime($this->user->id, '7d');

            expect($result)->toBeInstanceOf(TimeSeriesChartData::class);
        });

        it('returns 3 series for Open, Snoozed, Done', function (): void {
            $result = $this->service->followUpsOverTime($this->user->id, '7d');

            expect($result->series)->toHaveCount(3);
            $names = array_column($result->series, 'name');
            expect($names)->toBe(['Open', 'Snoozed', 'Done']);
        });

        it('returns 3 colors', function (): void {
            $result = $this->service->followUpsOverTime($this->user->id, '7d');

            expect($result->colors)->toHaveCount(3);
        });

        it('fills snapshot data into the correct series positions', function (): void {
            AnalyticsSnapshot::factory()->create([
                'user_id'       => $this->user->id,
                'snapshot_date' => '2026-03-06',
                'metric'        => 'follow_ups_status_open',
                'value'         => 7,
            ]);

            $result = $this->service->followUpsOverTime($this->user->id, '7d');

            $openSeries = $result->series[0]['data'];
            $dateIndex  = array_search('2026-03-06', $result->labels);
            expect($openSeries[$dateIndex])->toBe(7);
        });
    });

    describe('time range handling', function (): void {
        it('defaults to 30d when given an unknown time range key', function (): void {
            $result = $this->service->tasksOverTime($this->user->id, 'invalid');

            expect($result->labels)->toHaveCount(30);
        });
    });
});
