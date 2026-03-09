<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\TaskStatus;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Note;
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
    $response->assertViewHas('weekStats');
    $response->assertViewHas('pastReflections');
    expect($response->viewData('weekStats'))->toHaveKeys([
        'tasks_completed',
        'tasks_open',
        'follow_ups_handled',
        'bilas_held',
        'notes_written',
    ]);
    $response->assertViewHas('chartData');
});

test('weekly reflection weekStats includes completed tasks count this week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);
    Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subWeeks(2),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $weekStats = $response->viewData('weekStats');
    expect($weekStats['tasks_completed'])->toBe(1);
});

test('weekly reflection weekStats includes open tasks count', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::InProgress]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/weekly');

    $weekStats = $response->viewData('weekStats');
    expect($weekStats['tasks_open'])->toBe(2);
});

test('weekly reflection weekStats includes handled follow-ups count this week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);
    FollowUp::factory()->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Done,
        'updated_at' => now()->subWeeks(2),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $weekStats = $response->viewData('weekStats');
    expect($weekStats['follow_ups_handled'])->toBe(1);
});

test('weekly reflection weekStats includes bilas held this week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Bila::factory()->create([
        'user_id' => $user->id,
        'is_done' => true,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);
    Bila::factory()->create([
        'user_id' => $user->id,
        'is_done' => true,
        'updated_at' => now()->subWeeks(2),
    ]);
    Bila::factory()->create([
        'user_id' => $user->id,
        'is_done' => false,
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $weekStats = $response->viewData('weekStats');
    expect($weekStats['bilas_held'])->toBe(1);
});

test('weekly reflection weekStats includes notes written this week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Note::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->startOfWeek()->addDay(),
    ]);
    Note::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subWeeks(2),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $weekStats = $response->viewData('weekStats');
    expect($weekStats['notes_written'])->toBe(1);
});

test('weekly reflection passes chart data with donut and bar datasets', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->count(3)->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);
    Task::factory()->count(2)->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Open,
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $chartData = $response->viewData('chartData');
    expect($chartData)->toHaveKeys(['donut', 'bar']);

    expect($chartData['donut'])->toHaveKeys(['labels', 'series', 'colors']);
    expect($chartData['donut']['labels'])->toContain('Completed');
    expect($chartData['donut']['series'])->toBeArray();

    expect($chartData['bar'])->toHaveKeys(['labels', 'series', 'colors']);
    expect($chartData['bar']['labels'])->toBeArray();
    expect($chartData['bar']['series'])->toBeArray();
});

test('weekly reflection summary includes notes and bilas info', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Note::factory()->count(2)->create([
        'user_id' => $user->id,
        'created_at' => now()->startOfWeek()->addDay(),
    ]);
    Bila::factory()->create([
        'user_id' => $user->id,
        'is_done' => true,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $current = $response->viewData('currentReflection');
    expect($current->summary)->toContain('2');
    expect($current->summary)->toContain('note');
    expect($current->summary)->toContain('1');
    expect($current->summary)->toContain('bila');
});

test('weekly reflection shows current week reflection when it exists', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $reflection = WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->startOfWeek()->toDateString(),
        'week_end' => now()->endOfWeek()->toDateString(),
        'reflection' => 'This week was productive.',
    ]);

    $response = $this->actingAs($user)->get('/weekly');
    $response->assertStatus(200);

    $current = $response->viewData('currentReflection');
    expect($current)->not->toBeNull();
    expect($current->id)->toBe($reflection->id);
});

test('weekly reflection creates current week reflection when none exists', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/weekly');

    $current = $response->viewData('currentReflection');
    expect($current)->not->toBeNull();
    expect($current->week_start->toDateString())->toBe(now()->startOfWeek()->toDateString());
});

test('weekly reflection returns past reflections in descending order', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $older = WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->subWeeks(2)->startOfWeek()->toDateString(),
        'week_end' => now()->subWeeks(2)->endOfWeek()->toDateString(),
    ]);

    $newer = WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->subWeek()->startOfWeek()->toDateString(),
        'week_end' => now()->subWeek()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $past = $response->viewData('pastReflections');
    expect($past)->toHaveCount(2);
    expect($past->first()->id)->toBe($newer->id);
});

test('weekly reflection current week dates are formatted without timestamps', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/weekly');

    $response->assertDontSee('00:00:00');
    $current = $response->viewData('currentReflection');
    $formattedStart = \Carbon\Carbon::parse($current->week_start)->format('d M Y');
    $response->assertSee($formattedStart);
});

test('weekly reflection index works when another user already has a reflection for the same week', function () {
    /** @var \Tests\TestCase $this */
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    WeeklyReflection::factory()->create([
        'user_id' => $userA->id,
        'week_start' => now()->startOfWeek()->toDateString(),
        'week_end' => now()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($userB)->get('/weekly');

    $response->assertOk();
    $current = $response->viewData('currentReflection');
    expect($current)->not->toBeNull();
    expect($current->user_id)->toBe($userB->id);
});

test('weekly reflection auto-generates summary on index when summary is null', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->count(3)->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);
    Task::factory()->count(2)->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Open,
    ]);
    FollowUp::factory()->count(1)->create([
        'user_id' => $user->id,
        'status' => FollowUpStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    $current = $response->viewData('currentReflection');
    expect($current->summary)->not->toBeNull();
    expect($current->summary)->toContain('3');
    expect($current->summary)->toContain('2');
});

test('weekly reflection regenerates summary on every visit for current week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $reflection = WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->startOfWeek()->toDateString(),
        'week_end' => now()->endOfWeek()->toDateString(),
        'summary' => 'Stale summary',
    ]);

    Task::factory()->count(5)->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->startOfWeek()->addDay(),
    ]);

    $this->actingAs($user)->get('/weekly');

    $fresh = $reflection->fresh();
    expect($fresh->summary)->not->toBe('Stale summary');
    expect($fresh->summary)->toContain('5');
});

test('weekly reflection store creates a new reflection for a past week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $weekStart = now()->subWeeks(2)->startOfWeek()->toDateString();

    $response = $this->actingAs($user)->post('/weekly', [
        'week_start' => $weekStart,
    ]);

    $response->assertRedirect(route('weekly.index'));
    $this->assertDatabaseHas('weekly_reflections', [
        'user_id' => $user->id,
        'week_start' => $weekStart . ' 00:00:00',
    ]);
});

test('weekly reflection store does not create duplicate for same week', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $weekStart = now()->subWeek()->startOfWeek()->toDateString();

    WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => $weekStart,
        'week_end' => now()->subWeek()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($user)->post('/weekly', [
        'week_start' => $weekStart,
    ]);

    $response->assertRedirect(route('weekly.index'));
    $this->assertDatabaseCount('weekly_reflections', 1);
});

test('weekly reflection store requires a valid week_start date', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/weekly', [
        'week_start' => 'not-a-date',
    ]);

    $response->assertSessionHasErrors('week_start');
});

test('weekly reflection store rejects future week_start dates', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/weekly', [
        'week_start' => now()->addWeeks(2)->startOfWeek()->toDateString(),
    ]);

    $response->assertSessionHasErrors('week_start');
});

test('weekly reflection destroy deletes a reflection', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $reflection = WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->subWeek()->startOfWeek()->toDateString(),
        'week_end' => now()->subWeek()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($user)->delete("/weekly/{$reflection->id}");

    $response->assertRedirect(route('weekly.index'));
    $this->assertDatabaseMissing('weekly_reflections', ['id' => $reflection->id]);
});

test('weekly reflection destroy via ajax returns json', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $reflection = WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->subWeek()->startOfWeek()->toDateString(),
        'week_end' => now()->subWeek()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($user)
        ->deleteJson("/weekly/{$reflection->id}");

    $response->assertOk()->assertJson(['success' => true]);
    $this->assertDatabaseMissing('weekly_reflections', ['id' => $reflection->id]);
});

test('weekly reflection update supports editing past reflection text', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $reflection = WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->subWeek()->startOfWeek()->toDateString(),
        'week_end' => now()->subWeek()->endOfWeek()->toDateString(),
        'reflection' => 'Old text',
    ]);

    $response = $this->actingAs($user)
        ->patchJson("/weekly/{$reflection->id}", ['reflection' => 'Updated text']);

    $response->assertOk();
    expect($reflection->fresh()->reflection)->toBe('Updated text');
});

test('weekly reflection does not include current week in past reflections', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    WeeklyReflection::factory()->create([
        'user_id' => $user->id,
        'week_start' => now()->startOfWeek()->toDateString(),
        'week_end' => now()->endOfWeek()->toDateString(),
    ]);

    $response = $this->actingAs($user)->get('/weekly');

    expect($response->viewData('pastReflections'))->toHaveCount(0);
});
