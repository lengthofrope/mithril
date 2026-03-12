<?php

declare(strict_types=1);

use App\Models\Email;
use App\Models\EmailLink;
use App\Models\Task;
use App\Models\User;
use App\Services\EmailSyncService;
use App\Services\MicrosoftGraphService;
use Illuminate\Support\Collection;

describe('EmailSyncService::buildFilter()', function (): void {
    it('returns flagged filter when only flagged source is enabled', function (): void {
        $user = User::factory()->create([
            'email_source_flagged'     => true,
            'email_source_categorized' => false,
            'email_source_unread'      => false,
        ]);

        $service = app(EmailSyncService::class);

        expect($service->buildFilter($user))->toBe("flag/flagStatus eq 'flagged'");
    });

    it('returns categorized filter with custom category name', function (): void {
        $user = User::factory()->create([
            'email_source_flagged'        => false,
            'email_source_categorized'    => true,
            'email_source_category_name'  => 'ActionItems',
            'email_source_unread'         => false,
        ]);

        $service = app(EmailSyncService::class);

        expect($service->buildFilter($user))->toBe("categories/any(c:c eq 'ActionItems')");
    });

    it('returns unread filter when only unread source is enabled', function (): void {
        $user = User::factory()->create([
            'email_source_flagged'     => false,
            'email_source_categorized' => false,
            'email_source_unread'      => true,
        ]);

        $service = app(EmailSyncService::class);

        expect($service->buildFilter($user))->toBe('isRead eq false');
    });

    it('combines multiple sources with OR logic', function (): void {
        $user = User::factory()->create([
            'email_source_flagged'        => true,
            'email_source_categorized'    => true,
            'email_source_category_name'  => 'Mithril',
            'email_source_unread'         => true,
        ]);

        $service = app(EmailSyncService::class);
        $filter  = $service->buildFilter($user);

        expect($filter)->toContain("flag/flagStatus eq 'flagged'")
            ->and($filter)->toContain("categories/any(c:c eq 'Mithril')")
            ->and($filter)->toContain('isRead eq false')
            ->and($filter)->toContain(' or ');
    });

    it('returns empty string when no sources are enabled', function (): void {
        $user = User::factory()->create([
            'email_source_flagged'     => false,
            'email_source_categorized' => false,
            'email_source_unread'      => false,
        ]);

        $service = app(EmailSyncService::class);

        expect($service->buildFilter($user))->toBe('');
    });
});

describe('EmailSyncService::normalizeMessage()', function (): void {
    it('maps Graph API fields correctly', function (): void {
        $service = app(EmailSyncService::class);

        $normalized = $service->normalizeMessage([
            'microsoft_message_id' => 'AAMkABC123',
            'subject'              => 'Test subject',
            'sender_name'          => 'Alice',
            'sender_email'         => 'alice@example.com',
            'received_at'          => '2026-03-12T10:00:00Z',
            'body_preview'         => 'Preview text',
            'is_read'              => false,
            'is_flagged'           => true,
            'flag_due_date'        => '2026-03-20',
            'categories'           => ['Mithril'],
            'importance'           => 'high',
            'has_attachments'      => true,
            'web_link'             => 'https://outlook.office.com/mail/id/AAMkABC123',
        ], ['flagged', 'categorized']);

        expect($normalized['microsoft_message_id'])->toBe('AAMkABC123')
            ->and($normalized['sources'])->toBe(['flagged', 'categorized'])
            ->and($normalized['is_dismissed'])->toBeFalse();
    });

    it('truncates body_preview to 500 characters', function (): void {
        $service = app(EmailSyncService::class);

        $longBody = str_repeat('A', 600);

        $normalized = $service->normalizeMessage([
            'microsoft_message_id' => 'AAMkABC123',
            'subject'              => 'Long body',
            'sender_name'          => null,
            'sender_email'         => null,
            'received_at'          => '2026-03-12T10:00:00Z',
            'body_preview'         => $longBody,
            'is_read'              => true,
            'is_flagged'           => false,
            'flag_due_date'        => null,
            'categories'           => [],
            'importance'           => 'normal',
            'has_attachments'      => false,
            'web_link'             => null,
        ], ['unread']);

        expect(strlen($normalized['body_preview']))->toBe(500);
    });
});

describe('EmailSyncService::syncEmails()', function (): void {
    it('upserts new emails and updates existing ones', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
            'email_source_flagged'       => true,
            'email_source_categorized'   => false,
            'email_source_unread'        => false,
        ]);

        $graphService = Mockery::mock(MicrosoftGraphService::class);
        $graphService->shouldReceive('getMyMessages')
            ->once()
            ->andReturn(collect([
                [
                    'microsoft_message_id' => 'AAMkNEW1',
                    'subject'              => 'New email',
                    'sender_name'          => 'Alice',
                    'sender_email'         => 'alice@example.com',
                    'received_at'          => '2026-03-12T10:00:00Z',
                    'body_preview'         => 'Preview',
                    'is_read'              => false,
                    'is_flagged'           => true,
                    'flag_due_date'        => null,
                    'categories'           => [],
                    'importance'           => 'normal',
                    'has_attachments'      => false,
                    'web_link'             => null,
                ],
            ]));

        app()->instance(MicrosoftGraphService::class, $graphService);

        $service = app(EmailSyncService::class);
        $service->syncEmails($user);

        expect(Email::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);

        $email = Email::withoutGlobalScopes()->first();
        expect($email->microsoft_message_id)->toBe('AAMkNEW1')
            ->and($email->subject)->toBe('New email');
    });

    it('removes stale non-dismissed emails no longer in API response', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
            'email_source_flagged'       => true,
            'email_source_categorized'   => false,
            'email_source_unread'        => false,
        ]);

        Email::factory()->create([
            'user_id'              => $user->id,
            'microsoft_message_id' => 'AAMkSTALE',
            'is_dismissed'         => false,
        ]);

        $graphService = Mockery::mock(MicrosoftGraphService::class);
        $graphService->shouldReceive('getMyMessages')
            ->once()
            ->andReturn(collect([]));

        app()->instance(MicrosoftGraphService::class, $graphService);

        $service = app(EmailSyncService::class);
        $service->syncEmails($user);

        expect(Email::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(0);
    });

    it('does not remove dismissed emails regardless of API response', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
            'email_source_flagged'       => true,
            'email_source_categorized'   => false,
            'email_source_unread'        => false,
        ]);

        Email::factory()->create([
            'user_id'              => $user->id,
            'microsoft_message_id' => 'AAMkDISMISSED',
            'is_dismissed'         => true,
        ]);

        $graphService = Mockery::mock(MicrosoftGraphService::class);
        $graphService->shouldReceive('getMyMessages')
            ->once()
            ->andReturn(collect([]));

        app()->instance(MicrosoftGraphService::class, $graphService);

        $service = app(EmailSyncService::class);
        $service->syncEmails($user);

        expect(Email::withoutGlobalScopes()->where('user_id', $user->id)->count())->toBe(1);
    });

    it('preserves links via SET NULL when stale emails are removed', function (): void {
        $user = User::factory()->create([
            'microsoft_id'               => 'ms-id-123',
            'microsoft_access_token'     => 'valid-token',
            'microsoft_refresh_token'    => 'valid-refresh',
            'microsoft_token_expires_at' => now()->addHour(),
            'email_source_flagged'       => true,
            'email_source_categorized'   => false,
            'email_source_unread'        => false,
        ]);

        $email = Email::factory()->create([
            'user_id'              => $user->id,
            'microsoft_message_id' => 'AAMkLINKED',
            'is_dismissed'         => false,
        ]);

        $task = Task::factory()->create(['user_id' => $user->id]);

        EmailLink::factory()->forTask($task)->create([
            'email_id'      => $email->id,
            'email_subject' => $email->subject,
        ]);

        $graphService = Mockery::mock(MicrosoftGraphService::class);
        $graphService->shouldReceive('getMyMessages')
            ->once()
            ->andReturn(collect([]));

        app()->instance(MicrosoftGraphService::class, $graphService);

        $service = app(EmailSyncService::class);
        $service->syncEmails($user);

        expect(EmailLink::count())->toBe(1);

        $link = EmailLink::first();
        expect($link->email_id)->toBeNull()
            ->and($link->email_subject)->toBeString();
    });

    it('skips sync when no sources are enabled', function (): void {
        $user = User::factory()->create([
            'email_source_flagged'     => false,
            'email_source_categorized' => false,
            'email_source_unread'      => false,
        ]);

        $graphService = Mockery::mock(MicrosoftGraphService::class);
        $graphService->shouldNotReceive('getMyMessages');

        app()->instance(MicrosoftGraphService::class, $graphService);

        $service = app(EmailSyncService::class);
        $service->syncEmails($user);
    });
});
