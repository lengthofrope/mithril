<?php

declare(strict_types=1);

use App\Models\JiraIssue;
use App\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'jira_cloud_id'         => 'test-cloud-id',
        'jira_access_token'     => 'test-token',
        'jira_refresh_token'    => 'test-refresh',
        'jira_token_expires_at' => now()->addHour(),
    ]);
});

describe('Jira page default source filter', function (): void {
    it('defaults to assigned source when no source param is provided', function (): void {
        JiraIssue::factory()->for($this->user)->create(['sources' => ['assigned'], 'status_category' => 'new']);
        JiraIssue::factory()->for($this->user)->create(['sources' => ['mentioned'], 'status_category' => 'new']);
        JiraIssue::factory()->for($this->user)->create(['sources' => ['watched'], 'status_category' => 'new']);

        $response = $this->actingAs($this->user)->get('/jira');

        $issues = $response->viewData('issues');
        expect($issues)->toHaveCount(1);
        expect($issues->first()->sources)->toContain('assigned');
    });

    it('shows only mentioned issues when source=mentioned is requested', function (): void {
        JiraIssue::factory()->for($this->user)->create(['sources' => ['assigned'], 'status_category' => 'new']);
        JiraIssue::factory()->for($this->user)->create(['sources' => ['mentioned'], 'status_category' => 'new']);

        $response = $this->actingAs($this->user)->get('/jira?source=mentioned');

        $issues = $response->viewData('issues');
        expect($issues)->toHaveCount(1);
        expect($issues->first()->sources)->toContain('mentioned');
    });

    it('shows only watched issues when source=watched is requested', function (): void {
        JiraIssue::factory()->for($this->user)->create(['sources' => ['assigned'], 'status_category' => 'new']);
        JiraIssue::factory()->for($this->user)->create(['sources' => ['watched'], 'status_category' => 'new']);

        $response = $this->actingAs($this->user)->get('/jira?source=watched');

        $issues = $response->viewData('issues');
        expect($issues)->toHaveCount(1);
        expect($issues->first()->sources)->toContain('watched');
    });
});

describe('Jira page done issues hidden by default', function (): void {
    it('excludes done issues when no status_category is selected', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'new',
        ]);
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'done',
        ]);

        $response = $this->actingAs($this->user)->get('/jira');

        $issues = $response->viewData('issues');
        expect($issues)->toHaveCount(1);
        expect($issues->first()->status_category)->toBe('new');
    });

    it('shows done issues when status_category=done is explicitly selected', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'new',
        ]);
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'done',
        ]);

        $response = $this->actingAs($this->user)->get('/jira?source=assigned&status_category=done');

        $issues = $response->viewData('issues');
        expect($issues)->toHaveCount(1);
        expect($issues->first()->status_category)->toBe('done');
    });

    it('shows open issues when status_category=new is explicitly selected', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'new',
        ]);
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'indeterminate',
        ]);

        $response = $this->actingAs($this->user)->get('/jira?source=assigned&status_category=new');

        $issues = $response->viewData('issues');
        expect($issues)->toHaveCount(1);
        expect($issues->first()->status_category)->toBe('new');
    });

    it('excludes done issues even with in-progress filter when done is not selected', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'indeterminate',
        ]);
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'done',
        ]);

        $response = $this->actingAs($this->user)->get('/jira?source=assigned&status_category=indeterminate');

        $issues = $response->viewData('issues');
        expect($issues)->toHaveCount(1);
        expect($issues->first()->status_category)->toBe('indeterminate');
    });
});

describe('Jira page no All tab', function (): void {
    it('does not render an All tab link', function (): void {
        JiraIssue::factory()->for($this->user)->create(['sources' => ['assigned']]);

        $this->actingAs($this->user)
            ->get('/jira')
            ->assertOk()
            ->assertDontSee('>All</a>', false);
    });
});

describe('Jira page project options respect active filters', function (): void {
    it('only lists projects that have issues matching the active source filter', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'new',
            'project_key'     => 'ALPHA',
            'project_name'    => 'Alpha Project',
        ]);
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['mentioned'],
            'status_category' => 'new',
            'project_key'     => 'BETA',
            'project_name'    => 'Beta Project',
        ]);

        $response = $this->actingAs($this->user)->get('/jira?source=assigned');

        $projectOptions = $response->viewData('projectOptions');
        $values = array_column($projectOptions, 'value');
        expect($values)->toContain('ALPHA');
        expect($values)->not->toContain('BETA');
    });

    it('excludes projects that only have done issues when done is not selected', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'new',
            'project_key'     => 'ACTIVE',
            'project_name'    => 'Active Project',
        ]);
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'done',
            'project_key'     => 'FINISHED',
            'project_name'    => 'Finished Project',
        ]);

        $response = $this->actingAs($this->user)->get('/jira');

        $projectOptions = $response->viewData('projectOptions');
        $values = array_column($projectOptions, 'value');
        expect($values)->toContain('ACTIVE');
        expect($values)->not->toContain('FINISHED');
    });

    it('includes done projects when status_category=done is selected', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'done',
            'project_key'     => 'FINISHED',
            'project_name'    => 'Finished Project',
        ]);

        $response = $this->actingAs($this->user)->get('/jira?source=assigned&status_category=done');

        $projectOptions = $response->viewData('projectOptions');
        $values = array_column($projectOptions, 'value');
        expect($values)->toContain('FINISHED');
    });

    it('keeps the selected project in the dropdown even when no issues match the other filters', function (): void {
        JiraIssue::factory()->for($this->user)->create([
            'sources'         => ['assigned'],
            'status_category' => 'new',
            'project_key'     => 'PROJ',
            'project_name'    => 'My Project',
        ]);

        $response = $this->actingAs($this->user)->get('/jira?project_key=PROJ&status_category=indeterminate');

        $projectOptions = $response->viewData('projectOptions');
        $values = array_column($projectOptions, 'value');
        expect($values)->toContain('PROJ');
    });
});
