<?php

declare(strict_types=1);

use App\Helpers\MenuHelper;
use App\Models\Team;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create(['microsoft_id' => 'ms-123']);
    $this->actingAs($this->user);
});

it('includes calendar and email items when user has microsoft connection', function (): void {
    $items = MenuHelper::getMainNavItems();
    $names = collect($items)->pluck('name')->filter()->values()->toArray();

    expect($names)->toContain('Calendar');
    expect($names)->toContain('E-mail');
});

it('excludes calendar and email items when user has no microsoft connection', function (): void {
    $user = User::factory()->create(['microsoft_id' => null]);
    $this->actingAs($user);

    $items = MenuHelper::getMainNavItems();
    $names = collect($items)->pluck('name')->filter()->values()->toArray();

    expect($names)->not->toContain('Calendar');
    expect($names)->not->toContain('E-mail');
});

it('contains no adjacent separators', function (): void {
    $items = MenuHelper::getMainNavItems();
    $previousWasSeparator = false;

    foreach ($items as $item) {
        $isSeparator = !empty($item['separator']);
        expect($isSeparator && $previousWasSeparator)->toBeFalse();
        $previousWasSeparator = $isSeparator;
    }
});

it('does not start or end with a separator', function (): void {
    $items = MenuHelper::getMainNavItems();

    expect($items)->not->toBeEmpty();
    expect($items[0])->not->toHaveKey('separator');
    expect(end($items))->not->toHaveKey('separator');
});

it('builds teams item with sub-items when teams exist', function (): void {
    Team::factory()->create(['name' => 'Alpha']);
    Team::factory()->create(['name' => 'Beta']);

    $items = MenuHelper::getMainNavItems();
    $teamsItem = collect($items)->firstWhere('name', 'Teams');

    expect($teamsItem)->not->toBeNull();
    expect($teamsItem)->toHaveKey('subItems');
    expect($teamsItem['subItems'])->toHaveCount(3);
    expect($teamsItem['subItems'][0]['name'])->toBe('All Teams');
});

it('builds teams item as simple link when no teams exist', function (): void {
    $items = MenuHelper::getMainNavItems();
    $teamsItem = collect($items)->firstWhere('name', 'Teams');

    expect($teamsItem)->not->toBeNull();
    expect($teamsItem)->toHaveKey('path');
    expect($teamsItem)->not->toHaveKey('subItems');
});

it('returns email icon svg', function (): void {
    $svg = MenuHelper::getIconSvg('email');

    expect($svg)->toContain('<svg');
    expect($svg)->toContain('</svg>');
});

it('returns fallback svg for unknown icon', function (): void {
    $svg = MenuHelper::getIconSvg('nonexistent');

    expect($svg)->toContain('<circle');
});

it('email item path is /mail', function (): void {
    $items = MenuHelper::getMainNavItems();
    $emailItem = collect($items)->firstWhere('name', 'E-mail');

    expect($emailItem['path'])->toBe('/mail');
});

it('includes all expected navigation items', function (): void {
    $items = MenuHelper::getMainNavItems();
    $names = collect($items)->pluck('name')->filter()->values()->toArray();

    expect($names)->toContain('Dashboard');
    expect($names)->toContain('Tasks');
    expect($names)->toContain('Follow-ups');
    expect($names)->toContain('Notes');
    expect($names)->toContain("Bila's");
    expect($names)->toContain('Teams');
    expect($names)->toContain('Weekly Review');
    expect($names)->toContain('Analytics');
});
