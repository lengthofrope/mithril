<?php

declare(strict_types=1);

use App\DataTransferObjects\TokenResponse;
use Carbon\Carbon;

describe('TokenResponse DTO', function (): void {
    describe('construction', function (): void {
        it('can be constructed with all required properties', function (): void {
            $expiresAt = Carbon::now()->addHour();

            $dto = new TokenResponse(
                accessToken:  'access-token-value',
                refreshToken: 'refresh-token-value',
                expiresAt:    $expiresAt,
                microsoftId:  'ms-object-id-123',
                email:        'user@example.com',
            );

            expect($dto->accessToken)->toBe('access-token-value')
                ->and($dto->refreshToken)->toBe('refresh-token-value')
                ->and($dto->expiresAt)->toBe($expiresAt)
                ->and($dto->microsoftId)->toBe('ms-object-id-123')
                ->and($dto->email)->toBe('user@example.com');
        });
    });

    describe('property access', function (): void {
        it('exposes accessToken as a public property', function (): void {
            $dto = new TokenResponse(
                accessToken:  'my-access-token',
                refreshToken: 'my-refresh-token',
                expiresAt:    Carbon::now()->addHour(),
                microsoftId:  'ms-id',
                email:        'user@example.com',
            );

            expect($dto->accessToken)->toBe('my-access-token');
        });

        it('exposes refreshToken as a public property', function (): void {
            $dto = new TokenResponse(
                accessToken:  'my-access-token',
                refreshToken: 'my-refresh-token',
                expiresAt:    Carbon::now()->addHour(),
                microsoftId:  'ms-id',
                email:        'user@example.com',
            );

            expect($dto->refreshToken)->toBe('my-refresh-token');
        });

        it('exposes expiresAt as a public property', function (): void {
            $expiresAt = Carbon::parse('2026-12-31 23:59:59');

            $dto = new TokenResponse(
                accessToken:  'token',
                refreshToken: 'refresh',
                expiresAt:    $expiresAt,
                microsoftId:  'ms-id',
                email:        'user@example.com',
            );

            expect($dto->expiresAt)->toBe($expiresAt);
        });

        it('exposes microsoftId as a public property', function (): void {
            $dto = new TokenResponse(
                accessToken:  'token',
                refreshToken: 'refresh',
                expiresAt:    Carbon::now()->addHour(),
                microsoftId:  'unique-ms-object-id',
                email:        'user@example.com',
            );

            expect($dto->microsoftId)->toBe('unique-ms-object-id');
        });

        it('exposes email as a public property', function (): void {
            $dto = new TokenResponse(
                accessToken:  'token',
                refreshToken: 'refresh',
                expiresAt:    Carbon::now()->addHour(),
                microsoftId:  'ms-id',
                email:        'specific@company.com',
            );

            expect($dto->email)->toBe('specific@company.com');
        });
    });

    describe('immutability', function (): void {
        it('is marked as readonly via reflection', function (): void {
            $reflection = new ReflectionClass(TokenResponse::class);

            expect($reflection->isReadOnly())->toBeTrue();
        });

        it('throws an error when attempting to mutate a property', function (): void {
            $dto = new TokenResponse(
                accessToken:  'token',
                refreshToken: 'refresh',
                expiresAt:    Carbon::now()->addHour(),
                microsoftId:  'ms-id',
                email:        'user@example.com',
            );

            expect(fn () => $dto->accessToken = 'mutated')->toThrow(Error::class);
        });
    });
});
