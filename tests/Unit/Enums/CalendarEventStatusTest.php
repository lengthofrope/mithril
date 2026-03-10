<?php

declare(strict_types=1);

use App\Enums\CalendarEventStatus;

test('CalendarEventStatus has expected cases', function () {
    expect(CalendarEventStatus::cases())->toHaveCount(5);
});

test('CalendarEventStatus case Free has correct string value', function () {
    expect(CalendarEventStatus::Free->value)->toBe('free');
});

test('CalendarEventStatus case Tentative has correct string value', function () {
    expect(CalendarEventStatus::Tentative->value)->toBe('tentative');
});

test('CalendarEventStatus case Busy has correct string value', function () {
    expect(CalendarEventStatus::Busy->value)->toBe('busy');
});

test('CalendarEventStatus case OutOfOffice has correct string value', function () {
    expect(CalendarEventStatus::OutOfOffice->value)->toBe('oof');
});

test('CalendarEventStatus case WorkingElsewhere has correct string value', function () {
    expect(CalendarEventStatus::WorkingElsewhere->value)->toBe('workingElsewhere');
});

test('CalendarEventStatus can be instantiated from valid string value', function () {
    expect(CalendarEventStatus::from('free'))->toBe(CalendarEventStatus::Free)
        ->and(CalendarEventStatus::from('tentative'))->toBe(CalendarEventStatus::Tentative)
        ->and(CalendarEventStatus::from('busy'))->toBe(CalendarEventStatus::Busy)
        ->and(CalendarEventStatus::from('oof'))->toBe(CalendarEventStatus::OutOfOffice)
        ->and(CalendarEventStatus::from('workingElsewhere'))->toBe(CalendarEventStatus::WorkingElsewhere);
});

test('CalendarEventStatus tryFrom returns null for invalid value', function () {
    expect(CalendarEventStatus::tryFrom('invalid'))->toBeNull()
        ->and(CalendarEventStatus::tryFrom('Busy'))->toBeNull()
        ->and(CalendarEventStatus::tryFrom('out_of_office'))->toBeNull();
});
