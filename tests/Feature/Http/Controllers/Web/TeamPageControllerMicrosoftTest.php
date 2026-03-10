<?php

declare(strict_types=1);

use App\Enums\MemberStatus;
use App\Enums\StatusSource;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;

describe('TeamPageController — Microsoft availability fields', function (): void {
    describe('updateMember()', function (): void {
        it('accepts and persists the microsoft_email field', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'microsoft_email' => 'member@example.com',
                ])
                ->assertOk()
                ->assertJson(['success' => true]);

            expect($member->fresh()->microsoft_email)->toBe('member@example.com');
        });

        it('accepts and persists the status_source field', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status_source' => 'microsoft',
                ])
                ->assertOk()
                ->assertJson(['success' => true]);

            expect($member->fresh()->status_source)->toBe(StatusSource::Microsoft);
        });

        it('rejects an invalid status_source value', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status_source' => 'invalid_source',
                ])
                ->assertUnprocessable();
        });

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

        it('resets status_synced_at to null when switching status_source to microsoft', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'          => $user->id,
                'status_source'    => StatusSource::Manual,
                'status_synced_at' => now()->subHour(),
            ]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status_source' => 'microsoft',
                ])
                ->assertOk()
                ->assertJson(['success' => true]);

            expect($member->fresh()->status_synced_at)->toBeNull();
        });

        it('does not reset status_synced_at when switching status_source to manual', function (): void {
            /** @var \Tests\TestCase $this */
            $user     = User::factory()->create();
            $syncTime = now()->subHour();
            $member   = TeamMember::factory()->create([
                'user_id'          => $user->id,
                'status_source'    => StatusSource::Microsoft,
                'status_synced_at' => $syncTime,
            ]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'status_source' => 'manual',
                ])
                ->assertOk()
                ->assertJson(['success' => true]);

            expect($member->fresh()->status_synced_at)->not->toBeNull();
        });

        it('clears microsoft_email when null is sent', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'         => $user->id,
                'microsoft_email' => 'was-set@example.com',
            ]);

            $this->actingAs($user)
                ->patchJson(route('members.update', $member), [
                    'microsoft_email' => null,
                ])
                ->assertOk();

            expect($member->fresh()->microsoft_email)->toBeNull();
        });
    });

    describe('member profile page', function (): void {
        it('shows the microsoft email field on the member profile page', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'         => $user->id,
                'microsoft_email' => 'sync@example.com',
            ]);

            $this->actingAs($user)
                ->get(route('teams.member', $member))
                ->assertOk()
                ->assertSee('microsoft_email')
                ->assertSee('sync@example.com');
        });

        it('shows the status source dropdown on the member profile page', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create(['user_id' => $user->id]);

            $this->actingAs($user)
                ->get(route('teams.member', $member))
                ->assertOk()
                ->assertSee('status-source')
                ->assertSee('Status source');
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

        it('shows a warning when status_source is microsoft but microsoft_email is missing', function (): void {
            /** @var \Tests\TestCase $this */
            $user   = User::factory()->create();
            $member = TeamMember::factory()->create([
                'user_id'         => $user->id,
                'status_source'   => StatusSource::Microsoft,
                'microsoft_email' => null,
            ]);

            $this->actingAs($user)
                ->get(route('teams.member', $member))
                ->assertOk()
                ->assertSee('Microsoft email is required for auto-sync');
        });
    });
});
