<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('data:prune processes all users with prune setting configured', function () {
    $user1 = User::factory()->create(['prune_after_days' => 30]);
    $user2 = User::factory()->create(['prune_after_days' => 60]);

    Task::factory()->create([
        'user_id' => $user1->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(45),
    ]);
    Task::factory()->create([
        'user_id' => $user2->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(90),
    ]);

    $this->artisan('data:prune')
        ->assertExitCode(0);

    $this->assertDatabaseCount('tasks', 0);
});

test('data:prune with --user option prunes only that user', function () {
    $user1 = User::factory()->create(['prune_after_days' => 30, 'email' => 'prune@test.com']);
    $user2 = User::factory()->create(['prune_after_days' => 30]);

    Task::factory()->create([
        'user_id' => $user1->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(45),
    ]);
    Task::factory()->create([
        'user_id' => $user2->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(45),
    ]);

    $this->artisan('data:prune', ['--user' => 'prune@test.com'])
        ->assertExitCode(0);

    $this->assertDatabaseCount('tasks', 1);
});

test('data:prune with --dry-run shows counts without deleting', function () {
    $user = User::factory()->create(['prune_after_days' => 30]);
    Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(45),
    ]);

    $this->artisan('data:prune', ['--dry-run' => true])
        ->assertExitCode(0);

    $this->assertDatabaseCount('tasks', 1);
});

test('user without prune_after_days is skipped', function () {
    $user = User::factory()->create(['prune_after_days' => null]);
    Task::factory()->create([
        'user_id' => $user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(90),
    ]);

    $this->artisan('data:prune')
        ->assertExitCode(0);

    $this->assertDatabaseCount('tasks', 1);
});

test('data:prune with nonexistent user email fails gracefully', function () {
    $this->artisan('data:prune', ['--user' => 'nonexistent@test.com'])
        ->assertExitCode(1);
});
