<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('setting prune_after_days via settings page saves to user', function () {
    $response = $this->patchJson(route('settings.updatePruneAfterDays'), [
        'prune_after_days' => 90,
    ]);

    $response->assertOk()->assertJson(['success' => true]);
    $this->user->refresh();
    expect($this->user->prune_after_days)->toBe(90);
});

test('setting prune_after_days below 30 is rejected', function () {
    $response = $this->patchJson(route('settings.updatePruneAfterDays'), [
        'prune_after_days' => 10,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('prune_after_days');
});

test('setting prune_after_days above 365 is rejected', function () {
    $response = $this->patchJson(route('settings.updatePruneAfterDays'), [
        'prune_after_days' => 500,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('prune_after_days');
});

test('clearing prune_after_days disables pruning', function () {
    $this->user->update(['prune_after_days' => 90]);

    $response = $this->patchJson(route('settings.updatePruneAfterDays'), [
        'prune_after_days' => null,
    ]);

    $response->assertOk();
    $this->user->refresh();
    expect($this->user->prune_after_days)->toBeNull();
});

test('manual prune button triggers pruning and shows results', function () {
    $this->user->update(['prune_after_days' => 30]);

    Task::factory()->create([
        'user_id' => $this->user->id,
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(45),
    ]);

    $response = $this->post(route('settings.prune'));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('status');
    $this->assertDatabaseCount('tasks', 0);
});

test('manual prune requires prune_after_days to be configured', function () {
    $this->user->update(['prune_after_days' => null]);

    $response = $this->post(route('settings.prune'));

    $response->assertRedirect(route('settings.index'));
    $response->assertSessionHas('error');
});
