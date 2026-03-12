<?php

declare(strict_types=1);

use App\Enums\EmailImportance;
use App\Models\Bila;
use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;

describe('Email model', function (): void {
    it('can be created with factory defaults', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);

        expect($email)->toBeInstanceOf(Email::class)
            ->and($email->user_id)->toBe($user->id)
            ->and($email->subject)->toBeString()
            ->and($email->microsoft_message_id)->toBeString();
    });

    it('scopes to the authenticated user via BelongsToUser', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Email::factory()->create(['user_id' => $user1->id]);
        Email::factory()->create(['user_id' => $user2->id]);

        $this->actingAs($user1);

        expect(Email::count())->toBe(1);
    });

    it('casts flag_due_date as a date', function (): void {
        $email = Email::factory()->create([
            'flag_due_date' => '2026-03-15',
        ]);

        expect($email->flag_due_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
            ->and($email->flag_due_date->toDateString())->toBe('2026-03-15');
    });

    it('casts categories as an array', function (): void {
        $email = Email::factory()->create([
            'categories' => ['Mithril', 'Important'],
        ]);

        $fresh = Email::withoutGlobalScopes()->find($email->id);

        expect($fresh->categories)->toBeArray()
            ->and($fresh->categories)->toBe(['Mithril', 'Important']);
    });

    it('casts sources as an array', function (): void {
        $email = Email::factory()->create([
            'sources' => ['flagged', 'unread'],
        ]);

        $fresh = Email::withoutGlobalScopes()->find($email->id);

        expect($fresh->sources)->toBeArray()
            ->and($fresh->sources)->toBe(['flagged', 'unread']);
    });

    it('casts importance as EmailImportance enum', function (): void {
        $email = Email::factory()->create([
            'importance' => 'high',
        ]);

        expect($email->importance)->toBe(EmailImportance::High);
    });

    it('casts boolean fields correctly', function (): void {
        $email = Email::factory()->create([
            'is_read'           => true,
            'is_flagged'        => true,
            'has_attachments'   => true,
            'is_dismissed'      => false,
        ]);

        expect($email->is_read)->toBeTrue()
            ->and($email->is_flagged)->toBeTrue()
            ->and($email->has_attachments)->toBeTrue()
            ->and($email->is_dismissed)->toBeFalse();
    });

    it('enforces unique constraint on user_id + microsoft_message_id', function (): void {
        $user = User::factory()->create();
        $msgId = 'AAMkABC123';

        Email::factory()->create([
            'user_id'              => $user->id,
            'microsoft_message_id' => $msgId,
        ]);

        expect(fn () => Email::factory()->create([
            'user_id'              => $user->id,
            'microsoft_message_id' => $msgId,
        ]))->toThrow(QueryException::class);
    });

    it('allows same microsoft_message_id for different users', function (): void {
        $msgId = 'AAMkABC123';

        Email::factory()->create(['microsoft_message_id' => $msgId]);
        Email::factory()->create(['microsoft_message_id' => $msgId]);

        expect(Email::withoutGlobalScopes()->count())->toBe(2);
    });

    it('has emailLinks relationship', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        EmailLink::factory()->forTask($task)->create(['email_id' => $email->id]);

        expect($email->emailLinks)->toHaveCount(1);
    });
});
