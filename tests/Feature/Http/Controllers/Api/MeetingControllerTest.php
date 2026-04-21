<?php

declare(strict_types=1);

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('Meeting API creation', function (): void {
    it('accepts null scheduled_at when creating a meeting', function () {
        /** @var \Tests\TestCase $this */
        $response = $this->postJson('/api/v1/meetings', [
            'title' => 'Planning Session',
            'type' => MeetingType::Team->value,
            'status' => MeetingStatus::Scheduled->value,
            'scheduled_at' => null,
            'notes' => 'Preparation meeting',
            'transcription_language' => 'en',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('meetings', [
            'user_id' => $this->user->id,
            'title' => 'Planning Session',
            'scheduled_at' => null,
        ]);
    });

    it('creates a meeting without scheduled_at field in request', function () {
        /** @var \Tests\TestCase $this */
        $response = $this->postJson('/api/v1/meetings', [
            'title' => 'Prep Meeting',
            'type' => MeetingType::OneOnOne->value,
            'status' => MeetingStatus::Scheduled->value,
            'notes' => 'To be scheduled',
            'transcription_language' => 'en',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('meetings', [
            'user_id' => $this->user->id,
            'title' => 'Prep Meeting',
            'scheduled_at' => null,
        ]);
    });
});
