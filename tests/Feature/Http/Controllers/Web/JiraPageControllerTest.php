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

test('jira page renders for connected user', function (): void {
    JiraIssue::factory()->for($this->user)->count(3)->create();

    $this->actingAs($this->user)
        ->get('/jira')
        ->assertOk()
        ->assertViewIs('pages.jira')
        ->assertViewHas('isJiraConnected', true)
        ->assertViewHas('issues');
});

test('jira page shows empty state for disconnected user', function (): void {
    $disconnected = User::factory()->create();

    $this->actingAs($disconnected)
        ->get('/jira')
        ->assertOk()
        ->assertViewHas('isJiraConnected', false);
});

test('jira page filters by source', function (): void {
    JiraIssue::factory()->for($this->user)->create(['sources' => ['assigned']]);
    JiraIssue::factory()->for($this->user)->create(['sources' => ['mentioned']]);
    JiraIssue::factory()->for($this->user)->create(['sources' => ['watched']]);

    $response = $this->actingAs($this->user)
        ->get('/jira?source=assigned');

    $response->assertOk();

    $issues = $response->viewData('issues');
    expect($issues)->toHaveCount(1);
    expect($issues->first()->sources)->toContain('assigned');
});

test('jira page filters by status category', function (): void {
    JiraIssue::factory()->for($this->user)->create(['status_category' => 'new']);
    JiraIssue::factory()->for($this->user)->create(['status_category' => 'indeterminate']);
    JiraIssue::factory()->for($this->user)->create(['status_category' => 'done']);

    $response = $this->actingAs($this->user)
        ->get('/jira?status_category=indeterminate');

    $response->assertOk();

    $issues = $response->viewData('issues');
    expect($issues)->toHaveCount(1);
    expect($issues->first()->status_category)->toBe('indeterminate');
});

test('jira page filters by project key', function (): void {
    JiraIssue::factory()->for($this->user)->create(['project_key' => 'PROJ']);
    JiraIssue::factory()->for($this->user)->create(['project_key' => 'OTHER']);

    $response = $this->actingAs($this->user)
        ->get('/jira?project_key=PROJ');

    $response->assertOk();

    $issues = $response->viewData('issues');
    expect($issues)->toHaveCount(1);
    expect($issues->first()->project_key)->toBe('PROJ');
});

test('jira page groups issues by project', function (): void {
    JiraIssue::factory()->for($this->user)->create(['project_key' => 'PROJ', 'project_name' => 'Project A']);
    JiraIssue::factory()->for($this->user)->count(2)->create(['project_key' => 'OTHER', 'project_name' => 'Project B']);

    $response = $this->actingAs($this->user)
        ->get('/jira');

    $response->assertOk();
    $grouped = $response->viewData('groupedIssues');
    expect($grouped)->toHaveCount(2);
});

test('jira page hides dismissed issues by default', function (): void {
    JiraIssue::factory()->for($this->user)->create(['is_dismissed' => false]);
    JiraIssue::factory()->for($this->user)->create(['is_dismissed' => true]);

    $response = $this->actingAs($this->user)
        ->get('/jira');

    $issues = $response->viewData('issues');
    expect($issues)->toHaveCount(1);
});

test('jira page shows dismissed issues when filter is set', function (): void {
    JiraIssue::factory()->for($this->user)->create(['is_dismissed' => false]);
    JiraIssue::factory()->for($this->user)->create(['is_dismissed' => true]);

    $response = $this->actingAs($this->user)
        ->get('/jira?show_dismissed=1');

    $issues = $response->viewData('issues');
    expect($issues)->toHaveCount(2);
});

test('jira page provides project options for filter', function (): void {
    JiraIssue::factory()->for($this->user)->create(['project_key' => 'PROJ', 'project_name' => 'Project A']);
    JiraIssue::factory()->for($this->user)->create(['project_key' => 'OTHER', 'project_name' => 'Project B']);

    $response = $this->actingAs($this->user)
        ->get('/jira');

    $projectOptions = $response->viewData('projectOptions');
    expect($projectOptions)->toHaveCount(2);
});

test('jira page requires authentication', function (): void {
    $this->get('/jira')
        ->assertRedirect('/login');
});
