<?php

declare(strict_types=1);

use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

describe('PartialController dashboard sections', function (): void {
    describe('dashboard tasks section', function (): void {
        it('returns HTML partial for dashboard tasks section', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/dashboard/tasks');

            $response->assertOk()
                ->assertHeader('ETag');
        });

        it('returns 304 when ETag matches', function (): void {
            $user = User::factory()->create();

            $first = $this->actingAs($user)->get('/partials/dashboard/tasks');
            $etag = $first->headers->get('ETag');

            $second = $this->actingAs($user)->get(
                '/partials/dashboard/tasks',
                ['If-None-Match' => $etag],
            );

            $second->assertStatus(304);
        });

        it('sorts today tasks by deadline then priority', function (): void {
            $this->travelTo(Carbon::parse('2026-03-11 12:00:00', 'Europe/Amsterdam'));
            $user = User::factory()->create();

            $low = Task::factory()->create(['user_id' => $user->id, 'deadline' => now('Europe/Amsterdam')->toDateString(), 'status' => TaskStatus::Open, 'priority' => Priority::Low]);
            $urgent = Task::factory()->create(['user_id' => $user->id, 'deadline' => now('Europe/Amsterdam')->toDateString(), 'status' => TaskStatus::Open, 'priority' => Priority::Urgent]);

            $response = $this->actingAs($user)->get('/partials/dashboard/tasks');

            $response->assertOk();
            $content = $response->getContent();
            expect(strpos($content, (string) $urgent->title))->toBeLessThan(strpos($content, (string) $low->title));
        });
    });

    describe('dashboard follow-ups section', function (): void {
        it('returns HTML partial for dashboard follow-ups section', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/dashboard/follow-ups');

            $response->assertOk()
                ->assertHeader('ETag');
        });

        it('sorts same-date today follow-ups urgent-first', function (): void {
            $this->travelTo(Carbon::parse('2026-06-04 12:00:00', 'UTC'));
            $user = User::factory()->create();

            $today = now()->toDateString();

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'Low priority today follow-up',
                'priority'       => Priority::Low,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $today,
            ]);

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'Urgent today follow-up',
                'priority'       => Priority::Urgent,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $today,
            ]);

            $response = $this->actingAs($user)->get(route('partials.dashboard.follow-ups'));

            $response->assertOk();
            $response->assertSeeInOrder(['Urgent today follow-up', 'Low priority today follow-up']);
        });

        it('sorts same-date upcoming follow-ups urgent-first', function (): void {
            $this->travelTo(Carbon::parse('2026-06-04 12:00:00', 'UTC'));
            $user = User::factory()->create();

            $futureDate = now()->addDays(3)->toDateString();

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'Normal upcoming follow-up',
                'priority'       => Priority::Normal,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $futureDate,
            ]);

            FollowUp::factory()->create([
                'user_id'        => $user->id,
                'title'          => 'High upcoming follow-up',
                'priority'       => Priority::High,
                'is_private'     => false,
                'status'         => FollowUpStatus::Open,
                'follow_up_date' => $futureDate,
            ]);

            $response = $this->actingAs($user)->get(route('partials.dashboard.follow-ups'));

            $response->assertOk();
            $response->assertSeeInOrder(['High upcoming follow-up', 'Normal upcoming follow-up']);
        });
    });

    describe('dashboard meetings section', function (): void {
        it('returns HTML partial for dashboard meetings section', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/dashboard/meetings');

            $response->assertOk()
                ->assertHeader('ETag');
        });
    });

    describe('dashboard calendar section', function (): void {
        it('returns HTML partial for dashboard calendar section', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/dashboard/calendar');

            $response->assertOk()
                ->assertHeader('ETag');
        });
    });

    describe('dashboard emails section', function (): void {
        it('returns HTML partial for dashboard emails section', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/dashboard/emails');

            $response->assertOk()
                ->assertHeader('ETag');
        });
    });

    describe('authentication', function (): void {
        it('returns redirect for unauthenticated requests', function (): void {
            $response = $this->get('/partials/dashboard/tasks');

            $response->assertRedirect('/login');
        });
    });
});
