<?php

declare(strict_types=1);

use App\Enums\StatusSource;

test('StatusSource has expected cases', function () {
    expect(StatusSource::cases())->toHaveCount(2);
});

test('StatusSource case Manual has correct string value', function () {
    expect(StatusSource::Manual->value)->toBe('manual');
});

test('StatusSource case Microsoft has correct string value', function () {
    expect(StatusSource::Microsoft->value)->toBe('microsoft');
});

test('StatusSource can be instantiated from valid string value', function () {
    expect(StatusSource::from('manual'))->toBe(StatusSource::Manual)
        ->and(StatusSource::from('microsoft'))->toBe(StatusSource::Microsoft);
});

test('StatusSource tryFrom returns null for invalid value', function () {
    expect(StatusSource::tryFrom('invalid'))->toBeNull()
        ->and(StatusSource::tryFrom('Manual'))->toBeNull()
        ->and(StatusSource::tryFrom(''))->toBeNull();
});
