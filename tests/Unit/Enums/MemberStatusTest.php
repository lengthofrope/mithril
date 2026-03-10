<?php

declare(strict_types=1);

use App\Enums\MemberStatus;

test('member status enum has all expected cases', function () {
    $cases = MemberStatus::cases();

    expect($cases)->toHaveCount(5);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Available')
        ->toContain('PartiallyAvailable')
        ->toContain('WorkingElsewhere')
        ->toContain('InAMeeting')
        ->toContain('Absent');
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

test('member status case working elsewhere has correct string value', function () {
    expect(MemberStatus::WorkingElsewhere->value)->toBe('working_elsewhere');
});

test('member status case in a meeting has correct string value', function () {
    expect(MemberStatus::InAMeeting->value)->toBe('in_a_meeting');
});

test('member status can be instantiated from valid string value', function () {
    expect(MemberStatus::from('available'))->toBe(MemberStatus::Available);
    expect(MemberStatus::from('partially_available'))->toBe(MemberStatus::PartiallyAvailable);
    expect(MemberStatus::from('working_elsewhere'))->toBe(MemberStatus::WorkingElsewhere);
    expect(MemberStatus::from('in_a_meeting'))->toBe(MemberStatus::InAMeeting);
    expect(MemberStatus::from('absent'))->toBe(MemberStatus::Absent);
});

test('member status tryFrom returns null for invalid value', function () {
    expect(MemberStatus::tryFrom('invalid'))->toBeNull();
    expect(MemberStatus::tryFrom('AVAILABLE'))->toBeNull();
    expect(MemberStatus::tryFrom('partial'))->toBeNull();
});

test('member status from throws value error for invalid value', function () {
    expect(fn () => MemberStatus::from('online'))->toThrow(ValueError::class);
});
