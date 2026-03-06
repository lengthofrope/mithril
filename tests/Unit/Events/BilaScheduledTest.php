<?php

declare(strict_types=1);

use App\Events\BilaScheduled;
use App\Models\Bila;

test('bila scheduled event carries the bila model', function () {
    $bila = Bila::factory()->create();

    $event = new BilaScheduled($bila);

    expect($event->bila)->toBeInstanceOf(Bila::class);
    expect($event->bila->id)->toBe($bila->id);
});

test('bila scheduled event bila property matches the given instance', function () {
    $bila = Bila::factory()->create(['scheduled_date' => '2026-04-01']);

    $event = new BilaScheduled($bila);

    expect($event->bila->scheduled_date->format('Y-m-d'))->toBe('2026-04-01');
});

test('bila scheduled event bila references correct team member', function () {
    $bila = Bila::factory()->create();

    $event = new BilaScheduled($bila);

    expect($event->bila->team_member_id)->toBe($bila->team_member_id);
});

test('bila scheduled event property is readonly', function () {
    $bila = Bila::factory()->create();

    $event = new BilaScheduled($bila);

    expect(fn () => $event->bila = Bila::factory()->create())->toThrow(Error::class);
});
