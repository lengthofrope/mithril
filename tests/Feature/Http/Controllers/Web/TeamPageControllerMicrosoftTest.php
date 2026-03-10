<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Enums\StatusSource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\MicrosoftGraphService;

describe('TeamPageController — Microsoft availability fields', function (): void {
    describe('updateMember() — O365 auto-detection via email field', function (): void {
        it('auto-sets status_source to microsoft when email is a known O365 account', function (): void {
            /** @var \Tests\TestCase $this */
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $member = TeamMember::factory()->create([
                'user_id'       => $user->id,
                'status_source' => StatusSource::Manual,
            ]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldReceive('isKnownMicrosoftUser')
                ->with($user, 'colleague@company.com')
                ->once()
                ->andReturn(true);
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'email' => 'colleague@company.com',
                ])
                ->assertOk()
                ->assertJson(['success' => true, 'status_source' => 'microsoft']);

            $fresh = $member->fresh();
            expect($fresh->email)->toBe('colleague@company.com');
            expect($fresh->microsoft_email)->toBe('colleague@company.com');
            expect($fresh->status_source)->toBe(StatusSource::Microsoft);
            expect($fresh->status_synced_at)->toBeNull();
        });

        it('auto-sets status_source to manual when email is not a known O365 account', function (): void {
            /** @var \Tests\TestCase $this */
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $member = TeamMember::factory()->create([
                'user_id'       => $user->id,
                'status_source' => StatusSource::Microsoft,
            ]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldReceive('isKnownMicrosoftUser')
                ->with($user, 'external@gmail.com')
                ->once()
                ->andReturn(false);
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'email' => 'external@gmail.com',
                ])
                ->assertOk()
                ->assertJson(['success' => true, 'status_source' => 'manual']);

            $fresh = $member->fresh();
            expect($fresh->status_source)->toBe(StatusSource::Manual);
            expect($fresh->microsoft_email)->toBeNull();
        });

        it('clears microsoft_email and resets status_source when email is cleared', function (): void {
            /** @var \Tests\TestCase $this */
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $member = TeamMember::factory()->create([
                'user_id'         => $user->id,
                'email'           => 'colleague@company.com',
                'microsoft_email' => 'colleague@company.com',
                'status_source'   => StatusSource::Microsoft,
            ]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'email' => null,
                ])
                ->assertOk()
                ->assertJson(['success' => true, 'status_source' => 'manual']);

            $fresh = $member->fresh();
            expect($fresh->email)->toBeNull();
            expect($fresh->microsoft_email)->toBeNull();
            expect($fresh->status_source)->toBe(StatusSource::Manual);
        });

        it('defaults status_source to manual when user has no Microsoft connection', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'email' => 'someone@company.com',
                ])
                ->assertOk()
                ->assertJson(['success' => true, 'status_source' => 'manual']);

            expect($member->fresh()->status_source)->toBe(StatusSource::Manual);
            expect($member->fresh()->microsoft_email)->toBeNull();
        });

        it('gracefully falls back to manual when O365 lookup fails', function (): void {
            /** @var \Tests\TestCase $this */
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldReceive('isKnownMicrosoftUser')
                ->andThrow(new RuntimeException('Graph API error'));
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'email' => 'colleague@company.com',
                ])
                ->assertOk()
                ->assertJson(['success' => true, 'status_source' => 'manual']);

            expect($member->fresh()->status_source)->toBe(StatusSource::Manual);
        });

        it('does not call O365 lookup when email is not in the request', function (): void {
            /** @var \Tests\TestCase $this */
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $member = TeamMember::factory()->create([
                'user_id'         => $user->id,
                'email'           => 'colleague@company.com',
                'microsoft_email' => 'colleague@company.com',
                'status_source'   => StatusSource::Microsoft,
            ]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldNotReceive('isKnownMicrosoftUser');
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'name' => 'Updated Name',
                ])
                ->assertOk();

            expect($member->fresh()->status_source)->toBe(StatusSource::Microsoft);
        });

        it('resets status_synced_at when status_source changes to microsoft', function (): void {
            /** @var \Tests\TestCase $this */
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $member = TeamMember::factory()->create([
                'user_id'          => $user->id,
                'status_source'    => StatusSource::Manual,
                'status_synced_at' => now()->subHour(),
            ]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldReceive('isKnownMicrosoftUser')->andReturn(true);
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'email' => 'colleague@company.com',
                ])
                ->assertOk();

            expect($member->fresh()->status_synced_at)->toBeNull();
        });

        it('no longer accepts status_source as a direct input field', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'       => $user->id,
                'status_source' => StatusSource::Manual,
            ]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status_source' => 'microsoft',
                ])
                ->assertOk();

            expect($member->fresh()->status_source)->toBe(StatusSource::Manual);
        });

        it('no longer accepts microsoft_email as a direct input field', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'microsoft_email' => 'someone@company.com',
                ])
                ->assertOk();

            expect($member->fresh()->microsoft_email)->toBeNull();
        });
    });

    describe('updateMember() — status changes', function (): void {
        it('blocks manual status changes when status_source is microsoft', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'       => $user->id,
                'status'        => MemberStatus::Available,
                'status_source' => StatusSource::Microsoft,
            ]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status' => 'absent',
                ])
                ->assertOk()
                ->assertJson(['success' => true]);

            expect($member->fresh()->status)->toBe(MemberStatus::Available);
        });

        it('allows manual status changes when status_source is manual', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'       => $user->id,
                'status'        => MemberStatus::Available,
                'status_source' => StatusSource::Manual,
            ]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status' => 'absent',
                ])
                ->assertOk()
                ->assertJson(['success' => true]);

            expect($member->fresh()->status)->toBe(MemberStatus::Absent);
        });

        it('validates status against MemberStatus enum values', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status' => 'not_a_valid_status',
                ])
                ->assertUnprocessable();
        });

        it('accepts all valid MemberStatus enum values', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();

            foreach (MemberStatus::cases() as $memberStatus) {
                $member = TeamMember::factory()->create([
                    'user_id'       => $user->id,
                    'status_source' => StatusSource::Manual,
                ]);

                $this->actingAs($user)
                    ->patchJson(route('members.update', $member), [
                        'status' => $memberStatus->value,
                    ])
                    ->assertOk();
            }
        });
    });

    describe('storeMember() — O365 auto-detection on creation', function (): void {
        it('auto-sets status_source to microsoft when email is a known O365 account', function (): void {
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $team = Team::factory()->create(['user_id' => $user->id]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldReceive('isKnownMicrosoftUser')
                ->with($user, 'colleague@company.com')
                ->once()
                ->andReturn(true);
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->post(route('teams.members.store', $team), [
                    'name'  => 'Colleague',
                    'email' => 'colleague@company.com',
                ])
                ->assertRedirect();

            $member = TeamMember::where('email', 'colleague@company.com')->first();
            expect($member->status_source)->toBe(StatusSource::Microsoft);
            expect($member->microsoft_email)->toBe('colleague@company.com');
        });

        it('defaults to manual when email is not a known O365 account', function (): void {
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $team = Team::factory()->create(['user_id' => $user->id]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldReceive('isKnownMicrosoftUser')
                ->with($user, 'external@gmail.com')
                ->once()
                ->andReturn(false);
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->post(route('teams.members.store', $team), [
                    'name'  => 'External',
                    'email' => 'external@gmail.com',
                ])
                ->assertRedirect();

            $member = TeamMember::where('email', 'external@gmail.com')->first();
            expect($member->status_source)->toBe(StatusSource::Manual);
            expect($member->microsoft_email)->toBeNull();
        });

        it('defaults to manual when no email is provided', function (): void {
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $team = Team::factory()->create(['user_id' => $user->id]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldNotReceive('isKnownMicrosoftUser');
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->post(route('teams.members.store', $team), [
                    'name' => 'No Email',
                ])
                ->assertRedirect();

            $member = TeamMember::where('name', 'No Email')->first();
            expect($member->status_source)->toBe(StatusSource::Manual);
            expect($member->microsoft_email)->toBeNull();
        });

        it('defaults to manual when user has no Microsoft connection', function (): void {
            $user = User::factory()->create(['microsoft_id' => null]);
            $team = Team::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->post(route('teams.members.store', $team), [
                    'name'  => 'Disconnected',
                    'email' => 'someone@company.com',
                ])
                ->assertRedirect();

            $member = TeamMember::where('email', 'someone@company.com')->first();
            expect($member->status_source)->toBe(StatusSource::Manual);
            expect($member->microsoft_email)->toBeNull();
        });

        it('gracefully falls back to manual when O365 lookup fails on creation', function (): void {
            $user = User::factory()->create([
                'microsoft_id'    => 'ms-id-123',
                'microsoft_email' => 'user@company.com',
            ]);
            $team = Team::factory()->create(['user_id' => $user->id]);

            $mock = Mockery::mock(MicrosoftGraphService::class);
            $mock->shouldReceive('isKnownMicrosoftUser')
                ->andThrow(new RuntimeException('Graph API error'));
            $this->app->instance(MicrosoftGraphService::class, $mock);

            $this->actingAs($user)
                ->post(route('teams.members.store', $team), [
                    'name'  => 'Error Member',
                    'email' => 'error@company.com',
                ])
                ->assertRedirect();

            $member = TeamMember::where('email', 'error@company.com')->first();
            expect($member->status_source)->toBe(StatusSource::Manual);
            expect($member->microsoft_email)->toBeNull();
        });
    });

    describe('member profile page', function (): void {
        it('does not show a separate microsoft email field', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->get(route('teams.member', $member))
                ->assertOk()
                ->assertDontSee('Microsoft email (for availability sync)');
        });

        it('does not show a manual status source dropdown', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->get(route('teams.member', $member))
                ->assertOk()
                ->assertDontSee('Status source');
        });

        it('shows the auto-sync indicator when status_source is microsoft', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'       => $user->id,
                'status_source' => StatusSource::Microsoft,
            ]);

            $this->actingAs($user)
                ->get(route('teams.member', $member))
                ->assertOk()
                ->assertSee('Auto-synced via Office 365');
        });

        it('does not show the auto-sync indicator when status_source is manual', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'       => $user->id,
                'status_source' => StatusSource::Manual,
            ]);

            $this->actingAs($user)
                ->get(route('teams.member', $member))
                ->assertOk()
                ->assertDontSee('Auto-synced via Office 365');
        });
    });
});
