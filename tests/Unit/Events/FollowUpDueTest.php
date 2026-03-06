<?php

declare(strict_types=1);

use App\Events\FollowUpDue;
use App\Models\FollowUp;

test('follow up due event carries the follow up model', function () {
    $followUp = FollowUp::factory()->create();

    $event = new FollowUpDue($followUp);

    expect($event->followUp)->toBeInstanceOf(FollowUp::class);
    expect($event->followUp->id)->toBe($followUp->id);
});

test('follow up due event follow up property matches the given instance', function () {
    $followUp = FollowUp::factory()->create(['description' => 'Send budget report to stakeholders']);

    $event = new FollowUpDue($followUp);

    expect($event->followUp->description)->toBe('Send budget report to stakeholders');
});

test('follow up due event property is readonly', function () {
    $followUp = FollowUp::factory()->create();

    $event = new FollowUpDue($followUp);

    expect(fn () => $event->followUp = FollowUp::factory()->create())->toThrow(Error::class);
});

test('follow up due event can be constructed with any follow up status', function () {
    $followUp = FollowUp::factory()->create([
        'status' => \App\Enums\FollowUpStatus::Snoozed,
    ]);

    $event = new FollowUpDue($followUp);

    expect($event->followUp->status)->toBe(\App\Enums\FollowUpStatus::Snoozed);
});
