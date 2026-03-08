<?php

declare(strict_types=1);

use App\DataTransferObjects\ChartData;

describe('ChartData DTO', function (): void {
    describe('construction', function (): void {
        it('can be constructed with labels and series', function (): void {
            $dto = new ChartData(
                labels: ['Open', 'Done'],
                series: [5, 10],
            );

            expect($dto->labels)->toBe(['Open', 'Done'])
                ->and($dto->series)->toBe([5, 10]);
        });

        it('defaults colors to an empty array when not provided', function (): void {
            $dto = new ChartData(
                labels: ['Open'],
                series: [3],
            );

            expect($dto->colors)->toBe([]);
        });

        it('preserves custom colors when provided', function (): void {
            $dto = new ChartData(
                labels: ['Open', 'Done'],
                series: [5, 10],
                colors: ['#FF0000', '#00FF00'],
            );

            expect($dto->colors)->toBe(['#FF0000', '#00FF00']);
        });
    });

    describe('property access', function (): void {
        it('exposes labels as a public property', function (): void {
            $dto = new ChartData(
                labels: ['Urgent', 'High', 'Normal'],
                series: [1, 2, 3],
            );

            expect($dto->labels)->toBe(['Urgent', 'High', 'Normal']);
        });

        it('exposes series as a public property', function (): void {
            $dto = new ChartData(
                labels: ['Urgent', 'High', 'Normal'],
                series: [1, 2, 3],
            );

            expect($dto->series)->toBe([1, 2, 3]);
        });

        it('exposes colors as a public property', function (): void {
            $dto = new ChartData(
                labels: ['Open'],
                series: [7],
                colors: ['#3B82F6'],
            );

            expect($dto->colors)->toBe(['#3B82F6']);
        });
    });

    describe('immutability', function (): void {
        it('is marked as readonly via reflection', function (): void {
            $reflection = new ReflectionClass(ChartData::class);

            expect($reflection->isReadOnly())->toBeTrue();
        });

        it('throws an error when attempting to mutate a property', function (): void {
            $dto = new ChartData(
                labels: ['Open'],
                series: [4],
            );

            expect(fn () => $dto->labels = ['Done'])->toThrow(Error::class);
        });
    });
});
