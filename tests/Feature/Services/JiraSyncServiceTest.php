<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\User;
use App\Services\JiraCloudService;
use App\Services\JiraSyncService;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->user = User::factory()->create([
        'jira_cloud_id'          => 'cloud-123',
        'jira_account_id'        => 'account-abc',
        'jira_access_token'      => 'valid-token',
        'jira_refresh_token'     => 'valid-refresh',
        'jira_token_expires_at'  => now()->addHour(),
    ]);
});

test('syncIssues upserts assigned issues into jira_issues table', function () {
    $mockService = Mockery::mock(JiraCloudService::class);

    $mockService->shouldReceive('searchIssues')
        ->times(3)
        ->andReturn(
            collect([makeRawIssue('10001', 'PROJ-1', 'First issue')]),
            collect([]),
            collect([]),
        );

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);

    $issues = JiraIssue::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->get();

    expect($issues)->toHaveCount(1)
        ->and($issues->first()->issue_key)->toBe('PROJ-1')
        ->and($issues->first()->sources)->toContain('assigned');
});

test('syncIssues stores account IDs instead of personal data', function () {
    $mockService = Mockery::mock(JiraCloudService::class);

    $mockService->shouldReceive('searchIssues')
        ->times(3)
        ->andReturn(
            collect([makeRawIssue('10001', 'PROJ-1', 'Test issue')]),
            collect([]),
            collect([]),
        );

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);

    $issue = JiraIssue::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->first();

    expect($issue->assignee_account_id)->toBe('assignee-acc-123')
        ->and($issue->reporter_account_id)->toBe('reporter-acc-456');
});

test('syncIssues constructs web_url from user jira_site_url', function () {
    $this->user->update(['jira_site_url' => 'https://mysite.atlassian.net']);

    $mockService = Mockery::mock(JiraCloudService::class);

    $mockService->shouldReceive('searchIssues')
        ->times(3)
        ->andReturn(
            collect([makeRawIssue('10001', 'PROJ-1', 'Test issue')]),
            collect([]),
            collect([]),
        );

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);

    $issue = JiraIssue::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->first();

    expect($issue->web_url)->toBe('https://mysite.atlassian.net/browse/PROJ-1');
});

test('syncIssues merges sources when issue matches multiple queries', function () {
    $mockService = Mockery::mock(JiraCloudService::class);

    $issue = makeRawIssue('10001', 'PROJ-1', 'Shared issue');

    $mockService->shouldReceive('searchIssues')
        ->times(3)
        ->andReturn(
            collect([$issue]),
            collect([$issue]),
            collect([]),
        );

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);

    $dbIssue = JiraIssue::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->first();

    expect($dbIssue->sources)->toContain('assigned')
        ->and($dbIssue->sources)->toContain('mentioned');
});

test('syncIssues removes stale issues not in sync set', function () {
    $mockService = Mockery::mock(JiraCloudService::class);

    JiraIssue::withoutGlobalScopes()->create([
        'user_id'            => $this->user->id,
        'jira_issue_id'      => 'stale-issue',
        'issue_key'          => 'PROJ-99',
        'summary'            => 'Stale issue',
        'project_key'        => 'PROJ',
        'project_name'       => 'Project',
        'issue_type'         => 'Task',
        'status_name'        => 'Open',
        'status_category'    => 'new',
        'web_url'            => 'https://jira.example.com/browse/PROJ-99',
        'sources'            => ['assigned'],
        'updated_in_jira_at' => now()->subDay(),
        'synced_at'          => now()->subDay(),
    ]);

    $mockService->shouldReceive('searchIssues')
        ->times(3)
        ->andReturn(
            collect([makeRawIssue('10001', 'PROJ-1', 'Fresh issue')]),
            collect([]),
            collect([]),
        );

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);

    $issues = JiraIssue::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->get();

    expect($issues)->toHaveCount(1)
        ->and($issues->first()->issue_key)->toBe('PROJ-1');
});

test('syncIssues preserves dismissed issues even when not in sync set', function () {
    $mockService = Mockery::mock(JiraCloudService::class);

    JiraIssue::withoutGlobalScopes()->create([
        'user_id'            => $this->user->id,
        'jira_issue_id'      => 'dismissed-issue',
        'issue_key'          => 'PROJ-50',
        'summary'            => 'Dismissed issue',
        'project_key'        => 'PROJ',
        'project_name'       => 'Project',
        'issue_type'         => 'Bug',
        'status_name'        => 'Open',
        'status_category'    => 'new',
        'web_url'            => 'https://jira.example.com/browse/PROJ-50',
        'sources'            => ['assigned'],
        'is_dismissed'       => true,
        'updated_in_jira_at' => now()->subDay(),
        'synced_at'          => now()->subDay(),
    ]);

    $mockService->shouldReceive('searchIssues')
        ->times(3)
        ->andReturn(collect([]), collect([]), collect([]));

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);

    $dismissed = JiraIssue::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->where('jira_issue_id', 'dismissed-issue')
        ->first();

    expect($dismissed)->not->toBeNull()
        ->and($dismissed->is_dismissed)->toBeTrue();
});

test('syncIssues preserves is_dismissed flag when issue reappears in sync', function () {
    $mockService = Mockery::mock(JiraCloudService::class);

    JiraIssue::withoutGlobalScopes()->create([
        'user_id'            => $this->user->id,
        'jira_issue_id'      => '10001',
        'issue_key'          => 'PROJ-1',
        'summary'            => 'Old summary',
        'project_key'        => 'PROJ',
        'project_name'       => 'Project',
        'issue_type'         => 'Task',
        'status_name'        => 'Open',
        'status_category'    => 'new',
        'web_url'            => 'https://jira.example.com/browse/PROJ-1',
        'sources'            => ['assigned'],
        'is_dismissed'       => true,
        'updated_in_jira_at' => now()->subDay(),
        'synced_at'          => now()->subDay(),
    ]);

    $mockService->shouldReceive('searchIssues')
        ->times(3)
        ->andReturn(
            collect([makeRawIssue('10001', 'PROJ-1', 'Updated summary')]),
            collect([]),
            collect([]),
        );

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);

    $issue = JiraIssue::withoutGlobalScopes()
        ->where('user_id', $this->user->id)
        ->where('jira_issue_id', '10001')
        ->first();

    expect($issue->summary)->toBe('Updated summary')
        ->and($issue->is_dismissed)->toBeTrue();
});

test('syncIssues limits total issues to max_issues_per_sync config', function () {
    config(['jira.max_issues_per_sync' => 250]);

    $mockService = Mockery::mock(JiraCloudService::class);

    $mockService->shouldReceive('searchIssues')
        ->with($this->user, Mockery::any(), Mockery::on(fn ($val) => $val <= 250))
        ->times(3)
        ->andReturn(collect([]), collect([]), collect([]));

    $syncService = new JiraSyncService($mockService);
    $syncService->syncIssues($this->user);
});

/**
 * Helper to create a raw Jira API issue array matching what JiraCloudService returns.
 */
function makeRawIssue(string $id, string $key, string $summary): array
{
    return [
        'id'     => $id,
        'key'    => $key,
        'self'   => "https://jira.example.com/rest/api/3/issue/{$id}",
        'fields' => [
            'summary'     => $summary,
            'description' => ['content' => [['content' => [['text' => 'Some description text']]]]],
            'project'     => ['key' => explode('-', $key)[0], 'name' => 'Project'],
            'issuetype'   => ['name' => 'Task'],
            'status'      => ['name' => 'Open', 'statusCategory' => ['key' => 'new']],
            'priority'    => ['name' => 'Medium'],
            'assignee'    => ['accountId' => 'assignee-acc-123', 'displayName' => 'John Doe', 'emailAddress' => 'john@example.com'],
            'reporter'    => ['accountId' => 'reporter-acc-456', 'displayName' => 'Jane Doe', 'emailAddress' => 'jane@example.com'],
            'labels'      => ['backend'],
            'updated'     => now()->toIso8601String(),
        ],
    ];
}
