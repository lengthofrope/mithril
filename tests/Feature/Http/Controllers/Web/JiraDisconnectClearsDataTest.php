<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\JiraIssueLink;
use App\Models\Task;
use App\Models\User;

test('disconnecting jira clears all cached issues and links for the user', function (): void {
    $user = User::factory()->create([
        'jira_cloud_id'        => 'test-cloud-id',
        'jira_account_id'      => 'test-account-id',
        'jira_access_token'    => 'test-token',
        'jira_refresh_token'   => 'test-refresh',
        'jira_token_expires_at' => now()->addHour(),
    ]);

    $issue = JiraIssue::factory()->for($user)->create();
    $task = Task::factory()->for($user)->create();
    JiraIssueLink::create([
        'jira_issue_id' => $issue->id,
        'issue_key'     => $issue->issue_key,
        'linkable_type' => Task::class,
        'linkable_id'   => $task->id,
    ]);

    $this->actingAs($user)
        ->delete('/auth/jira')
        ->assertRedirect();

    expect($user->fresh()->hasJiraConnection())->toBeFalse();
    $this->assertDatabaseCount('jira_issues', 0);
    $this->assertDatabaseCount('jira_issue_links', 0);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});
