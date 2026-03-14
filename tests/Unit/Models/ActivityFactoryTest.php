<?php

declare(strict_types=1);

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Attachment;

describe('Activity factory', function (): void {
    it('creates a default comment activity', function (): void {
        $activity = Activity::factory()->create();

        expect($activity->type)->toBe(ActivityType::Comment)
            ->and($activity->body)->toBeString()
            ->and($activity->activityable)->not->toBeNull();
    });

    it('creates a link activity', function (): void {
        $activity = Activity::factory()->link()->create();

        expect($activity->type)->toBe(ActivityType::Link)
            ->and($activity->metadata)->toHaveKey('url')
            ->and($activity->metadata)->toHaveKey('title');
    });

    it('creates a system activity', function (): void {
        $activity = Activity::factory()->system()->create();

        expect($activity->type)->toBe(ActivityType::System)
            ->and($activity->metadata)->toHaveKey('action')
            ->and($activity->metadata)->toHaveKey('changes');
    });

    it('creates an attachment activity', function (): void {
        $activity = Activity::factory()->attachment()->create();

        expect($activity->type)->toBe(ActivityType::Attachment);
    });
});

describe('Attachment factory', function (): void {
    it('creates a default attachment', function (): void {
        $attachment = Attachment::factory()->create();

        expect($attachment->filename)->toBeString()
            ->and($attachment->mime_type)->toBeString()
            ->and($attachment->size)->toBeGreaterThan(0);
    });
});
