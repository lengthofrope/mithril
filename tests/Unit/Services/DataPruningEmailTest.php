<?php

declare(strict_types=1);

use App\Models\Email;
use App\Models\EmailLink;
use App\Models\Task;
use App\Models\User;
use App\Services\DataPruningService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['prune_after_days' => 60]);
    $this->actingAs($this->user);
    $this->service = new DataPruningService();
});

test('dismissed emails older than retention are pruned', function () {
    Email::factory()->dismissed()->create([
        'user_id' => $this->user->id,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->emailsDeleted)->toBe(1);
    $this->assertDatabaseCount('emails', 0);
});

test('dismissed emails newer than retention are preserved', function () {
    Email::factory()->dismissed()->create([
        'user_id' => $this->user->id,
        'updated_at' => now()->subDays(30),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->emailsDeleted)->toBe(0);
    $this->assertDatabaseCount('emails', 1);
});

test('active (non-dismissed) emails are never pruned regardless of age', function () {
    Email::factory()->create([
        'user_id' => $this->user->id,
        'is_dismissed' => false,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->emailsDeleted)->toBe(0);
    $this->assertDatabaseCount('emails', 1);
});

test('pruning email sets email_id to null on linked resources', function () {
    $email = Email::factory()->dismissed()->create([
        'user_id' => $this->user->id,
        'subject' => 'Important email',
        'updated_at' => now()->subDays(90),
    ]);

    $task = Task::factory()->create(['user_id' => $this->user->id]);
    $link = EmailLink::factory()->create([
        'email_id' => $email->id,
        'email_subject' => $email->subject,
        'linkable_type' => Task::class,
        'linkable_id' => $task->id,
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('emails', 0);
    $this->assertDatabaseHas('email_links', [
        'id' => $link->id,
        'email_id' => null,
        'email_subject' => 'Important email',
    ]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

test('orphaned email links are cleaned up', function () {
    $email = Email::factory()->create(['user_id' => $this->user->id]);

    EmailLink::factory()->create([
        'email_id' => $email->id,
        'linkable_type' => Task::class,
        'linkable_id' => 99999,
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('email_links', 0);
});

test('email link retains email_subject after email is pruned', function () {
    $email = Email::factory()->dismissed()->create([
        'user_id' => $this->user->id,
        'subject' => 'Preserved subject',
        'updated_at' => now()->subDays(90),
    ]);

    $task = Task::factory()->create(['user_id' => $this->user->id]);
    EmailLink::factory()->create([
        'email_id' => $email->id,
        'email_subject' => 'Preserved subject',
        'linkable_type' => Task::class,
        'linkable_id' => $task->id,
    ]);

    $this->service->pruneForUser($this->user);

    $link = EmailLink::first();
    expect($link->email_subject)->toBe('Preserved subject');
    expect($link->email_id)->toBeNull();
});

test('emails from another user are not pruned', function () {
    $otherUser = User::factory()->create(['prune_after_days' => 60]);

    auth()->logout();
    Email::factory()->dismissed()->create([
        'user_id' => $otherUser->id,
        'updated_at' => now()->subDays(90),
    ]);
    $this->actingAs($this->user);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('emails', 1);
});
