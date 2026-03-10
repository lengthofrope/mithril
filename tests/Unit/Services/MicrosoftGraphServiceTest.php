<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Facades\Http;

describe('MicrosoftGraphService::isKnownMicrosoftUser()', function (): void {
    it('returns true when the email resolves to a valid schedule', function (): void {
        /** @var \Tests\TestCase $this */
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendar/getSchedule' => Http::response([
                'value' => [
                    [
                        'scheduleId'       => 'colleague@company.com',
                        'availabilityView' => '0000',
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);

        expect($service->isKnownMicrosoftUser($user, 'colleague@company.com'))->toBeTrue();
    });

    it('returns false when the schedule response contains an error for the email', function (): void {
        /** @var \Tests\TestCase $this */
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendar/getSchedule' => Http::response([
                'value' => [
                    [
                        'scheduleId' => 'unknown@external.com',
                        'error'      => [
                            'responseCode' => 'ErrorMailRecipientNotFound',
                            'message'      => 'The mailbox is either inactive or does not exist.',
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);

        expect($service->isKnownMicrosoftUser($user, 'unknown@external.com'))->toBeFalse();
    });

    it('returns false when the Graph API returns an error response', function (): void {
        /** @var \Tests\TestCase $this */
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendar/getSchedule' => Http::response(['error' => ['message' => 'Forbidden']], 403),
        ]);

        $service = app(MicrosoftGraphService::class);

        expect($service->isKnownMicrosoftUser($user, 'colleague@company.com'))->toBeFalse();
    });

    it('returns false when the schedule response is empty', function (): void {
        /** @var \Tests\TestCase $this */
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendar/getSchedule' => Http::response(['value' => []]),
        ]);

        $service = app(MicrosoftGraphService::class);

        expect($service->isKnownMicrosoftUser($user, 'colleague@company.com'))->toBeFalse();
    });
});
