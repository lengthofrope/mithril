<?php

declare(strict_types=1);

use App\Jobs\SyncJiraIssuesJob;
use App\Models\User;
use App\Services\JiraSyncService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

test('job calls syncIssues on the sync service', function () {
    $user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-123',
        'jira_access_token'      => 'token',
        'jira_refresh_token'     => 'refresh',
        'jira_token_expires_at'  => now()->addHour(),
    ]);

    $mock = Mockery::mock(JiraSyncService::class);
    $mock->shouldReceive('syncIssues')
        ->once()
        ->with(Mockery::on(fn (User $u) => $u->id === $user->id));

    $this->app->instance(JiraSyncService::class, $mock);

    $job = new SyncJiraIssuesJob($user);
    $job->handle($mock);
});

test('job handles token revocation gracefully without re-queuing', function () {
    $user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-123',
        'jira_access_token'      => 'token',
        'jira_refresh_token'     => 'refresh',
        'jira_token_expires_at'  => now()->addHour(),
    ]);

    $mock = Mockery::mock(JiraSyncService::class);
    $mock->shouldReceive('syncIssues')
        ->once()
        ->andThrow(new RuntimeException('Token refresh failed'));

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn (string $msg) => str_contains($msg, 'Jira sync skipped'));

    $user->jira_cloud_id = null;
    $user->save();

    $job = new SyncJiraIssuesJob($user);
    $job->handle($mock);
});

test('job re-throws non-auth exceptions for retry', function () {
    $user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-123',
        'jira_access_token'      => 'token',
        'jira_refresh_token'     => 'refresh',
        'jira_token_expires_at'  => now()->addHour(),
    ]);

    $mock = Mockery::mock(JiraSyncService::class);
    $mock->shouldReceive('syncIssues')
        ->once()
        ->andThrow(new RuntimeException('Network timeout'));

    $job = new SyncJiraIssuesJob($user);
    $job->handle($mock);
})->throws(RuntimeException::class, 'Network timeout');

test('job has correct retry configuration', function () {
    $user = User::factory()->create();

    $job = new SyncJiraIssuesJob($user);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120, 300]);
});
