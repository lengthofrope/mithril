<?php

declare(strict_types=1);

use App\Enums\ChartType;

test('chart type enum has exactly 5 cases', function () {
    $cases = ChartType::cases();

    expect($cases)->toHaveCount(5);

    $names = array_map(fn ($case) => $case->name, $cases);
    expect($names)->toContain('Donut')
        ->toContain('Bar')
        ->toContain('BarHorizontal')
        ->toContain('StackedBar')
        ->toContain('Line');
});

test('chart type case donut has correct string value', function () {
    expect(ChartType::Donut->value)->toBe('donut');
});

test('chart type case bar has correct string value', function () {
    expect(ChartType::Bar->value)->toBe('bar');
});

test('chart type case bar horizontal has correct string value', function () {
    expect(ChartType::BarHorizontal->value)->toBe('bar_horizontal');
});

test('chart type case stacked bar has correct string value', function () {
    expect(ChartType::StackedBar->value)->toBe('stacked_bar');
});

test('chart type case line has correct string value', function () {
    expect(ChartType::Line->value)->toBe('line');
});

test('chart type can be instantiated from valid string value', function () {
    expect(ChartType::from('donut'))->toBe(ChartType::Donut);
    expect(ChartType::from('bar'))->toBe(ChartType::Bar);
    expect(ChartType::from('bar_horizontal'))->toBe(ChartType::BarHorizontal);
    expect(ChartType::from('stacked_bar'))->toBe(ChartType::StackedBar);
    expect(ChartType::from('line'))->toBe(ChartType::Line);
});

test('chart type tryFrom returns null for invalid value', function () {
    expect(ChartType::tryFrom('invalid'))->toBeNull();
    expect(ChartType::tryFrom('DONUT'))->toBeNull();
    expect(ChartType::tryFrom('bar-horizontal'))->toBeNull();
});

test('chart type from throws value error for invalid value', function () {
    expect(fn () => ChartType::from('pie'))->toThrow(ValueError::class);
});
