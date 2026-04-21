<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('scopeOverdue does not return tasks with null deadline', function (): void {
    $user = User::factory()->create();

    Task::factory()->create([
        'user_id'  => $user->id,
        'title'    => 'No deadline task',
        'deadline' => null,
        'status'   => TaskStatus::Open,
    ]);

    $results = Task::overdue()->get();

    expect($results->pluck('title'))->not->toContain('No deadline task');
});

test('scopeOverdue returns tasks with a past deadline that are not done', function (): void {
    $user = User::factory()->create();

    Task::factory()->create([
        'user_id'  => $user->id,
        'title'    => 'Past deadline task',
        'deadline' => now()->subDays(3)->toDateString(),
        'status'   => TaskStatus::Open,
    ]);

    $results = Task::overdue()->get();

    expect($results->pluck('title'))->toContain('Past deadline task');
});

test('scopeOverdue excludes done tasks even when deadline is past', function (): void {
    $user = User::factory()->create();

    Task::factory()->create([
        'user_id'  => $user->id,
        'title'    => 'Done past deadline task',
        'deadline' => now()->subDays(2)->toDateString(),
        'status'   => TaskStatus::Done,
    ]);

    $results = Task::overdue()->get();

    expect($results->pluck('title'))->not->toContain('Done past deadline task');
});
