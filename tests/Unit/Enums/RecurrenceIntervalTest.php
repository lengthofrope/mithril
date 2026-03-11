<?php

declare(strict_types=1);

use App\Enums\RecurrenceInterval;

test('recurrence interval enum has all expected cases', function () {
    $cases = RecurrenceInterval::cases();

    expect($cases)->toHaveCount(5);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Daily')
        ->toContain('Weekly')
        ->toContain('Biweekly')
        ->toContain('Monthly')
        ->toContain('Custom');
});

test('recurrence interval case daily has correct string value', function () {
    expect(RecurrenceInterval::Daily->value)->toBe('daily');
});

test('recurrence interval case weekly has correct string value', function () {
    expect(RecurrenceInterval::Weekly->value)->toBe('weekly');
});

test('recurrence interval case biweekly has correct string value', function () {
    expect(RecurrenceInterval::Biweekly->value)->toBe('biweekly');
});

test('recurrence interval case monthly has correct string value', function () {
    expect(RecurrenceInterval::Monthly->value)->toBe('monthly');
});

test('recurrence interval case custom has correct string value', function () {
    expect(RecurrenceInterval::Custom->value)->toBe('custom');
});

test('recurrence interval can be instantiated from valid string value', function () {
    expect(RecurrenceInterval::from('daily'))->toBe(RecurrenceInterval::Daily);
    expect(RecurrenceInterval::from('weekly'))->toBe(RecurrenceInterval::Weekly);
    expect(RecurrenceInterval::from('biweekly'))->toBe(RecurrenceInterval::Biweekly);
    expect(RecurrenceInterval::from('monthly'))->toBe(RecurrenceInterval::Monthly);
    expect(RecurrenceInterval::from('custom'))->toBe(RecurrenceInterval::Custom);
});

test('recurrence interval tryFrom returns null for invalid value', function () {
    expect(RecurrenceInterval::tryFrom('invalid'))->toBeNull();
    expect(RecurrenceInterval::tryFrom('bi-weekly'))->toBeNull();
    expect(RecurrenceInterval::tryFrom('DAILY'))->toBeNull();
});

test('recurrence interval from throws value error for invalid value', function () {
    expect(fn () => RecurrenceInterval::from('fortnightly'))->toThrow(ValueError::class);
});
