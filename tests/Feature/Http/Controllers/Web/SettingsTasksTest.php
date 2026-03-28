<?php

declare(strict_types=1);

use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('settings tasks page renders category names as text inputs', function () {
    /** @var \Tests\TestCase $this */
    $category = TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Bug Reports']);

    $response = $this->get('/settings/tasks');

    $response->assertOk();
    $response->assertSee('value="Bug Reports"', false);
    $response->assertSee('x-data="inlineAutoSave', false);
});

test('settings tasks page renders group names as text inputs', function () {
    /** @var \Tests\TestCase $this */
    $group = TaskGroup::factory()->create(['user_id' => $this->user->id, 'name' => 'Sprint 12']);

    $response = $this->get('/settings/tasks');

    $response->assertOk();
    $response->assertSee('value="Sprint 12"', false);
});

test('settings tasks page renders group color as color input', function () {
    /** @var \Tests\TestCase $this */
    $group = TaskGroup::factory()->create(['user_id' => $this->user->id, 'color' => '#3b82f6']);

    $response = $this->get('/settings/tasks');

    $response->assertOk();
    $response->assertSee('type="color"', false);
    $response->assertSee('value="#3b82f6"', false);
});

test('settings tasks page renders save status indicator for categories', function () {
    /** @var \Tests\TestCase $this */
    TaskCategory::factory()->create(['user_id' => $this->user->id, 'name' => 'Test']);

    $response = $this->get('/settings/tasks');

    $response->assertOk();
    $response->assertSee('x-text="statusText"', false);
});

test('settings tasks page renders save status indicator for groups', function () {
    /** @var \Tests\TestCase $this */
    TaskGroup::factory()->create(['user_id' => $this->user->id, 'name' => 'Test Group']);

    $response = $this->get('/settings/tasks');

    $response->assertOk();
    $response->assertSee('x-text="statusText"', false);
});
