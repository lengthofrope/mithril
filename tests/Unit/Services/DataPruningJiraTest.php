<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\JiraIssueLink;
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

test('dismissed jira issues older than retention are pruned', function () {
    JiraIssue::factory()->dismissed()->create([
        'user_id'    => $this->user->id,
        'updated_at' => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->jiraIssuesDeleted)->toBe(1);
    $this->assertDatabaseCount('jira_issues', 0);
});

test('dismissed jira issues newer than retention are preserved', function () {
    JiraIssue::factory()->dismissed()->create([
        'user_id'    => $this->user->id,
        'updated_at' => now()->subDays(30),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->jiraIssuesDeleted)->toBe(0);
    $this->assertDatabaseCount('jira_issues', 1);
});

test('active jira issues are never pruned regardless of age', function () {
    JiraIssue::factory()->create([
        'user_id'      => $this->user->id,
        'is_dismissed'  => false,
        'updated_at'   => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->jiraIssuesDeleted)->toBe(0);
    $this->assertDatabaseCount('jira_issues', 1);
});

test('stale jira issues beyond 30 days sync are pruned as safety net', function () {
    JiraIssue::factory()->create([
        'user_id'      => $this->user->id,
        'is_dismissed'  => false,
        'synced_at'    => now()->subDays(31),
        'updated_at'   => now()->subDays(31),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->jiraIssuesDeleted)->toBe(1);
    $this->assertDatabaseCount('jira_issues', 0);
});

test('recently synced jira issues are not pruned by safety net', function () {
    JiraIssue::factory()->create([
        'user_id'      => $this->user->id,
        'is_dismissed'  => false,
        'synced_at'    => now()->subDays(5),
        'updated_at'   => now()->subDays(90),
    ]);

    $result = $this->service->pruneForUser($this->user);

    expect($result->jiraIssuesDeleted)->toBe(0);
});

test('orphaned jira issue links are cleaned up', function () {
    $issue = JiraIssue::factory()->create(['user_id' => $this->user->id]);

    JiraIssueLink::create([
        'jira_issue_id' => $issue->id,
        'issue_key'     => $issue->issue_key,
        'linkable_type' => Task::class,
        'linkable_id'   => 99999,
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('jira_issue_links', 0);
});

test('pruning jira issue sets jira_issue_id to null on linked resources', function () {
    $issue = JiraIssue::factory()->dismissed()->create([
        'user_id'    => $this->user->id,
        'updated_at' => now()->subDays(90),
    ]);

    $task = Task::factory()->create(['user_id' => $this->user->id]);
    $link = JiraIssueLink::create([
        'jira_issue_id' => $issue->id,
        'issue_key'     => $issue->issue_key,
        'issue_summary' => $issue->summary,
        'linkable_type' => Task::class,
        'linkable_id'   => $task->id,
    ]);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('jira_issues', 0);
    $this->assertDatabaseHas('jira_issue_links', [
        'id'             => $link->id,
        'jira_issue_id'  => null,
        'issue_key'      => $issue->issue_key,
    ]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

test('jira issues from another user are not pruned', function () {
    $otherUser = User::factory()->create(['prune_after_days' => 60]);

    auth()->logout();
    JiraIssue::factory()->dismissed()->create([
        'user_id'    => $otherUser->id,
        'updated_at' => now()->subDays(90),
    ]);
    $this->actingAs($this->user);

    $this->service->pruneForUser($this->user);

    $this->assertDatabaseCount('jira_issues', 1);
});
