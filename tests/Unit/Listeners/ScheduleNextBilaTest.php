<?php

declare(strict_types=1);

use App\Events\BilaScheduled;
use App\Listeners\ScheduleNextBila;
use App\Models\Bila;
use App\Models\TeamMember;

test('updates next_bila_date on team member based on interval', function () {
    $member = TeamMember::factory()->create(['bila_interval_days' => 14]);
    $bila = Bila::factory()->create([
        'team_member_id' => $member->id,
        'scheduled_date' => '2026-03-06',
    ]);

    $event = new BilaScheduled($bila);
    (new ScheduleNextBila())->handle($event);

    $member->refresh();
    expect($member->next_bila_date->toDateString())->toBe('2026-03-20');
});

test('calculates next bila date by adding interval_days to scheduled_date', function () {
    $member = TeamMember::factory()->create(['bila_interval_days' => 7]);
    $bila = Bila::factory()->create([
        'team_member_id' => $member->id,
        'scheduled_date' => '2026-04-01',
    ]);

    $event = new BilaScheduled($bila);
    (new ScheduleNextBila())->handle($event);

    $member->refresh();
    expect($member->next_bila_date->toDateString())->toBe('2026-04-08');
});

test('does not update next_bila_date when bila_interval_days is zero', function () {
    $member = TeamMember::factory()->create([
        'bila_interval_days' => 0,
        'next_bila_date' => null,
    ]);
    $bila = Bila::factory()->create(['team_member_id' => $member->id]);

    $event = new BilaScheduled($bila);
    (new ScheduleNextBila())->handle($event);

    $member->refresh();
    expect($member->next_bila_date)->toBeNull();
});

test('does not update next_bila_date when bila_interval_days is negative', function () {
    $member = TeamMember::factory()->create([
        'bila_interval_days' => -7,
        'next_bila_date' => null,
    ]);
    $bila = Bila::factory()->create(['team_member_id' => $member->id]);

    $event = new BilaScheduled($bila);
    (new ScheduleNextBila())->handle($event);

    $member->refresh();
    expect($member->next_bila_date)->toBeNull();
});

test('overwrites existing next_bila_date with newly calculated date', function () {
    $member = TeamMember::factory()->create([
        'bila_interval_days' => 14,
        'next_bila_date' => '2026-01-01',
    ]);
    $bila = Bila::factory()->create([
        'team_member_id' => $member->id,
        'scheduled_date' => '2026-03-10',
    ]);

    $event = new BilaScheduled($bila);
    (new ScheduleNextBila())->handle($event);

    $member->refresh();
    expect($member->next_bila_date->toDateString())->toBe('2026-03-24');
});

test('handles bila with no associated team member gracefully', function () {
    $member = TeamMember::factory()->create(['bila_interval_days' => 14]);
    $bila = Bila::factory()->create([
        'team_member_id' => $member->id,
        'scheduled_date' => '2026-03-06',
    ]);

    $member->delete();
    $bila->unsetRelation('teamMember');

    $event = new BilaScheduled($bila);

    expect(fn () => (new ScheduleNextBila())->handle($event))->not->toThrow(Throwable::class);
});
