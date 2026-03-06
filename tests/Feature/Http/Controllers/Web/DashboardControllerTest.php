<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
});

test('dashboard redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('dashboard renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewIs('pages.dashboard');
});

test('dashboard passes greeting variable to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('greeting');
    expect($response->viewData('greeting'))->toBeString()->not->toBeEmpty();
});

test('dashboard passes stats array to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('stats');
    expect($response->viewData('stats'))->toBeArray()->toHaveKeys([
        'open_tasks',
        'urgent_tasks',
        'overdue_follow_ups',
        'today_follow_ups',
        'upcoming_bilas',
    ]);
});

test('dashboard passes today array to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('today');
    expect($response->viewData('today'))->toBeArray()->toHaveKeys([
        'tasks_due_today',
        'overdue_follow_ups',
        'bilas_today',
    ]);
});

test('stats open_tasks counts non-done tasks only', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['status' => TaskStatus::Open]);
    Task::factory()->create(['status' => TaskStatus::InProgress]);
    Task::factory()->create(['status' => TaskStatus::Waiting]);
    Task::factory()->create(['status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('stats')['open_tasks'])->toBe(3);
});

test('stats urgent_tasks counts urgent non-done tasks only', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['priority' => Priority::Urgent, 'status' => TaskStatus::Open]);
    Task::factory()->create(['priority' => Priority::Urgent, 'status' => TaskStatus::Done]);
    Task::factory()->create(['priority' => Priority::Normal, 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('stats')['urgent_tasks'])->toBe(1);
});

test('stats overdue_follow_ups counts overdue non-done follow-ups', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['follow_up_date' => now()->subDays(2)]);
    FollowUp::factory()->create(['follow_up_date' => now()->addDay()]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('stats')['overdue_follow_ups'])->toBe(1);
});

test('stats today_follow_ups counts follow-ups due today', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['follow_up_date' => now()->toDateString()]);
    FollowUp::factory()->create(['follow_up_date' => now()->addDays(3)]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('stats')['today_follow_ups'])->toBe(1);
});

test('stats upcoming_bilas counts bilas within the current week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Bila::factory()->create(['scheduled_date' => now()->startOfWeek()]);
    Bila::factory()->create(['scheduled_date' => now()->endOfWeek()]);
    Bila::factory()->create(['scheduled_date' => now()->addWeeks(2)]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('stats')['upcoming_bilas'])->toBe(2);
});

test('today tasks_due_today contains tasks with deadline today and not done', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['deadline' => now()->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['deadline' => now()->toDateString(), 'status' => TaskStatus::Done]);
    Task::factory()->create(['deadline' => now()->addDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    $tasksDueToday = $response->viewData('today')['tasks_due_today'];
    expect($tasksDueToday)->toHaveCount(1);
});

test('today overdue_follow_ups contains all overdue non-done follow-ups', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['follow_up_date' => now()->subDays(3)]);
    FollowUp::factory()->create(['follow_up_date' => now()->subDay()]);
    FollowUp::factory()->create(['follow_up_date' => now()->addDay()]);

    $response = $this->actingAs($user)->get('/');

    $overdueFollowUps = $response->viewData('today')['overdue_follow_ups'];
    expect($overdueFollowUps)->toHaveCount(2);
});

test('today bilas_today contains bilas scheduled for today', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Bila::factory()->create(['scheduled_date' => now()->toDateString()]);
    Bila::factory()->create(['scheduled_date' => now()->addDay()->toDateString()]);

    $response = $this->actingAs($user)->get('/');

    $bilasToday = $response->viewData('today')['bilas_today'];
    expect($bilasToday)->toHaveCount(1);
});

test('dashboard stats are zero when no data exists', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $stats = $response->viewData('stats');
    expect($stats['open_tasks'])->toBe(0);
    expect($stats['urgent_tasks'])->toBe(0);
    expect($stats['overdue_follow_ups'])->toBe(0);
    expect($stats['today_follow_ups'])->toBe(0);
    expect($stats['upcoming_bilas'])->toBe(0);
});
