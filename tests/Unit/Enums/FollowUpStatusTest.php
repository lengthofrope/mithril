<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;

test('follow up status enum has all expected cases', function () {
    $cases = FollowUpStatus::cases();

    expect($cases)->toHaveCount(3);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Open')
        ->toContain('Snoozed')
        ->toContain('Done');
});

test('follow up status case open has correct string value', function () {
    expect(FollowUpStatus::Open->value)->toBe('open');
});

test('follow up status case snoozed has correct string value', function () {
    expect(FollowUpStatus::Snoozed->value)->toBe('snoozed');
});

test('follow up status case done has correct string value', function () {
    expect(FollowUpStatus::Done->value)->toBe('done');
});

test('follow up status can be instantiated from valid string value', function () {
    expect(FollowUpStatus::from('open'))->toBe(FollowUpStatus::Open);
    expect(FollowUpStatus::from('snoozed'))->toBe(FollowUpStatus::Snoozed);
    expect(FollowUpStatus::from('done'))->toBe(FollowUpStatus::Done);
});

test('follow up status tryFrom returns null for invalid value', function () {
    expect(FollowUpStatus::tryFrom('invalid'))->toBeNull();
    expect(FollowUpStatus::tryFrom('OPEN'))->toBeNull();
    expect(FollowUpStatus::tryFrom('closed'))->toBeNull();
});

test('follow up status from throws value error for invalid value', function () {
    expect(fn () => FollowUpStatus::from('pending'))->toThrow(ValueError::class);
});
