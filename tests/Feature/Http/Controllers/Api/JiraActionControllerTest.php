<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\JiraIssueLink;
use App\Models\Task;
use App\Models\TeamMember;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'jira_cloud_id'        => 'test-cloud-id',
        'jira_access_token'    => 'test-token',
        'jira_refresh_token'   => 'test-refresh',
        'jira_token_expires_at' => now()->addHour(),
    ]);
});

test('prefill returns task prefill data', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary'       => 'Fix login',
        'priority_name' => 'High',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/jira-issues/{$issue->id}/prefill/task")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Fix login')
        ->assertJsonPath('data.priority', 'high');
});

test('prefill returns follow-up prefill data', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary' => 'Follow up on deployment',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/jira-issues/{$issue->id}/prefill/follow-up")
        ->assertOk()
        ->assertJsonPath('data.description', 'Follow up on deployment');
});

test('prefill returns note prefill data', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'issue_key' => 'PROJ-123',
        'summary'   => 'Sprint planning',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/jira-issues/{$issue->id}/prefill/note")
        ->assertOk()
        ->assertJsonPath('data.title', 'PROJ-123 Sprint planning');
});

test('prefill returns bila prefill data with team member', function (): void {
    TeamMember::factory()->create([
        'user_id'          => $this->user->id,
        'jira_account_id'  => 'jira-acc-123',
    ]);

    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary'             => 'Discuss architecture',
        'assignee_account_id' => 'jira-acc-123',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/jira-issues/{$issue->id}/prefill/bila")
        ->assertOk()
        ->assertJsonPath('data.prep_item_content', 'Discuss architecture');
});

test('prefill returns error for bila without team member', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => 'no-match-acc',
    ]);

    $this->actingAs($this->user)
        ->getJson("/api/v1/jira-issues/{$issue->id}/prefill/bila")
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('create creates a task and links it', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary'       => 'New task from Jira',
        'priority_name' => 'Medium',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/jira-issues/{$issue->id}/create/task")
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(Task::where('title', 'New task from Jira')->exists())->toBeTrue();
    expect(JiraIssueLink::where('jira_issue_id', $issue->id)->exists())->toBeTrue();
});

test('create creates a follow-up and links it', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary' => 'Follow up Jira issue',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/jira-issues/{$issue->id}/create/follow-up")
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(\App\Models\FollowUp::where('description', 'Follow up Jira issue')->exists())->toBeTrue();
});

test('create creates a note and links it', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'issue_key' => 'PROJ-99',
        'summary'   => 'Meeting notes',
    ]);

    $this->actingAs($this->user)
        ->postJson("/api/v1/jira-issues/{$issue->id}/create/note")
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(\App\Models\Note::where('title', 'PROJ-99 Meeting notes')->exists())->toBeTrue();
});

test('unlink removes link without deleting resource', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create();
    $task = Task::factory()->for($this->user)->create();
    $link = JiraIssueLink::create([
        'jira_issue_id' => $issue->id,
        'issue_key'     => $issue->issue_key,
        'issue_summary' => $issue->summary,
        'linkable_type' => Task::class,
        'linkable_id'   => $task->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/jira-issues/{$issue->id}/links/{$link->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(JiraIssueLink::find($link->id))->toBeNull();
    expect(Task::find($task->id))->not->toBeNull();
});

test('unlink fails for link not belonging to issue', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create();
    $otherIssue = JiraIssue::factory()->for($this->user)->create();
    $task = Task::factory()->for($this->user)->create();
    $link = JiraIssueLink::create([
        'jira_issue_id' => $otherIssue->id,
        'issue_key'     => $otherIssue->issue_key,
        'linkable_type' => Task::class,
        'linkable_id'   => $task->id,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/api/v1/jira-issues/{$issue->id}/links/{$link->id}")
        ->assertStatus(404);
});

test('api requires authentication', function (): void {
    $this->getJson('/api/v1/jira-issues/1/prefill/task')
        ->assertUnauthorized();
});
