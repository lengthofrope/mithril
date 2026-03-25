<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingAttendee;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('MeetingAttendee model', function (): void {
    describe('fillable attributes', function (): void {
        it('allows mass assignment of meeting_id and team_member_id', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $meeting = Meeting::factory()->create(['user_id' => $user->id]);

            $attendee = MeetingAttendee::create([
                'meeting_id' => $meeting->id,
                'team_member_id' => $member->id,
            ]);

            expect($attendee->meeting_id)->toBe($meeting->id)
                ->and($attendee->team_member_id)->toBe($member->id);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a Meeting', function (): void {
            $meeting = Meeting::factory()->create();
            $attendee = MeetingAttendee::factory()->create(['meeting_id' => $meeting->id]);

            expect($attendee->meeting())->toBeInstanceOf(BelongsTo::class)
                ->and($attendee->meeting->id)->toBe($meeting->id);
        });

        it('belongs to a TeamMember', function (): void {
            $user = User::factory()->create();
            $team = Team::create(['name' => 'Dev Team', 'user_id' => $user->id]);
            $member = TeamMember::create(['team_id' => $team->id, 'name' => 'Alice', 'user_id' => $user->id]);
            $attendee = MeetingAttendee::factory()->create(['team_member_id' => $member->id]);

            expect($attendee->teamMember())->toBeInstanceOf(BelongsTo::class)
                ->and($attendee->teamMember->id)->toBe($member->id);
        });
    });
});
