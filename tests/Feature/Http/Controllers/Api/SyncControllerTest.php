<?php

declare(strict_types=1);

use App\Jobs\SyncCalendarEventsJob;
use App\Jobs\SyncEmailsJob;
use App\Jobs\SyncJiraIssuesJob;
use App\Models\CalendarEvent;
use App\Models\Email;
use App\Models\JiraIssue;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

describe('Jira sync endpoint', function (): void {
    it('dispatches SyncJiraIssuesJob and returns synced_at', function (): void {
        $user = User::factory()->create([
            'jira_cloud_id'         => 'test-cloud-id',
            'jira_access_token'     => 'test-token',
            'jira_refresh_token'    => 'test-refresh',
            'jira_token_expires_at' => now()->addHour(),
        ]);

        JiraIssue::factory()->for($user)->create([
            'synced_at'       => '2026-03-13 10:00:00',
            'status_category' => 'new',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/sync/jira')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Jira sync started.'])
            ->assertJsonStructure(['synced_at']);

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
    it('dispatches SyncCalendarEventsJob and returns synced_at', function (): void {
        $user = User::factory()->create([
            'microsoft_id'              => 'test-ms-id',
            'microsoft_access_token'    => 'test-token',
            'microsoft_refresh_token'   => 'test-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        CalendarEvent::factory()->for($user)->create([
            'synced_at' => '2026-03-13 10:00:00',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/sync/calendar')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Calendar sync started.'])
            ->assertJsonStructure(['synced_at']);

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
    it('dispatches SyncEmailsJob and returns synced_at', function (): void {
        $user = User::factory()->create([
            'microsoft_id'              => 'test-ms-id',
            'microsoft_access_token'    => 'test-token',
            'microsoft_refresh_token'   => 'test-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Email::factory()->for($user)->create([
            'synced_at' => '2026-03-13 10:00:00',
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/sync/emails')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Email sync started.'])
            ->assertJsonStructure(['synced_at']);

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

describe('Sync status endpoint', function (): void {
    it('returns the latest synced_at for jira', function (): void {
        $user = User::factory()->create([
            'jira_cloud_id'         => 'test-cloud-id',
            'jira_access_token'     => 'test-token',
            'jira_refresh_token'    => 'test-refresh',
            'jira_token_expires_at' => now()->addHour(),
        ]);

        JiraIssue::factory()->for($user)->create([
            'synced_at'       => '2026-03-13 12:00:00',
            'status_category' => 'new',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/sync/jira/status')
            ->assertOk()
            ->assertJson(['success' => true, 'synced_at' => '2026-03-13 12:00:00']);
    });

    it('returns the latest synced_at for calendar', function (): void {
        $user = User::factory()->create([
            'microsoft_id'              => 'test-ms-id',
            'microsoft_access_token'    => 'test-token',
            'microsoft_refresh_token'   => 'test-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        CalendarEvent::factory()->for($user)->create([
            'synced_at' => '2026-03-13 12:00:00',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/sync/calendar/status')
            ->assertOk()
            ->assertJson(['success' => true, 'synced_at' => '2026-03-13 12:00:00']);
    });

    it('returns the latest synced_at for emails', function (): void {
        $user = User::factory()->create([
            'microsoft_id'              => 'test-ms-id',
            'microsoft_access_token'    => 'test-token',
            'microsoft_refresh_token'   => 'test-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Email::factory()->for($user)->create([
            'synced_at' => '2026-03-13 12:00:00',
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/sync/emails/status')
            ->assertOk()
            ->assertJson(['success' => true, 'synced_at' => '2026-03-13 12:00:00']);
    });

    it('returns null synced_at when no records exist', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/sync/jira/status')
            ->assertOk()
            ->assertJson(['success' => true, 'synced_at' => null]);
    });
});
