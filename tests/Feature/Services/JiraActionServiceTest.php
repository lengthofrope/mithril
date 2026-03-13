<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\JiraActionService;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'jira_cloud_id'        => 'test-cloud-id',
        'jira_site_url'        => 'https://test.atlassian.net',
        'jira_access_token'    => 'test-token',
        'jira_refresh_token'   => 'test-refresh',
        'jira_token_expires_at' => now()->addHour(),
    ]);
    $this->actingAs($this->user);
    $this->service = app(JiraActionService::class);
});

test('resolveTeamMember matches assignee account ID against team member jira_account_id', function (): void {
    $member = TeamMember::factory()->create([
        'user_id'          => $this->user->id,
        'jira_account_id'  => 'jira-acc-123',
    ]);

    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => 'jira-acc-123',
    ]);

    $result = $this->service->resolveTeamMember($issue);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($member->id);
});

test('resolveTeamMember returns null when no jira_account_id match', function (): void {
    TeamMember::factory()->create([
        'user_id'          => $this->user->id,
        'jira_account_id'  => null,
    ]);

    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => 'jira-acc-unknown',
    ]);

    $result = $this->service->resolveTeamMember($issue);

    expect($result)->toBeNull();
});

test('resolveTeamMember returns null when assignee account ID is null', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => null,
    ]);

    $result = $this->service->resolveTeamMember($issue);

    expect($result)->toBeNull();
});

test('resolveTeamMember falls back to email lookup via JiraUserService', function (): void {
    $member = TeamMember::factory()->create([
        'user_id'          => $this->user->id,
        'email'            => 'alice@example.com',
        'jira_account_id'  => null,
    ]);

    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => 'jira-acc-fallback',
    ]);

    $mockCloud = Mockery::mock(\App\Services\JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->andReturn(collect([
            ['accountId' => 'jira-acc-fallback', 'displayName' => 'Alice', 'emailAddress' => 'alice@example.com'],
        ]));

    $service = new JiraActionService($mockCloud);
    $result  = $service->resolveTeamMember($issue);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($member->id);

    $member->refresh();
    expect($member->jira_account_id)->toBe('jira-acc-fallback');
});

test('resolveTeamMember auto-populates jira_account_id on matched team member', function (): void {
    $member = TeamMember::factory()->create([
        'user_id'          => $this->user->id,
        'email'            => 'bob@example.com',
        'jira_account_id'  => null,
    ]);

    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => 'jira-acc-bob',
    ]);

    $mockCloud = Mockery::mock(\App\Services\JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->andReturn(collect([
            ['accountId' => 'jira-acc-bob', 'displayName' => 'Bob', 'emailAddress' => 'bob@example.com'],
        ]));

    $service = new JiraActionService($mockCloud);
    $service->resolveTeamMember($issue);

    $member->refresh();
    expect($member->jira_account_id)->toBe('jira-acc-bob');
});

test('resolveTeamMember email fallback returns null when email not available', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => 'jira-acc-private',
    ]);

    $mockCloud = Mockery::mock(\App\Services\JiraCloudService::class);
    $mockCloud->shouldReceive('fetchUsersBulk')
        ->once()
        ->andReturn(collect([
            ['accountId' => 'jira-acc-private', 'displayName' => 'Private User'],
        ]));

    $service = new JiraActionService($mockCloud);
    $result  = $service->resolveTeamMember($issue);

    expect($result)->toBeNull();
});

test('buildPrefillData returns correct task prefill', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary'       => 'Fix the login bug',
        'priority_name' => 'High',
    ]);

    $data = $this->service->buildPrefillData($issue, 'task');

    expect($data['title'])->toBe('Fix the login bug');
    expect($data['priority'])->toBe('high');
});

test('buildPrefillData maps Jira priorities to task priorities', function (string $jiraPriority, string $expectedPriority): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'priority_name' => $jiraPriority,
    ]);

    $data = $this->service->buildPrefillData($issue, 'task');

    expect($data['priority'])->toBe($expectedPriority);
})->with([
    ['Highest', 'urgent'],
    ['High', 'high'],
    ['Medium', 'normal'],
    ['Low', 'low'],
    ['Lowest', 'low'],
]);

test('buildPrefillData returns correct follow-up prefill', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary' => 'Follow up on deployment',
    ]);

    $data = $this->service->buildPrefillData($issue, 'follow-up');

    expect($data['description'])->toBe('Follow up on deployment');
    expect($data['follow_up_date'])->toBe(now()->addDays(3)->toDateString());
});

test('buildPrefillData returns correct note prefill', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'issue_key'          => 'PROJ-123',
        'summary'            => 'Sprint planning notes',
        'description_preview' => 'Some description text',
    ]);

    $data = $this->service->buildPrefillData($issue, 'note');

    expect($data['title'])->toBe('PROJ-123 Sprint planning notes');
    expect($data['content'])->toBe('Some description text');
});

test('buildPrefillData returns correct bila prefill with team member', function (): void {
    $member = TeamMember::factory()->create([
        'user_id'          => $this->user->id,
        'jira_account_id'  => 'jira-acc-123',
    ]);

    $issue = JiraIssue::factory()->for($this->user)->create([
        'summary'             => 'Discuss architecture',
        'assignee_account_id' => 'jira-acc-123',
    ]);

    $data = $this->service->buildPrefillData($issue, 'bila');

    expect($data['prep_item_content'])->toBe('Discuss architecture');
    expect($data['team_member_id'])->toBe($member->id);
});

test('buildPrefillData throws for bila without team member match', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create([
        'assignee_account_id' => 'no-match-acc',
    ]);

    $this->service->buildPrefillData($issue, 'bila');
})->throws(\InvalidArgumentException::class);

test('linkResource creates a JiraIssueLink', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create();
    $task = \App\Models\Task::factory()->for($this->user)->create();

    $link = $this->service->linkResource($issue, $task);

    expect($link)->not->toBeNull();
    expect($link->jira_issue_id)->toBe($issue->id);
    expect($link->linkable_type)->toBe(\App\Models\Task::class);
    expect($link->linkable_id)->toBe($task->id);
    expect($link->issue_key)->toBe($issue->issue_key);
    expect($link->issue_summary)->toBe($issue->summary);
});

test('linkResource prevents duplicate links', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create();
    $task = \App\Models\Task::factory()->for($this->user)->create();

    $link1 = $this->service->linkResource($issue, $task);
    $link2 = $this->service->linkResource($issue, $task);

    expect($link1->id)->toBe($link2->id);
    expect(\App\Models\JiraIssueLink::count())->toBe(1);
});

test('buildPrefillData throws for invalid type', function (): void {
    $issue = JiraIssue::factory()->for($this->user)->create();

    $this->service->buildPrefillData($issue, 'invalid');
})->throws(\InvalidArgumentException::class);
