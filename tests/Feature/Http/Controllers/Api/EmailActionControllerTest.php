<?php

declare(strict_types=1);

use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

/**
 * Sets up the base fixture: user, team, team member, and email.
 *
 * @return array{user: User, team: Team, member: TeamMember, email: Email}
 */
function makeEmailFixture(): array
{
    $user   = User::factory()->create();
    $team   = Team::factory()->create(['user_id' => $user->id]);
    $member = TeamMember::factory()->create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'email'   => 'member@example.com',
    ]);
    $email = Email::factory()->create([
        'user_id'      => $user->id,
        'subject'      => 'Action needed',
        'sender_email' => 'member@example.com',
        'importance'   => 'high',
        'is_flagged'   => true,
        'flag_due_date' => '2026-03-20',
    ]);

    return compact('user', 'team', 'member', 'email');
}

// ─── Index ───────────────────────────────────────────────────────────────────

describe('GET /api/v1/emails', function (): void {
    it('returns the user\'s emails', function (): void {
        $user = User::factory()->create();
        Email::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails');

        $response->assertOk()
            ->assertJson(['success' => true]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('respects BelongsToUser scope', function (): void {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Email::factory()->create(['user_id' => $user1->id]);
        Email::factory()->create(['user_id' => $user2->id]);

        $response = $this->actingAs($user1)->getJson('/api/v1/emails');

        expect($response->json('data'))->toHaveCount(1);
    });

    it('filters by source', function (): void {
        $user = User::factory()->create();
        Email::factory()->create(['user_id' => $user->id, 'sources' => ['flagged']]);
        Email::factory()->create(['user_id' => $user->id, 'sources' => ['unread']]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails?source=flagged');

        expect($response->json('data'))->toHaveCount(1);
    });

    it('includes sender_is_team_member boolean', function (): void {
        ['user' => $user, 'email' => $email] = makeEmailFixture();

        $response = $this->actingAs($user)->getJson('/api/v1/emails');

        expect($response->json('data.0.sender_is_team_member'))->toBeTrue();
    });

    it('returns 401 for unauthenticated requests', function (): void {
        $this->getJson('/api/v1/emails')->assertUnauthorized();
    });

    it('paginates results with default 25 per page', function (): void {
        $user = User::factory()->create();
        Email::factory()->count(30)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails');

        $response->assertOk()
            ->assertJson(['success' => true]);

        expect($response->json('data'))->toHaveCount(25, 'Default page size should be 25')
            ->and($response->json('meta.current_page'))->toBe(1)
            ->and($response->json('meta.last_page'))->toBe(2)
            ->and($response->json('meta.per_page'))->toBe(25)
            ->and($response->json('meta.total'))->toBe(30);
    });

    it('accepts per_page query parameter', function (): void {
        $user = User::factory()->create();
        Email::factory()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails?per_page=10');

        expect($response->json('data'))->toHaveCount(10, 'Should return 10 items when per_page=10')
            ->and($response->json('meta.per_page'))->toBe(10)
            ->and($response->json('meta.last_page'))->toBe(2);
    });

    it('clamps per_page to a maximum of 100', function (): void {
        $user = User::factory()->create();
        Email::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails?per_page=200');

        expect($response->json('meta.per_page'))->toBe(100, 'per_page should be clamped to 100');
    });

    it('accepts page query parameter', function (): void {
        $user = User::factory()->create();
        Email::factory()->count(30)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails?per_page=25&page=2');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(5, 'Page 2 should have remaining 5 items')
            ->and($response->json('meta.current_page'))->toBe(2);
    });

    it('filters by source with pagination', function (): void {
        $user = User::factory()->create();
        Email::factory()->count(30)->create(['user_id' => $user->id, 'sources' => ['flagged']]);
        Email::factory()->count(10)->create(['user_id' => $user->id, 'sources' => ['unread']]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails?source=flagged&per_page=25');

        expect($response->json('meta.total'))->toBe(30, 'Source filter should apply before pagination')
            ->and($response->json('meta.last_page'))->toBe(2)
            ->and($response->json('data'))->toHaveCount(25);
    });

    it('includes sender display data in paginated results', function (): void {
        ['user' => $user, 'email' => $email] = makeEmailFixture();

        $response = $this->actingAs($user)->getJson('/api/v1/emails');

        expect($response->json('data.0.sender_is_team_member'))->toBeTrue(
            'Paginated results must still include sender display data'
        );
    });

    it('includes email links in paginated results', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);
        EmailLink::factory()->forTask($task)->create(['email_id' => $email->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails');

        expect($response->json('data.0.links'))->toHaveCount(1, 'Paginated results must include email links');
    });
});

// ─── Dashboard ───────────────────────────────────────────────────────────────

describe('GET /api/v1/emails/dashboard', function (): void {
    it('returns all flagged emails', function (): void {
        $user = User::factory()->create();
        Email::factory()->create(['user_id' => $user->id, 'is_flagged' => true]);
        Email::factory()->create(['user_id' => $user->id, 'is_flagged' => false]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails/dashboard');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('orders by flag_due_date ASC NULLS LAST', function (): void {
        $user = User::factory()->create();
        Email::factory()->create([
            'user_id' => $user->id, 'is_flagged' => true,
            'flag_due_date' => null, 'subject' => 'No deadline',
        ]);
        Email::factory()->create([
            'user_id' => $user->id, 'is_flagged' => true,
            'flag_due_date' => '2026-03-25', 'subject' => 'Later',
        ]);
        Email::factory()->create([
            'user_id' => $user->id, 'is_flagged' => true,
            'flag_due_date' => '2026-03-15', 'subject' => 'Sooner',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails/dashboard');

        $subjects = collect($response->json('data'))->pluck('subject')->all();

        expect($subjects)->toBe(['Sooner', 'Later', 'No deadline']);
    });

    it('includes sender_is_team_member per email', function (): void {
        ['user' => $user] = makeEmailFixture();

        $response = $this->actingAs($user)->getJson('/api/v1/emails/dashboard');

        expect($response->json('data.0.sender_is_team_member'))->toBeTrue();
    });

    it('remains unpaginated and has no meta key', function (): void {
        $user = User::factory()->create();
        Email::factory()->count(30)->create(['user_id' => $user->id, 'is_flagged' => true]);

        $response = $this->actingAs($user)->getJson('/api/v1/emails/dashboard');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(30, 'Dashboard should return all flagged emails without pagination')
            ->and($response->json('meta'))->toBeNull('Dashboard response should not include pagination meta');
    });
});

// ─── Prefill ─────────────────────────────────────────────────────────────────

describe('GET /api/v1/emails/{email}/prefill/{type}', function (): void {
    it('returns prefill data for task', function (): void {
        ['user' => $user, 'member' => $member, 'email' => $email] = makeEmailFixture();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/emails/{$email->id}/prefill/task");

        $response->assertOk()
            ->assertJson(['success' => true]);

        expect($response->json('data.title'))->toBe('Action needed')
            ->and($response->json('data.team_member_id'))->toBe($member->id);
    });

    it('returns 422 for meeting when sender is not a team member', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'unknown@example.com',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/emails/{$email->id}/prefill/meeting");

        $response->assertStatus(422);
    });
});

// ─── Create ──────────────────────────────────────────────────────────────────

describe('POST /api/v1/emails/{email}/create/{type}', function (): void {
    it('creates a task and links it to the email', function (): void {
        ['user' => $user, 'email' => $email] = makeEmailFixture();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/emails/{$email->id}/create/task");

        $response->assertStatus(201)
            ->assertJson(['success' => true, 'message' => 'Created successfully.']);

        expect(Task::count())->toBe(1)
            ->and(EmailLink::count())->toBe(1);
    });

    it('creates a follow-up and links it', function (): void {
        ['user' => $user, 'email' => $email] = makeEmailFixture();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/emails/{$email->id}/create/follow-up");

        $response->assertStatus(201);
        expect(FollowUp::count())->toBe(1);
    });

    it('creates a note and links it', function (): void {
        ['user' => $user, 'email' => $email] = makeEmailFixture();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/emails/{$email->id}/create/note");

        $response->assertStatus(201);
        expect(Note::count())->toBe(1);
    });

    it('returns 422 for meeting when sender is not a team member', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create([
            'user_id'      => $user->id,
            'sender_email' => 'unknown@example.com',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/emails/{$email->id}/create/meeting");

        $response->assertStatus(422);
    });
});

// ─── Unlink ──────────────────────────────────────────────────────────────────

describe('DELETE /api/v1/emails/{email}/links/{emailLink}', function (): void {
    it('removes the link without deleting the resource', function (): void {
        $user  = User::factory()->create();
        $email = Email::factory()->create(['user_id' => $user->id]);
        $task  = Task::factory()->create(['user_id' => $user->id]);

        $link = EmailLink::factory()->forTask($task)->create([
            'email_id' => $email->id,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/emails/{$email->id}/links/{$link->id}")
            ->assertOk()
            ->assertJson(['message' => 'Link removed.']);

        expect(EmailLink::count())->toBe(0)
            ->and(Task::count())->toBe(1);
    });
});
