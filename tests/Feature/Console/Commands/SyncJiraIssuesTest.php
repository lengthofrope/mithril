<?php

declare(strict_types=1);

use App\Jobs\SyncJiraIssuesJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('command dispatches jobs for all jira-connected users', function () {
    Queue::fake();

    $connectedUser = User::factory()->create([
        'jira_cloud_id' => 'cloud-123',
    ]);

    $disconnectedUser = User::factory()->create([
        'jira_cloud_id' => null,
    ]);

    $this->artisan('jira:sync-issues')
        ->assertSuccessful();

    Queue::assertPushed(SyncJiraIssuesJob::class, 1);
});

test('command outputs correct info message', function () {
    Queue::fake();

    User::factory()->create(['jira_cloud_id' => 'cloud-1']);
    User::factory()->create(['jira_cloud_id' => 'cloud-2']);

    $this->artisan('jira:sync-issues')
        ->expectsOutputToContain('2 connected user(s)')
        ->assertSuccessful();
});

test('command handles no connected users', function () {
    Queue::fake();

    User::factory()->create(['jira_cloud_id' => null]);

    $this->artisan('jira:sync-issues')
        ->expectsOutputToContain('0 connected user(s)')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});
