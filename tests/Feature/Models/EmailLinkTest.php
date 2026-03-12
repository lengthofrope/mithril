<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\QueryException;

describe('EmailLink model', function (): void {
    it('can be created with factory defaults', function (): void {
        $link = EmailLink::factory()->create();

        expect($link)->toBeInstanceOf(EmailLink::class)
            ->and($link->email_subject)->toBeString();
    });

    it('morphs to a task', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        $link = EmailLink::factory()->forTask($task)->create([
            'email_id' => $email->id,
        ]);

        expect($link->linkable)->toBeInstanceOf(Task::class)
            ->and($link->linkable->id)->toBe($task->id);
    });

    it('morphs to a follow-up', function (): void {
        $user     = User::factory()->create();
        $email    = Email::factory()->create(['user_id' => $user->id]);
        $followUp = FollowUp::factory()->create(['user_id' => $user->id]);

        $link = EmailLink::factory()->forFollowUp($followUp)->create([
            'email_id' => $email->id,
        ]);

        expect($link->linkable)->toBeInstanceOf(FollowUp::class)
            ->and($link->linkable->id)->toBe($followUp->id);
    });

    it('morphs to a note', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $note  = Note::factory()->create(['user_id' => $user->id]);

        $link = EmailLink::factory()->forNote($note)->create([
            'email_id' => $email->id,
        ]);

        expect($link->linkable)->toBeInstanceOf(Note::class)
            ->and($link->linkable->id)->toBe($note->id);
    });

    it('morphs to a bila', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $bila  = Bila::factory()->create(['user_id' => $user->id]);

        $link = EmailLink::factory()->forBila($bila)->create([
            'email_id' => $email->id,
        ]);

        expect($link->linkable)->toBeInstanceOf(Bila::class)
            ->and($link->linkable->id)->toBe($bila->id);
    });

    it('sets email_id to NULL when email is deleted (SET NULL)', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        $link = EmailLink::factory()->forTask($task)->create([
            'email_id'      => $email->id,
            'email_subject' => $email->subject,
        ]);

        $email->delete();

        $link->refresh();

        expect($link->email_id)->toBeNull()
            ->and($link->email_subject)->toBeString();
    });

    it('preserves email_subject as denormalized field after email deletion', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Important meeting notes',
        ]);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $link = EmailLink::factory()->forTask($task)->create([
            'email_id'      => $email->id,
            'email_subject' => 'Important meeting notes',
        ]);

        $email->delete();
        $link->refresh();

        expect($link->email_subject)->toBe('Important meeting notes');
    });

    it('prevents duplicate links with unique constraint', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        EmailLink::factory()->forTask($task)->create([
            'email_id' => $email->id,
        ]);

        expect(fn () => EmailLink::factory()->forTask($task)->create([
            'email_id' => $email->id,
        ]))->toThrow(QueryException::class);
    });

    it('cleans up EmailLinks when resource is deleted via HasResourceLinks', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        EmailLink::factory()->forTask($task)->create([
            'email_id' => $email->id,
        ]);

        expect(EmailLink::count())->toBe(1);

        $task->delete();

        expect(EmailLink::count())->toBe(0);
    });
});
