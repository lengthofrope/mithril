<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Facades\Http;

describe('MicrosoftGraphService attendee normalisation', function (): void {
    it('normalises attendees from Graph calendarView response', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-attendee',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendarView*' => Http::response([
                'value' => [
                    [
                        'id'      => 'graph-event-1',
                        'subject' => 'Team sync',
                        'start'   => ['dateTime' => '2026-03-10T10:00:00', 'timeZone' => 'UTC'],
                        'end'     => ['dateTime' => '2026-03-10T11:00:00', 'timeZone' => 'UTC'],
                        'isAllDay'         => false,
                        'location'         => ['displayName' => ''],
                        'showAs'           => 'busy',
                        'isOnlineMeeting'  => false,
                        'onlineMeeting'    => null,
                        'organizer'        => [
                            'emailAddress' => [
                                'name'    => 'Alice',
                                'address' => 'alice@company.com',
                            ],
                        ],
                        'attendees' => [
                            [
                                'emailAddress' => [
                                    'name'    => 'Bob Smith',
                                    'address' => 'bob@company.com',
                                ],
                                'type' => 'required',
                            ],
                            [
                                'emailAddress' => [
                                    'name'    => 'Carol Jones',
                                    'address' => 'carol@company.com',
                                ],
                                'type' => 'optional',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $events  = $service->getMyCalendarEvents($user, now(), now()->addDay());

        expect($events)->toHaveCount(1);

        $attendees = $events->first()['attendees'];

        expect($attendees)->toBeArray()
            ->toHaveCount(2)
            ->sequence(
                fn ($item) => $item->toMatchArray(['email' => 'bob@company.com', 'name' => 'Bob Smith']),
                fn ($item) => $item->toMatchArray(['email' => 'carol@company.com', 'name' => 'Carol Jones']),
            );
    });

    it('filters out attendees whose email address is null', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-null-email',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendarView*' => Http::response([
                'value' => [
                    [
                        'id'      => 'graph-event-2',
                        'subject' => 'Meeting',
                        'start'   => ['dateTime' => '2026-03-10T10:00:00', 'timeZone' => 'UTC'],
                        'end'     => ['dateTime' => '2026-03-10T11:00:00', 'timeZone' => 'UTC'],
                        'isAllDay'        => false,
                        'location'        => ['displayName' => ''],
                        'showAs'          => 'busy',
                        'isOnlineMeeting' => false,
                        'onlineMeeting'   => null,
                        'organizer'       => [
                            'emailAddress' => ['name' => 'Alice', 'address' => 'alice@company.com'],
                        ],
                        'attendees' => [
                            [
                                'emailAddress' => ['name' => 'Valid Person', 'address' => 'valid@company.com'],
                                'type'         => 'required',
                            ],
                            [
                                'emailAddress' => ['name' => 'No Email', 'address' => null],
                                'type'         => 'required',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $events  = $service->getMyCalendarEvents($user, now(), now()->addDay());

        $attendees = $events->first()['attendees'];

        expect($attendees)->toHaveCount(1)
            ->and($attendees[0]['email'])->toBe('valid@company.com');
    });

    it('returns an empty array when the Graph response contains no attendees', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-no-attendees',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendarView*' => Http::response([
                'value' => [
                    [
                        'id'      => 'graph-event-3',
                        'subject' => 'Private event',
                        'start'   => ['dateTime' => '2026-03-10T10:00:00', 'timeZone' => 'UTC'],
                        'end'     => ['dateTime' => '2026-03-10T11:00:00', 'timeZone' => 'UTC'],
                        'isAllDay'        => false,
                        'location'        => ['displayName' => ''],
                        'showAs'          => 'busy',
                        'isOnlineMeeting' => false,
                        'onlineMeeting'   => null,
                        'organizer'       => [
                            'emailAddress' => ['name' => 'Alice', 'address' => 'alice@company.com'],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $events  = $service->getMyCalendarEvents($user, now(), now()->addDay());

        $attendees = $events->first()['attendees'];

        expect($attendees)->toBeArray()->toBeEmpty();
    });

    it('includes attendees as a key in every normalised event', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-key-check',
            'microsoft_email'            => 'user@company.com',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/calendarView*' => Http::response([
                'value' => [
                    [
                        'id'      => 'graph-event-4',
                        'subject' => 'Any event',
                        'start'   => ['dateTime' => '2026-03-10T10:00:00', 'timeZone' => 'UTC'],
                        'end'     => ['dateTime' => '2026-03-10T11:00:00', 'timeZone' => 'UTC'],
                        'isAllDay'        => false,
                        'location'        => ['displayName' => ''],
                        'showAs'          => 'busy',
                        'isOnlineMeeting' => false,
                        'onlineMeeting'   => null,
                        'organizer'       => [
                            'emailAddress' => ['name' => 'Alice', 'address' => 'alice@company.com'],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $events  = $service->getMyCalendarEvents($user, now(), now()->addDay());

        expect($events->first())->toHaveKey('attendees');
    });
});
