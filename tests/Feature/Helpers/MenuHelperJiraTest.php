<?php

declare(strict_types=1);

use App\Helpers\MenuHelper;
use App\Models\User;

test('navigation includes jira link when user has jira connection', function (): void {
    $user = User::factory()->create([
        'jira_cloud_id'        => 'test-cloud-id',
        'jira_access_token'    => 'test-token',
        'jira_refresh_token'   => 'test-refresh',
        'jira_token_expires_at' => now()->addHour(),
    ]);

    $this->actingAs($user);

    $items = MenuHelper::getMainNavItems();
    $jiraItem = collect($items)->first(fn ($item) => ($item['name'] ?? '') === 'Jira');

    expect($jiraItem)->not->toBeNull();
    expect($jiraItem['path'])->toBe('/jira');
});

test('navigation excludes jira link when user has no jira connection', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $items = MenuHelper::getMainNavItems();
    $jiraItem = collect($items)->first(fn ($item) => ($item['name'] ?? '') === 'Jira');

    expect($jiraItem)->toBeNull();
});

test('jira icon svg is defined', function (): void {
    $svg = MenuHelper::getIconSvg('jira');
    expect($svg)->toContain('svg');
    expect($svg)->not->toContain('circle cx="12" cy="12" r="9"'); // Not the fallback
});
