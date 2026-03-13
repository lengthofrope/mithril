<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\JiraIssueLink;
use App\Models\Task;
use App\Models\User;

test('jira issue belongs to user via BelongsToUser trait', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $issue = JiraIssue::factory()->create(['user_id' => $user->id]);

    expect(JiraIssue::count())->toBe(1)
        ->and($issue->user_id)->toBe($user->id);
});

test('jira issue is scoped to authenticated user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    JiraIssue::withoutGlobalScopes()->insert([
        [
            'user_id'           => $user1->id,
            'jira_issue_id'     => 'issue-1',
            'issue_key'         => 'PROJ-1',
            'summary'           => 'Issue for user 1',
            'project_key'       => 'PROJ',
            'project_name'      => 'Project',
            'issue_type'        => 'Task',
            'status_name'       => 'Open',
            'status_category'   => 'new',
            'web_url'           => 'https://jira.example.com/browse/PROJ-1',
            'sources'           => json_encode(['assigned']),
            'updated_in_jira_at' => now(),
            'synced_at'         => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
        [
            'user_id'           => $user2->id,
            'jira_issue_id'     => 'issue-2',
            'issue_key'         => 'PROJ-2',
            'summary'           => 'Issue for user 2',
            'project_key'       => 'PROJ',
            'project_name'      => 'Project',
            'issue_type'        => 'Task',
            'status_name'       => 'Open',
            'status_category'   => 'new',
            'web_url'           => 'https://jira.example.com/browse/PROJ-2',
            'sources'           => json_encode(['assigned']),
            'updated_in_jira_at' => now(),
            'synced_at'         => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ],
    ]);

    $this->actingAs($user1);
    expect(JiraIssue::count())->toBe(1);

    $this->actingAs($user2);
    expect(JiraIssue::count())->toBe(1);
});

test('jira issue casts sources to array', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $issue = JiraIssue::factory()->create([
        'user_id' => $user->id,
        'sources' => ['assigned', 'mentioned'],
    ]);

    $issue->refresh();
    expect($issue->sources)->toBeArray()
        ->and($issue->sources)->toBe(['assigned', 'mentioned']);
});

test('jira issue casts is_dismissed to boolean', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $issue = JiraIssue::factory()->create([
        'user_id'      => $user->id,
        'is_dismissed' => true,
    ]);

    $issue->refresh();
    expect($issue->is_dismissed)->toBeTrue();
});

test('jira issue has many jira issue links', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $issue = JiraIssue::factory()->create(['user_id' => $user->id]);
    $task  = Task::factory()->create(['user_id' => $user->id]);

    JiraIssueLink::create([
        'jira_issue_id' => $issue->id,
        'issue_key'     => $issue->issue_key,
        'issue_summary' => $issue->summary,
        'linkable_type' => Task::class,
        'linkable_id'   => $task->id,
    ]);

    expect($issue->jiraIssueLinks)->toHaveCount(1);
});
