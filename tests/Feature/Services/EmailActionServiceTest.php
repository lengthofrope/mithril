<?php

declare(strict_types=1);

use App\Models\Bila;
use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\EmailActionService;

describe('EmailActionService::resolveTeamMember()', function (): void {
    it('matches sender_email against TeamMember.email case-insensitively', function (): void {
        $user   = User::factory()->create();
        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'email'   => 'alice@example.com',
        ]);

        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'Alice@Example.COM',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);

        expect($service->resolveTeamMember($email)?->id)->toBe($member->id);
    });

    it('matches against TeamMember.microsoft_email', function (): void {
        $user   = User::factory()->create();
        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::factory()->create([
            'user_id'         => $user->id,
            'team_id'         => $team->id,
            'email'           => 'personal@example.com',
            'microsoft_email' => 'bob@company.com',
        ]);

        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'bob@company.com',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);

        expect($service->resolveTeamMember($email)?->id)->toBe($member->id);
    });

    it('returns null when no team member matches', function (): void {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        TeamMember::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'email'   => 'known@example.com',
        ]);

        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'unknown@example.com',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);

        expect($service->resolveTeamMember($email))->toBeNull();
    });
});

describe('EmailActionService::senderIsTeamMember()', function (): void {
    it('returns true when sender matches a team member', function (): void {
        $user   = User::factory()->create();
        $team   = Team::factory()->create(['user_id' => $user->id]);
        TeamMember::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'email'   => 'alice@example.com',
        ]);

        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'alice@example.com',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);

        expect($service->senderIsTeamMember($email))->toBeTrue();
    });

    it('returns false for unknown senders', function (): void {
        $user = User::factory()->create();

        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'nobody@example.com',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);

        expect($service->senderIsTeamMember($email))->toBeFalse();
    });
});

describe('EmailActionService::buildPrefillData()', function (): void {
    it('returns correct prefill for task', function (): void {
        $user   = User::factory()->create();
        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'email'   => 'alice@example.com',
        ]);

        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'subject'      => 'Please review document',
            'sender_email' => 'alice@example.com',
            'importance'   => 'high',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);
        $data    = $service->buildPrefillData($email, 'task');

        expect($data['title'])->toBe('Please review document')
            ->and($data['team_member_id'])->toBe($member->id)
            ->and($data['priority'])->toBe('high');
    });

    it('returns correct prefill for follow-up with flag_due_date', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id'       => $user->id,
            'subject'       => 'Waiting on approval',
            'sender_email'  => 'someone@example.com',
            'flag_due_date' => '2026-03-20',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);
        $data    = $service->buildPrefillData($email, 'follow-up');

        expect($data['description'])->toBe('Waiting on approval')
            ->and($data['follow_up_date'])->toBe('2026-03-20');
    });

    it('returns correct prefill for follow-up without flag_due_date', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id'       => $user->id,
            'subject'       => 'Follow up needed',
            'sender_email'  => 'someone@example.com',
            'flag_due_date' => null,
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);
        $data    = $service->buildPrefillData($email, 'follow-up');

        expect($data['follow_up_date'])->toBe(now()->addDays(3)->toDateString());
    });

    it('returns correct prefill for note', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'subject'      => 'Meeting notes',
            'body_preview' => 'Key points from the meeting...',
            'sender_email' => 'someone@example.com',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);
        $data    = $service->buildPrefillData($email, 'note');

        expect($data['title'])->toBe('Meeting notes')
            ->and($data['content'])->toBe('Key points from the meeting...');
    });

    it('returns correct prefill for bila when sender is a team member', function (): void {
        $user   = User::factory()->create();
        $team   = Team::factory()->create(['user_id' => $user->id]);
        $member = TeamMember::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'email'   => 'alice@example.com',
        ]);

        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'subject'      => 'Catch up',
            'sender_email' => 'alice@example.com',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);
        $data    = $service->buildPrefillData($email, 'bila');

        expect($data['team_member_id'])->toBe($member->id);
    });

    it('throws when bila type is requested but sender is not a team member', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'nobody@example.com',
        ]);

        $this->actingAs($user);
        $service = app(EmailActionService::class);

        expect(fn () => $service->buildPrefillData($email, 'bila'))
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('EmailActionService::linkResource()', function (): void {
    it('creates a link with denormalized email_subject', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id' => $user->id,
            'subject' => 'Important email',
        ]);
        $task = Task::factory()->create(['user_id' => $user->id]);

        $service = app(EmailActionService::class);
        $link    = $service->linkResource($email, $task);

        expect($link)->toBeInstanceOf(EmailLink::class)
            ->and($link->email_id)->toBe($email->id)
            ->and($link->email_subject)->toBe('Important email')
            ->and($link->linkable_type)->toBe(Task::class)
            ->and($link->linkable_id)->toBe($task->id);
    });

    it('prevents duplicate links', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        $service = app(EmailActionService::class);
        $link1   = $service->linkResource($email, $task);
        $link2   = $service->linkResource($email, $task);

        expect($link1->id)->toBe($link2->id)
            ->and(EmailLink::count())->toBe(1);
    });
});
