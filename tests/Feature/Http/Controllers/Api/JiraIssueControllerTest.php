<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'jira_cloud_id'        => 'test-cloud-id',
        'jira_access_token'    => 'test-token',
        'jira_refresh_token'   => 'test-refresh',
        'jira_token_expires_at' => now()->addHour(),
    ]);
});

test('index returns all non-dismissed issues', function (): void {
    JiraIssue::factory()->for($this->user)->count(3)->create();
    JiraIssue::factory()->for($this->user)->dismissed()->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

test('index filters by source', function (): void {
    JiraIssue::factory()->for($this->user)->create(['sources' => ['assigned']]);
    JiraIssue::factory()->for($this->user)->create(['sources' => ['mentioned']]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues?source=assigned')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('index filters by status category', function (): void {
    JiraIssue::factory()->for($this->user)->create(['status_category' => 'new']);
    JiraIssue::factory()->for($this->user)->create(['status_category' => 'done']);

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues?status_category=new')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('index includes jira issue links', function (): void {
    JiraIssue::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'issue_key', 'summary', 'jira_issue_links']]]);
});

test('dismiss toggles issue dismissed state', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create(['is_dismissed' => false]);

    $this->actingAs($this->user)
        ->patchJson("/api/v1/jira-issues/{$issue->id}/dismiss")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($issue->fresh()->is_dismissed)->toBeTrue();
});

test('undismiss restores issue', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->dismissed()->create();

    $this->actingAs($this->user)
        ->patchJson("/api/v1/jira-issues/{$issue->id}/undismiss")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($issue->fresh()->is_dismissed)->toBeFalse();
});

test('dashboard returns assigned open issues', function (): void {
    JiraIssue::factory()->for($this->user)->create([
        'sources'         => ['assigned'],
        'status_category' => 'new',
        'priority_name'   => 'High',
    ]);
    JiraIssue::factory()->for($this->user)->create([
        'sources'         => ['assigned'],
        'status_category' => 'done',
    ]);
    JiraIssue::factory()->for($this->user)->create([
        'sources'         => ['mentioned'],
        'status_category' => 'new',
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues/dashboard')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data.issues');
});

test('dashboard respects limit parameter', function (): void {
    JiraIssue::factory()->for($this->user)->count(10)->create([
        'sources'         => ['assigned'],
        'status_category' => 'new',
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues/dashboard?limit=3')
        ->assertOk()
        ->assertJsonCount(3, 'data.issues');
});

test('dashboard returns total count', function (): void {
    JiraIssue::factory()->for($this->user)->count(8)->create([
        'sources'         => ['assigned'],
        'status_category' => 'new',
    ]);

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues/dashboard?limit=3')
        ->assertOk()
        ->assertJsonPath('data.total', 8);
});

test('issues are scoped to authenticated user', function (): void {
    $otherUser = User::factory()->create();
    JiraIssue::factory()->for($otherUser)->count(3)->create();
    JiraIssue::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->getJson('/api/v1/jira-issues')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('api requires authentication', function (): void {
    $this->getJson('/api/v1/jira-issues')
        ->assertUnauthorized();
});
