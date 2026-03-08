<?php

declare(strict_types=1);

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
use App\Models\User;
use App\Services\AnalyticsDataService;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-08 12:00:00'));

    $this->user    = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = new AnalyticsDataService();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('AnalyticsDataService', function (): void {
    describe('resolve dispatcher', function (): void {
        it('returns ChartData for every DataSource case', function (): void {
            foreach (DataSource::cases() as $source) {
                $result = $this->service->resolve($source);
                expect($result)->toBeInstanceOf(ChartData::class);
            }
        });
    });

    describe('tasksByStatus', function (): void {
        it('returns all four status labels', function (): void {
            $result = $this->service->resolve(DataSource::TasksByStatus);

            expect($result->labels)->toBe(['Open', 'In Progress', 'Waiting', 'Done']);
        });

        it('counts tasks per status correctly', function (): void {
            Task::factory()->count(2)->create(['user_id' => $this->user->id, 'status' => TaskStatus::Open]);
            Task::factory()->create(['user_id' => $this->user->id, 'status' => TaskStatus::InProgress]);
            Task::factory()->create(['user_id' => $this->user->id, 'status' => TaskStatus::Done]);

            $result = $this->service->resolve(DataSource::TasksByStatus);

            expect($result->series)->toBe([2, 1, 0, 1]);
        });

        it('returns zero for statuses with no tasks', function (): void {
            $result = $this->service->resolve(DataSource::TasksByStatus);

            expect($result->series)->toBe([0, 0, 0, 0]);
        });

        it('always returns exactly 4 colors', function (): void {
            $result = $this->service->resolve(DataSource::TasksByStatus);

            expect($result->colors)->toHaveCount(4);
        });
    });

    describe('tasksByPriority', function (): void {
        it('returns all four priority labels', function (): void {
            $result = $this->service->resolve(DataSource::TasksByPriority);

            expect($result->labels)->toBe(['Urgent', 'High', 'Normal', 'Low']);
        });

        it('excludes done tasks from counts', function (): void {
            Task::factory()->create(['user_id' => $this->user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Open]);
            Task::factory()->create(['user_id' => $this->user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Done]);

            $result = $this->service->resolve(DataSource::TasksByPriority);

            $urgentIndex = array_search('Urgent', $result->labels);
            expect($result->series[$urgentIndex])->toBe(1);
        });

        it('counts tasks per priority correctly', function (): void {
            Task::factory()->count(2)->create(['user_id' => $this->user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Open]);
            Task::factory()->create(['user_id' => $this->user->id, 'priority' => Priority::High, 'status' => TaskStatus::Open]);
            Task::factory()->count(3)->create(['user_id' => $this->user->id, 'priority' => Priority::Normal, 'status' => TaskStatus::Open]);
            Task::factory()->create(['user_id' => $this->user->id, 'priority' => Priority::Low, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByPriority);

            expect($result->series)->toBe([2, 1, 3, 1]);
        });
    });

    describe('tasksByCategory', function (): void {
        it('groups tasks by category name', function (): void {
            $category = TaskCategory::create(['name' => 'Bug', 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'task_category_id' => $category->id, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByCategory);

            expect($result->labels)->toContain('Bug');
        });

        it('labels uncategorized tasks as Uncategorized', function (): void {
            Task::factory()->create(['user_id' => $this->user->id, 'task_category_id' => null, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByCategory);

            expect($result->labels)->toContain('Uncategorized');
        });

        it('excludes done tasks', function (): void {
            $category = TaskCategory::create(['name' => 'Feature', 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'task_category_id' => $category->id, 'status' => TaskStatus::Done]);

            $result = $this->service->resolve(DataSource::TasksByCategory);

            expect($result->labels)->not->toContain('Feature');
        });

        it('returns colors from the palette', function (): void {
            $palette = ['#3b82f6', '#14b8a6', '#6366f1', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6', '#06b6d4'];

            $categoryA = TaskCategory::create(['name' => 'Alpha', 'user_id' => $this->user->id]);
            $categoryB = TaskCategory::create(['name' => 'Beta', 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'task_category_id' => $categoryA->id, 'status' => TaskStatus::Open]);
            Task::factory()->create(['user_id' => $this->user->id, 'task_category_id' => $categoryB->id, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByCategory);

            foreach ($result->colors as $color) {
                expect($palette)->toContain($color);
            }
        });
    });

    describe('tasksByGroup', function (): void {
        it('groups tasks by group name', function (): void {
            $group = TaskGroup::create(['name' => 'Sprint 1', 'color' => '#3b82f6', 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'task_group_id' => $group->id, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByGroup);

            expect($result->labels)->toContain('Sprint 1');
        });

        it('uses group colors from the database', function (): void {
            $group = TaskGroup::create(['name' => 'Sprint 1', 'color' => '#ff0000', 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'task_group_id' => $group->id, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByGroup);

            $groupIndex = array_search('Sprint 1', $result->labels);
            expect($result->colors[$groupIndex])->toBe('#ff0000');
        });

        it('labels ungrouped tasks as Ungrouped with color #9ca3af', function (): void {
            Task::factory()->create(['user_id' => $this->user->id, 'task_group_id' => null, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByGroup);

            $ungroupedIndex = array_search('Ungrouped', $result->labels);
            expect($ungroupedIndex)->not->toBeFalse();
            expect($result->colors[$ungroupedIndex])->toBe('#9ca3af');
        });

        it('excludes done tasks', function (): void {
            $group = TaskGroup::create(['name' => 'Sprint Done', 'color' => '#22c55e', 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'task_group_id' => $group->id, 'status' => TaskStatus::Done]);

            $result = $this->service->resolve(DataSource::TasksByGroup);

            expect($result->labels)->not->toContain('Sprint Done');
        });
    });

    describe('tasksByMember', function (): void {
        it('groups tasks by team member name', function (): void {
            $team   = Team::create(['name' => 'Dev Team', 'user_id' => $this->user->id]);
            $member = TeamMember::create(['name' => 'Alice', 'team_id' => $team->id, 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'team_member_id' => $member->id, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByMember);

            expect($result->labels)->toContain('Alice');
        });

        it('labels unassigned tasks as Unassigned', function (): void {
            Task::factory()->create(['user_id' => $this->user->id, 'team_member_id' => null, 'status' => TaskStatus::Open]);

            $result = $this->service->resolve(DataSource::TasksByMember);

            expect($result->labels)->toContain('Unassigned');
        });

        it('excludes done tasks', function (): void {
            $team   = Team::create(['name' => 'Dev Team', 'user_id' => $this->user->id]);
            $member = TeamMember::create(['name' => 'Bob', 'team_id' => $team->id, 'user_id' => $this->user->id]);
            Task::factory()->create(['user_id' => $this->user->id, 'team_member_id' => $member->id, 'status' => TaskStatus::Done]);

            $result = $this->service->resolve(DataSource::TasksByMember);

            expect($result->labels)->not->toContain('Bob');
        });
    });

    describe('tasksByDeadline', function (): void {
        it('returns six fixed labels', function (): void {
            $result = $this->service->resolve(DataSource::TasksByDeadline);

            expect($result->labels)->toBe(['Overdue', 'Today', 'This Week', 'Next Week', 'Later', 'No Deadline']);
        });

        it('correctly classifies overdue tasks', function (): void {
            Task::factory()->create([
                'user_id'  => $this->user->id,
                'deadline' => now()->subDay()->toDateString(),
                'status'   => TaskStatus::Open,
            ]);

            $result = $this->service->resolve(DataSource::TasksByDeadline);

            $overdueIndex = array_search('Overdue', $result->labels);
            expect($result->series[$overdueIndex])->toBe(1);
        });

        it('correctly classifies tasks due today', function (): void {
            Task::factory()->create([
                'user_id'  => $this->user->id,
                'deadline' => now()->toDateString(),
                'status'   => TaskStatus::Open,
            ]);

            $result = $this->service->resolve(DataSource::TasksByDeadline);

            $todayIndex = array_search('Today', $result->labels);
            expect($result->series[$todayIndex])->toBe(1);
        });

        it('correctly classifies tasks with no deadline', function (): void {
            Task::factory()->create([
                'user_id'  => $this->user->id,
                'deadline' => null,
                'status'   => TaskStatus::Open,
            ]);

            $result = $this->service->resolve(DataSource::TasksByDeadline);

            $noDeadlineIndex = array_search('No Deadline', $result->labels);
            expect($result->series[$noDeadlineIndex])->toBe(1);
        });

        it('excludes done tasks', function (): void {
            Task::factory()->create([
                'user_id'  => $this->user->id,
                'deadline' => now()->subDay()->toDateString(),
                'status'   => TaskStatus::Done,
            ]);

            $result = $this->service->resolve(DataSource::TasksByDeadline);

            expect(array_sum($result->series))->toBe(0);
        });

        it('always returns exactly 6 colors', function (): void {
            $result = $this->service->resolve(DataSource::TasksByDeadline);

            expect($result->colors)->toHaveCount(6);
        });
    });

    describe('followUpsByStatus', function (): void {
        it('returns all three status labels', function (): void {
            $result = $this->service->resolve(DataSource::FollowUpsByStatus);

            expect($result->labels)->toBe(['Open', 'Snoozed', 'Done']);
        });

        it('counts follow-ups per status correctly', function (): void {
            FollowUp::create(['description' => 'Open 1', 'status' => FollowUpStatus::Open, 'follow_up_date' => now()->addDay(), 'user_id' => $this->user->id]);
            FollowUp::create(['description' => 'Open 2', 'status' => FollowUpStatus::Open, 'follow_up_date' => now()->addDay(), 'user_id' => $this->user->id]);
            FollowUp::create(['description' => 'Snoozed 1', 'status' => FollowUpStatus::Snoozed, 'follow_up_date' => now()->addDay(), 'user_id' => $this->user->id]);
            FollowUp::create(['description' => 'Done 1', 'status' => FollowUpStatus::Done, 'follow_up_date' => now()->addDay(), 'user_id' => $this->user->id]);

            $result = $this->service->resolve(DataSource::FollowUpsByStatus);

            expect($result->series)->toBe([2, 1, 1]);
        });
    });

    describe('followUpsByUrgency', function (): void {
        it('returns four fixed labels', function (): void {
            $result = $this->service->resolve(DataSource::FollowUpsByUrgency);

            expect($result->labels)->toBe(['Overdue', 'Today', 'This Week', 'Later']);
        });

        it('excludes done follow-ups', function (): void {
            FollowUp::create([
                'description'    => 'Done overdue',
                'status'         => FollowUpStatus::Done,
                'follow_up_date' => now()->subDay()->toDateString(),
                'user_id'        => $this->user->id,
            ]);

            $result = $this->service->resolve(DataSource::FollowUpsByUrgency);

            expect(array_sum($result->series))->toBe(0);
        });

        it('correctly classifies overdue follow-ups', function (): void {
            FollowUp::create([
                'description'    => 'Past item',
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => now()->subDay()->toDateString(),
                'user_id'        => $this->user->id,
            ]);

            $result = $this->service->resolve(DataSource::FollowUpsByUrgency);

            $overdueIndex = array_search('Overdue', $result->labels);
            expect($result->series[$overdueIndex])->toBe(1);
        });

        it('correctly classifies follow-ups due today', function (): void {
            FollowUp::create([
                'description'    => 'Today item',
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => now()->toDateString(),
                'user_id'        => $this->user->id,
            ]);

            $result = $this->service->resolve(DataSource::FollowUpsByUrgency);

            $todayIndex = array_search('Today', $result->labels);
            expect($result->series[$todayIndex])->toBe(1);
        });
    });
});
