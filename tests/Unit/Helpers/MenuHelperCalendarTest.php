<?php

declare(strict_types=1);

use App\Helpers\MenuHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('MenuHelper calendar item', function (): void {
    it('includes a Calendar menu item when user has Microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);
        $this->actingAs($user);

        $items = MenuHelper::getMainNavItems();

        $calendarItem = collect($items)->firstWhere('name', 'Calendar');
        expect($calendarItem)->not->toBeNull();
        expect($calendarItem['path'])->toBe('/calendar');
        expect($calendarItem['icon'])->toBe('calendar');
    });

    it('excludes the Calendar menu item when user has no Microsoft connection', function (): void {
        $user = User::factory()->create(['microsoft_id' => null]);
        $this->actingAs($user);

        $items = MenuHelper::getMainNavItems();

        $calendarItem = collect($items)->firstWhere('name', 'Calendar');
        expect($calendarItem)->toBeNull();
    });

    it('excludes the Calendar menu item when no user is authenticated', function (): void {
        $items = MenuHelper::getMainNavItems();

        $calendarItem = collect($items)->firstWhere('name', 'Calendar');
        expect($calendarItem)->toBeNull();
    });

    it('places the Calendar item after the core workflow section', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);
        $this->actingAs($user);

        $items = MenuHelper::getMainNavItems();
        $nonSeparatorNames = collect($items)
            ->reject(fn (array $item): bool => !empty($item['separator']))
            ->pluck('name')
            ->values()
            ->all();

        $notesIndex = array_search('Notes', $nonSeparatorNames);
        $calendarIndex = array_search('Calendar', $nonSeparatorNames);

        expect($calendarIndex)->toBeGreaterThan($notesIndex);
    });

    it('uses a unique meeting icon for Meetings menu item', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);
        $this->actingAs($user);

        $items = MenuHelper::getMainNavItems();

        $meetingsItem = collect($items)->firstWhere('name', 'Meetings');
        $calendarItem = collect($items)->firstWhere('name', 'Calendar');

        expect($meetingsItem['icon'])->toBe('meeting');
        expect($calendarItem['icon'])->toBe('calendar');
        expect($meetingsItem['icon'])->not->toBe($calendarItem['icon']);
    });
});
