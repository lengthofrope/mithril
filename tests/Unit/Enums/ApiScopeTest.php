<?php

declare(strict_types=1);

use App\Enums\ApiAbility;
use App\Enums\ApiScope;

test('api scope enum has exactly 3 cases', function () {
    $cases = ApiScope::cases();

    expect($cases)->toHaveCount(3);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('ReadOnly')
        ->toContain('ReadWrite')
        ->toContain('FullAccess');
});

test('api scope is a string-backed enum', function () {
    expect(ApiScope::ReadOnly->value)->toBe('read-only');
    expect(ApiScope::ReadWrite->value)->toBe('read-write');
    expect(ApiScope::FullAccess->value)->toBe('full-access');
});

test('api scope can be instantiated from valid string value', function () {
    expect(ApiScope::from('read-only'))->toBe(ApiScope::ReadOnly);
    expect(ApiScope::from('read-write'))->toBe(ApiScope::ReadWrite);
    expect(ApiScope::from('full-access'))->toBe(ApiScope::FullAccess);
});

test('api scope tryFrom returns null for invalid value', function () {
    expect(ApiScope::tryFrom('invalid'))->toBeNull();
    expect(ApiScope::tryFrom('READ-ONLY'))->toBeNull();
});

test('api scope from throws value error for invalid value', function () {
    expect(fn () => ApiScope::from('admin'))->toThrow(ValueError::class);
});

test('read-only scope contains only read abilities', function () {
    $abilities = ApiScope::ReadOnly->abilities();

    expect($abilities)->toBeArray();

    foreach ($abilities as $ability) {
        expect($ability)->toBeInstanceOf(ApiAbility::class);
        expect($ability->value)->toEndWith(':read');
    }

    expect($abilities)->toEqualCanonicalizing(ApiAbility::readAbilities());
});

test('read-write scope contains all read and write abilities', function () {
    $abilities = ApiScope::ReadWrite->abilities();

    expect($abilities)->toBeArray();

    $expectedAbilities = array_merge(ApiAbility::readAbilities(), ApiAbility::writeAbilities());
    expect($abilities)->toEqualCanonicalizing($expectedAbilities);

    foreach ($abilities as $ability) {
        expect($ability->value)->not->toEndWith(':delete');
    }
});

test('full-access scope contains all abilities', function () {
    $abilities = ApiScope::FullAccess->abilities();

    expect($abilities)->toEqualCanonicalizing(ApiAbility::cases());
});

test('each scope is a superset of the previous tier', function () {
    $readOnly = ApiScope::ReadOnly->abilities();
    $readWrite = ApiScope::ReadWrite->abilities();
    $fullAccess = ApiScope::FullAccess->abilities();

    foreach ($readOnly as $ability) {
        expect($readWrite)->toContain($ability);
        expect($fullAccess)->toContain($ability);
    }

    foreach ($readWrite as $ability) {
        expect($fullAccess)->toContain($ability);
    }
});

test('api scope abilityValues returns string array', function () {
    $values = ApiScope::ReadOnly->abilityValues();

    expect($values)->toBeArray();

    foreach ($values as $value) {
        expect($value)->toBeString();
    }

    expect($values)->toContain('tasks:read')
        ->toContain('search:read')
        ->not->toContain('tasks:write');
});
