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

    it('places the Calendar item right after Dashboard', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);
        $this->actingAs($user);

        $items = MenuHelper::getMainNavItems();
        $names = collect($items)->pluck('name')->values()->all();

        $dashboardIndex = array_search('Dashboard', $names);
        $calendarIndex = array_search('Calendar', $names);

        expect($calendarIndex)->toBe($dashboardIndex + 1);
    });

    it('uses a unique bila icon for Bilas menu item', function (): void {
        $user = User::factory()->create(['microsoft_id' => 'ms-123']);
        $this->actingAs($user);

        $items = MenuHelper::getMainNavItems();

        $bilasItem = collect($items)->firstWhere('name', "Bila's");
        $calendarItem = collect($items)->firstWhere('name', 'Calendar');

        expect($bilasItem['icon'])->toBe('bila');
        expect($calendarItem['icon'])->toBe('calendar');
        expect($bilasItem['icon'])->not->toBe($calendarItem['icon']);
    });
});
