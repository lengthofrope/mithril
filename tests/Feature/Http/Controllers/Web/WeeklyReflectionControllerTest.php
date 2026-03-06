<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use App\Models\WeeklyReflection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('weekly reflection index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/weekly');

    $response->assertOk();
});

test('weekly reflection index redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/weekly');

    $response->assertRedirect('/login');
});

test('weekly reflection index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/weekly');

    $response->assertViewIs('pages.weekly.index');
});

test('weekly reflection index passes required view variables', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/weekly');

    $response->assertViewHas('weekStart');
    $response->assertViewHas('weekEnd');
    $response->assertViewHas('currentReflection');
    $response->assertViewHas('summary');
    $response->assertViewHas('pastReflections');
});

test('weekly reflection summary includes completed tasks count this week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create([
        'status' => TaskStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);
    Task::factory()->create([
        'status' => TaskStatus::Done,
        'updated_at' => now()->subWeeks(2),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $summary = $response->viewData('summary');
    expect($summary['completed_tasks_count'])->toBe(1);
});

test('weekly reflection summary includes open tasks count', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['status' => TaskStatus::Open]);
    Task::factory()->create(['status' => TaskStatus::InProgress]);
    Task::factory()->create(['status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/weekly');

    $summary = $response->viewData('summary');
    expect($summary['open_tasks_count'])->toBe(2);
});

test('weekly reflection summary includes handled follow-ups count this week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'status' => FollowUpStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);
    FollowUp::factory()->create([
        'status' => FollowUpStatus::Done,
        'updated_at' => now()->subWeeks(2),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $summary = $response->viewData('summary');
    expect($summary['handled_follow_ups_count'])->toBe(1);
});

test('weekly reflection summary includes open follow-ups count', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['status' => FollowUpStatus::Open]);
    FollowUp::factory()->create(['status' => FollowUpStatus::Done]);

    $response = $this->actingAs($user)->get('/weekly');

    $summary = $response->viewData('summary');
    expect($summary['open_follow_ups_count'])->toBe(1);
});

test('weekly reflection shows current week reflection when it exists', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $reflection = WeeklyReflection::factory()->create([
        'week_start' => now()->startOfWeek()->toDateString(),
        'week_end' => now()->endOfWeek()->toDateString(),
        'reflection' => 'This week was productive.',
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $current = $response->viewData('currentReflection');
    expect($current)->not->toBeNull();
    expect($current->id)->toBe($reflection->id);
});

test('weekly reflection current reflection is null when none exists for current week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/weekly');

    expect($response->viewData('currentReflection'))->toBeNull();
});

test('weekly reflection returns past reflections in descending order', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $older = WeeklyReflection::factory()->create([
        'week_start' => now()->subWeeks(2)->startOfWeek()->toDateString(),
        'week_end' => now()->subWeeks(2)->endOfWeek()->toDateString(),
    ]);

    $newer = WeeklyReflection::factory()->create([
        'week_start' => now()->subWeek()->startOfWeek()->toDateString(),
        'week_end' => now()->subWeek()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $past = $response->viewData('pastReflections');
    expect($past)->toHaveCount(2);
    expect($past->first()->id)->toBe($newer->id);
});

test('weekly reflection does not include current week in past reflections', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    WeeklyReflection::factory()->create([
        'week_start' => now()->startOfWeek()->toDateString(),
        'week_end' => now()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    expect($response->viewData('pastReflections'))->toHaveCount(0);
});
