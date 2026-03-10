<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('counters endpoint returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    $response->assertOk();
});

test('counters endpoint returns standard api response with all counter keys', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'data' => [
                'open_tasks',
                'urgent_tasks',
                'overdue_follow_ups',
                'today_follow_ups',
                'bilas_this_week',
            ],
        ]);
});

test('counters endpoint returns zero counts when no data exists', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    $data = $response->json('data');
    expect($data['open_tasks'])->toBe(0);
    expect($data['urgent_tasks'])->toBe(0);
    expect($data['overdue_follow_ups'])->toBe(0);
    expect($data['today_follow_ups'])->toBe(0);
    expect($data['bilas_this_week'])->toBe(0);
});

test('counters endpoint counts open tasks correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::InProgress]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    expect($response->json('data.open_tasks'))->toBe(2);
});

test('counters endpoint counts urgent non-done tasks correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Done]);
    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Normal, 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    expect($response->json('data.urgent_tasks'))->toBe(1);
});

test('counters endpoint counts overdue follow-ups correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDays(2)]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDay()]);

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    expect($response->json('data.overdue_follow_ups'))->toBe(1);
});

test('counters endpoint counts today follow-ups correctly', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString()]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDays(3)]);

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    expect($response->json('data.today_follow_ups'))->toBe(1);
});

test('counters endpoint counts bilas this week correctly', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::create(2026, 3, 4, 10, 0, 0));
    $user = User::factory()->create();

    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()]);
    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()->addDay()]);
    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now()->addWeeks(2)]);
    Bila::factory()->create(['user_id' => $user->id, 'scheduled_date' => now(), 'is_done' => true]);

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    expect($response->json('data.bilas_this_week'))->toBe(2);
});

test('counters endpoint requires authentication', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/v1/counters');

    $response->assertUnauthorized();
});

test('counters endpoint only counts data belonging to authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $otherUser->id, 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->getJson('/api/v1/counters');

    expect($response->json('data.open_tasks'))->toBe(1);
});
