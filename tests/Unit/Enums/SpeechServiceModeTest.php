<?php

declare(strict_types=1);

use App\Enums\SpeechServiceMode;

test('speech service mode enum has all expected cases', function () {
    $cases = SpeechServiceMode::cases();

    expect($cases)->toHaveCount(2);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Server')
        ->toContain('Local');
});

test('speech service mode server has correct string value', function () {
    expect(SpeechServiceMode::Server->value)->toBe('server');
});

test('speech service mode local has correct string value', function () {
    expect(SpeechServiceMode::Local->value)->toBe('local');
});

test('speech service mode can be instantiated from valid string value', function () {
    expect(SpeechServiceMode::from('server'))->toBe(SpeechServiceMode::Server);
    expect(SpeechServiceMode::from('local'))->toBe(SpeechServiceMode::Local);
});

test('speech service mode tryFrom returns null for invalid value', function () {
    expect(SpeechServiceMode::tryFrom('invalid'))->toBeNull();
    expect(SpeechServiceMode::tryFrom('SERVER'))->toBeNull();
});

test('speech service mode from throws value error for invalid value', function () {
    expect(fn () => SpeechServiceMode::from('invalid'))->toThrow(ValueError::class);
});
