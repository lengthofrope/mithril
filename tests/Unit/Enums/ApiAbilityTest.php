<?php

declare(strict_types=1);

use App\Enums\ApiAbility;

test('api ability enum has all expected cases', function () {
    $cases = ApiAbility::cases();

    expect($cases)->toHaveCount(28);

    $values = array_map(fn ($case) => $case->value, $cases);

    expect($values)->toContain('tasks:read')
        ->toContain('tasks:write')
        ->toContain('tasks:delete')
        ->toContain('teams:read')
        ->toContain('teams:write')
        ->toContain('teams:delete')
        ->toContain('team-members:read')
        ->toContain('team-members:write')
        ->toContain('team-members:delete')
        ->toContain('notes:read')
        ->toContain('notes:write')
        ->toContain('notes:delete')
        ->toContain('follow-ups:read')
        ->toContain('follow-ups:write')
        ->toContain('follow-ups:delete')
        ->toContain('meetings:read')
        ->toContain('meetings:write')
        ->toContain('meetings:delete')
        ->toContain('agreements:read')
        ->toContain('agreements:write')
        ->toContain('agreements:delete')
        ->toContain('activities:read')
        ->toContain('activities:write')
        ->toContain('activities:delete')
        ->toContain('search:read')
        ->toContain('counters:read')
        ->toContain('export:read')
        ->toContain('export:write');
});

test('api ability is a string-backed enum', function () {
    expect(ApiAbility::TasksRead->value)->toBe('tasks:read');
    expect(ApiAbility::TeamsWrite->value)->toBe('teams:write');
    expect(ApiAbility::NotesDelete->value)->toBe('notes:delete');
});

test('api ability can be instantiated from valid string value', function () {
    expect(ApiAbility::from('tasks:read'))->toBe(ApiAbility::TasksRead);
    expect(ApiAbility::from('meetings:write'))->toBe(ApiAbility::MeetingsWrite);
    expect(ApiAbility::from('search:read'))->toBe(ApiAbility::SearchRead);
});

test('api ability tryFrom returns null for invalid value', function () {
    expect(ApiAbility::tryFrom('invalid'))->toBeNull();
    expect(ApiAbility::tryFrom('tasks:execute'))->toBeNull();
    expect(ApiAbility::tryFrom('TASKS:READ'))->toBeNull();
});

test('api ability from throws value error for invalid value', function () {
    expect(fn () => ApiAbility::from('invalid:ability'))->toThrow(ValueError::class);
});

test('readAbilities returns only read abilities', function () {
    $readAbilities = ApiAbility::readAbilities();

    expect($readAbilities)->toBeArray();

    foreach ($readAbilities as $ability) {
        expect($ability)->toBeInstanceOf(ApiAbility::class);
        expect($ability->value)->toEndWith(':read');
    }

    expect($readAbilities)->toContain(ApiAbility::TasksRead)
        ->toContain(ApiAbility::TeamsRead)
        ->toContain(ApiAbility::SearchRead)
        ->toContain(ApiAbility::CountersRead)
        ->toContain(ApiAbility::ExportRead);
});

test('writeAbilities returns only write abilities', function () {
    $writeAbilities = ApiAbility::writeAbilities();

    expect($writeAbilities)->toBeArray();

    foreach ($writeAbilities as $ability) {
        expect($ability)->toBeInstanceOf(ApiAbility::class);
        expect($ability->value)->toEndWith(':write');
    }

    expect($writeAbilities)->toContain(ApiAbility::TasksWrite)
        ->toContain(ApiAbility::ExportWrite)
        ->not->toContain(ApiAbility::SearchRead)
        ->not->toContain(ApiAbility::CountersRead);
});

test('deleteAbilities returns only delete abilities', function () {
    $deleteAbilities = ApiAbility::deleteAbilities();

    expect($deleteAbilities)->toBeArray();

    foreach ($deleteAbilities as $ability) {
        expect($ability)->toBeInstanceOf(ApiAbility::class);
        expect($ability->value)->toEndWith(':delete');
    }

    expect($deleteAbilities)->toContain(ApiAbility::TasksDelete)
        ->toContain(ApiAbility::MeetingsDelete)
        ->not->toContain(ApiAbility::ExportWrite);
});

test('forResource returns all abilities for a given resource', function () {
    $taskAbilities = ApiAbility::forResource('tasks');

    expect($taskAbilities)->toHaveCount(3)
        ->toContain(ApiAbility::TasksRead)
        ->toContain(ApiAbility::TasksWrite)
        ->toContain(ApiAbility::TasksDelete);
});

test('forResource returns abilities for resource with limited actions', function () {
    $searchAbilities = ApiAbility::forResource('search');

    expect($searchAbilities)->toHaveCount(1)
        ->toContain(ApiAbility::SearchRead);

    $exportAbilities = ApiAbility::forResource('export');

    expect($exportAbilities)->toHaveCount(2)
        ->toContain(ApiAbility::ExportRead)
        ->toContain(ApiAbility::ExportWrite);
});

test('forResource returns empty array for unknown resource', function () {
    expect(ApiAbility::forResource('unknown'))->toBeEmpty();
});

test('groupedByResource returns abilities grouped by resource name', function () {
    $grouped = ApiAbility::groupedByResource();

    expect($grouped)->toBeArray()
        ->toHaveKey('tasks')
        ->toHaveKey('teams')
        ->toHaveKey('search')
        ->toHaveKey('counters')
        ->toHaveKey('export');

    expect($grouped['tasks'])->toHaveCount(3);
    expect($grouped['search'])->toHaveCount(1);
    expect($grouped['export'])->toHaveCount(2);
});
