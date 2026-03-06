<?php

declare(strict_types=1);

use App\Enums\Priority;

test('priority enum has all expected cases', function () {
    $cases = Priority::cases();

    expect($cases)->toHaveCount(4);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Urgent')
        ->toContain('High')
        ->toContain('Normal')
        ->toContain('Low');
});

test('priority case urgent has correct string value', function () {
    expect(Priority::Urgent->value)->toBe('urgent');
});

test('priority case high has correct string value', function () {
    expect(Priority::High->value)->toBe('high');
});

test('priority case normal has correct string value', function () {
    expect(Priority::Normal->value)->toBe('normal');
});

test('priority case low has correct string value', function () {
    expect(Priority::Low->value)->toBe('low');
});

test('priority can be instantiated from valid string value', function () {
    expect(Priority::from('urgent'))->toBe(Priority::Urgent);
    expect(Priority::from('high'))->toBe(Priority::High);
    expect(Priority::from('normal'))->toBe(Priority::Normal);
    expect(Priority::from('low'))->toBe(Priority::Low);
});

test('priority tryFrom returns null for invalid value', function () {
    expect(Priority::tryFrom('invalid'))->toBeNull();
    expect(Priority::tryFrom(''))->toBeNull();
    expect(Priority::tryFrom('URGENT'))->toBeNull();
});

test('priority from throws value error for invalid value', function () {
    expect(fn () => Priority::from('critical'))->toThrow(ValueError::class);
});
