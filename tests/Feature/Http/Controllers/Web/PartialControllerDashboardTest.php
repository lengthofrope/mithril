<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    });

    describe('dashboard follow-ups section', function (): void {
        it('returns HTML partial for dashboard follow-ups section', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/dashboard/follow-ups');

            $response->assertOk()
                ->assertHeader('ETag');
        });
    });

    describe('dashboard bilas section', function (): void {
        it('returns HTML partial for dashboard bilas section', function (): void {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->get('/partials/dashboard/bilas');

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
