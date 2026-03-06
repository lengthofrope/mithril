<?php

declare(strict_types=1);

use App\Enums\TaskStatus;

test('task status enum has all expected cases', function () {
    $cases = TaskStatus::cases();

    expect($cases)->toHaveCount(4);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Open')
        ->toContain('InProgress')
        ->toContain('Waiting')
        ->toContain('Done');
});

test('task status case open has correct string value', function () {
    expect(TaskStatus::Open->value)->toBe('open');
});

test('task status case in_progress has correct string value', function () {
    expect(TaskStatus::InProgress->value)->toBe('in_progress');
});

test('task status case waiting has correct string value', function () {
    expect(TaskStatus::Waiting->value)->toBe('waiting');
});

test('task status case done has correct string value', function () {
    expect(TaskStatus::Done->value)->toBe('done');
});

test('task status can be instantiated from valid string value', function () {
    expect(TaskStatus::from('open'))->toBe(TaskStatus::Open);
    expect(TaskStatus::from('in_progress'))->toBe(TaskStatus::InProgress);
    expect(TaskStatus::from('waiting'))->toBe(TaskStatus::Waiting);
    expect(TaskStatus::from('done'))->toBe(TaskStatus::Done);
});

test('task status tryFrom returns null for invalid value', function () {
    expect(TaskStatus::tryFrom('invalid'))->toBeNull();
    expect(TaskStatus::tryFrom('in-progress'))->toBeNull();
    expect(TaskStatus::tryFrom('DONE'))->toBeNull();
});

test('task status from throws value error for invalid value', function () {
    expect(fn () => TaskStatus::from('pending'))->toThrow(ValueError::class);
});
