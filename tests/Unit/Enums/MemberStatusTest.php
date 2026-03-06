<?php

declare(strict_types=1);

use App\Enums\MemberStatus;

test('member status enum has all expected cases', function () {
    $cases = MemberStatus::cases();

    expect($cases)->toHaveCount(3);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Available')
        ->toContain('Absent')
        ->toContain('PartiallyAvailable');
});

test('member status case available has correct string value', function () {
    expect(MemberStatus::Available->value)->toBe('available');
});

test('member status case absent has correct string value', function () {
    expect(MemberStatus::Absent->value)->toBe('absent');
});

test('member status case partially available has correct string value', function () {
    expect(MemberStatus::PartiallyAvailable->value)->toBe('partially_available');
});

test('member status can be instantiated from valid string value', function () {
    expect(MemberStatus::from('available'))->toBe(MemberStatus::Available);
    expect(MemberStatus::from('absent'))->toBe(MemberStatus::Absent);
    expect(MemberStatus::from('partially_available'))->toBe(MemberStatus::PartiallyAvailable);
});

test('member status tryFrom returns null for invalid value', function () {
    expect(MemberStatus::tryFrom('invalid'))->toBeNull();
    expect(MemberStatus::tryFrom('AVAILABLE'))->toBeNull();
    expect(MemberStatus::tryFrom('partial'))->toBeNull();
});

test('member status from throws value error for invalid value', function () {
    expect(fn () => MemberStatus::from('online'))->toThrow(ValueError::class);
});
