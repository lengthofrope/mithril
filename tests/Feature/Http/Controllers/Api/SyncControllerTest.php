<?php

declare(strict_types=1);

use App\Jobs\SyncCalendarEventsJob;
use App\Jobs\SyncEmailsJob;
use App\Jobs\SyncJiraIssuesJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

describe('Jira sync endpoint', function (): void {
    it('dispatches SyncJiraIssuesJob for a connected user', function (): void {
        $user = User::factory()->create([
            'jira_cloud_id'         => 'test-cloud-id',
            'jira_access_token'     => 'test-token',
            'jira_refresh_token'    => 'test-refresh',
            'jira_token_expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/sync/jira')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Jira sync started.']);

        Queue::assertPushed(SyncJiraIssuesJob::class);
    });

    it('returns 422 when Jira is not connected', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/sync/jira')
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        Queue::assertNotPushed(SyncJiraIssuesJob::class);
    });

    it('requires authentication', function (): void {
        $this->postJson('/api/v1/sync/jira')
            ->assertUnauthorized();
    });
});

describe('Calendar sync endpoint', function (): void {
    it('dispatches SyncCalendarEventsJob for a connected user', function (): void {
        $user = User::factory()->create([
            'microsoft_id'              => 'test-ms-id',
            'microsoft_access_token'    => 'test-token',
            'microsoft_refresh_token'   => 'test-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/sync/calendar')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Calendar sync started.']);

        Queue::assertPushed(SyncCalendarEventsJob::class);
    });

    it('returns 422 when Microsoft is not connected', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/sync/calendar')
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        Queue::assertNotPushed(SyncCalendarEventsJob::class);
    });

    it('requires authentication', function (): void {
        $this->postJson('/api/v1/sync/calendar')
            ->assertUnauthorized();
    });
});

describe('Email sync endpoint', function (): void {
    it('dispatches SyncEmailsJob for a connected user', function (): void {
        $user = User::factory()->create([
            'microsoft_id'              => 'test-ms-id',
            'microsoft_access_token'    => 'test-token',
            'microsoft_refresh_token'   => 'test-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/sync/emails')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Email sync started.']);

        Queue::assertPushed(SyncEmailsJob::class);
    });

    it('returns 422 when Microsoft is not connected', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/sync/emails')
            ->assertUnprocessable()
            ->assertJson(['success' => false]);

        Queue::assertNotPushed(SyncEmailsJob::class);
    });

    it('requires authentication', function (): void {
        $this->postJson('/api/v1/sync/emails')
            ->assertUnauthorized();
    });
});
