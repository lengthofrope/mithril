<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Facades\Http;

describe('MicrosoftGraphService::getMyMessages()', function (): void {
    it('returns a normalized collection of messages', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/mailFolders/Inbox/messages*' => Http::response([
                'value' => [
                    [
                        'id'               => 'AAMkABC123',
                        'subject'          => 'Project update',
                        'from'             => [
                            'emailAddress' => [
                                'name'    => 'Alice',
                                'address' => 'alice@example.com',
                            ],
                        ],
                        'receivedDateTime' => '2026-03-12T10:00:00Z',
                        'bodyPreview'      => 'Here is the update...',
                        'isRead'           => false,
                        'flag'             => ['flagStatus' => 'flagged'],
                        'categories'       => ['Mithril'],
                        'importance'       => 'high',
                        'hasAttachments'   => true,
                        'webLink'          => 'https://outlook.office.com/mail/id/AAMkABC123',
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $result  = $service->getMyMessages($user, "flag/flagStatus eq 'flagged'");

        expect($result)->toHaveCount(1);

        $msg = $result->first();
        expect($msg['microsoft_message_id'])->toBe('AAMkABC123')
            ->and($msg['subject'])->toBe('Project update')
            ->and($msg['sender_name'])->toBe('Alice')
            ->and($msg['sender_email'])->toBe('alice@example.com')
            ->and($msg['received_at'])->toBe('2026-03-12T10:00:00Z')
            ->and($msg['body_preview'])->toBe('Here is the update...')
            ->and($msg['is_read'])->toBeFalse()
            ->and($msg['is_flagged'])->toBeTrue()
            ->and($msg['categories'])->toBe(['Mithril'])
            ->and($msg['importance'])->toBe('high')
            ->and($msg['has_attachments'])->toBeTrue()
            ->and($msg['web_link'])->toBe('https://outlook.office.com/mail/id/AAMkABC123');
    });

    it('extracts flag_due_date from flag.dueDateTime', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/mailFolders/Inbox/messages*' => Http::response([
                'value' => [
                    [
                        'id'               => 'AAMkDEF456',
                        'subject'          => 'Deadline task',
                        'from'             => [
                            'emailAddress' => [
                                'name'    => 'Bob',
                                'address' => 'bob@example.com',
                            ],
                        ],
                        'receivedDateTime' => '2026-03-12T08:00:00Z',
                        'bodyPreview'      => '',
                        'isRead'           => true,
                        'flag'             => [
                            'flagStatus'  => 'flagged',
                            'dueDateTime' => [
                                'dateTime' => '2026-03-20T00:00:00.0000000',
                                'timeZone' => 'UTC',
                            ],
                        ],
                        'categories'       => [],
                        'importance'       => 'normal',
                        'hasAttachments'   => false,
                        'webLink'          => 'https://outlook.office.com/mail/id/AAMkDEF456',
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $result  = $service->getMyMessages($user, "flag/flagStatus eq 'flagged'");

        expect($result->first()['flag_due_date'])->toBe('2026-03-20');
    });

    it('returns null flag_due_date when flag has no dueDateTime', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/mailFolders/Inbox/messages*' => Http::response([
                'value' => [
                    [
                        'id'               => 'AAMkGHI789',
                        'subject'          => 'No deadline',
                        'from'             => [
                            'emailAddress' => [
                                'name'    => 'Carol',
                                'address' => 'carol@example.com',
                            ],
                        ],
                        'receivedDateTime' => '2026-03-12T09:00:00Z',
                        'bodyPreview'      => 'No deadline here.',
                        'isRead'           => false,
                        'flag'             => ['flagStatus' => 'flagged'],
                        'categories'       => [],
                        'importance'       => 'low',
                        'hasAttachments'   => false,
                        'webLink'          => null,
                    ],
                ],
            ]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $result  = $service->getMyMessages($user, "flag/flagStatus eq 'flagged'");

        expect($result->first()['flag_due_date'])->toBeNull();
    });

    it('respects the top parameter', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/mailFolders/Inbox/messages*' => Http::response(['value' => []]),
        ]);

        $service = app(MicrosoftGraphService::class);
        $service->getMyMessages($user, "isRead eq false", 25);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '%24top=25');
        });
    });

    it('paginates through all pages via @odata.nextLink', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $graphUrl = config('microsoft.graph_url');

        Http::fakeSequence()
            ->push([
                'value'            => [
                    [
                        'id'               => 'msg-page1',
                        'subject'          => 'Page 1 message',
                        'from'             => ['emailAddress' => ['name' => 'Alice', 'address' => 'alice@example.com']],
                        'receivedDateTime' => '2026-03-15T10:00:00Z',
                        'bodyPreview'      => '',
                        'isRead'           => true,
                        'flag'             => ['flagStatus' => 'notFlagged'],
                        'categories'       => [],
                        'importance'       => 'normal',
                        'hasAttachments'   => false,
                        'webLink'          => null,
                    ],
                ],
                '@odata.nextLink' => $graphUrl . 'me/mailFolders/Inbox/messages?$skip=1',
            ])
            ->push([
                'value' => [
                    [
                        'id'               => 'msg-page2',
                        'subject'          => 'Page 2 message',
                        'from'             => ['emailAddress' => ['name' => 'Bob', 'address' => 'bob@example.com']],
                        'receivedDateTime' => '2026-03-15T09:00:00Z',
                        'bodyPreview'      => '',
                        'isRead'           => false,
                        'flag'             => ['flagStatus' => 'notFlagged'],
                        'categories'       => [],
                        'importance'       => 'normal',
                        'hasAttachments'   => false,
                        'webLink'          => null,
                    ],
                ],
            ]);

        $service = app(MicrosoftGraphService::class);
        $result  = $service->getMyMessages($user);

        expect($result)->toHaveCount(2)
            ->and($result[0]['microsoft_message_id'])->toBe('msg-page1')
            ->and($result[1]['microsoft_message_id'])->toBe('msg-page2');
    });

    it('stops pagination at a safe maximum to prevent infinite loops', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        $graphUrl = config('microsoft.graph_url');

        $sequence = Http::fakeSequence();

        for ($i = 0; $i < 15; $i++) {
            $sequence->push([
                'value'            => [
                    [
                        'id'               => "msg-page{$i}",
                        'subject'          => "Page {$i}",
                        'from'             => ['emailAddress' => ['name' => 'Test', 'address' => 'test@example.com']],
                        'receivedDateTime' => '2026-03-15T10:00:00Z',
                        'bodyPreview'      => '',
                        'isRead'           => true,
                        'flag'             => ['flagStatus' => 'notFlagged'],
                        'categories'       => [],
                        'importance'       => 'normal',
                        'hasAttachments'   => false,
                        'webLink'          => null,
                    ],
                ],
                '@odata.nextLink' => $graphUrl . "me/mailFolders/Inbox/messages?\$skip={$i}",
            ]);
        }

        $service = app(MicrosoftGraphService::class);
        $result  = $service->getMyMessages($user);

        expect($result->count())->toBeLessThanOrEqual(500);
    });

    it('throws RuntimeException on API failure', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
        ]);

        Http::fake([
            '*/me/mailFolders/Inbox/messages*' => Http::response([
                'error' => ['message' => 'MailboxNotEnabledForRESTAPI'],
            ], 403),
        ]);

        $service = app(MicrosoftGraphService::class);

        expect(fn () => $service->getMyMessages($user, "isRead eq false"))
            ->toThrow(RuntimeException::class);
    });
});
