<?php

declare(strict_types=1);

use App\Enums\ChartType;
use App\Enums\DataSource;
use App\Models\AnalyticsWidget;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ---------------------------------------------------------------------------
// analytics_widget reordering
// ---------------------------------------------------------------------------

test('reorder updates sort_order_analytics for analytics widgets', function () {
    /** @var \Tests\TestCase $this */
    $widgetA = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_analytics' => 1,
    ]);
    $widgetB = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_analytics' => 2,
    ]);
    $widgetC = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_analytics' => 3,
    ]);

    $response = $this->postJson('/api/v1/reorder', [
        'model_type' => 'analytics_widget',
        'sort_field' => 'sort_order_analytics',
        'items'      => [
            ['id' => $widgetA->id, 'sort_order' => 3],
            ['id' => $widgetB->id, 'sort_order' => 1],
            ['id' => $widgetC->id, 'sort_order' => 2],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Reordered successfully.',
        ]);

    $this->assertDatabaseHas('analytics_widgets', ['id' => $widgetA->id, 'sort_order_analytics' => 3]);
    $this->assertDatabaseHas('analytics_widgets', ['id' => $widgetB->id, 'sort_order_analytics' => 1]);
    $this->assertDatabaseHas('analytics_widgets', ['id' => $widgetC->id, 'sort_order_analytics' => 2]);
});

test('reorder updates sort_order_dashboard for analytics widgets', function () {
    /** @var \Tests\TestCase $this */
    $widgetA = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_dashboard' => 1,
    ]);
    $widgetB = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_dashboard' => 2,
    ]);

    $response = $this->postJson('/api/v1/reorder', [
        'model_type' => 'analytics_widget',
        'sort_field' => 'sort_order_dashboard',
        'items'      => [
            ['id' => $widgetA->id, 'sort_order' => 2],
            ['id' => $widgetB->id, 'sort_order' => 1],
        ],
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('analytics_widgets', ['id' => $widgetA->id, 'sort_order_dashboard' => 2]);
    $this->assertDatabaseHas('analytics_widgets', ['id' => $widgetB->id, 'sort_order_dashboard' => 1]);
});

test('reorder returns 422 when sort_field is missing for analytics_widget', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_analytics' => 1,
    ]);

    $response = $this->postJson('/api/v1/reorder', [
        'model_type' => 'analytics_widget',
        'items'      => [
            ['id' => $widget->id, 'sort_order' => 1],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
        ]);
});

test('reorder returns 422 validation error when sort_field is an invalid value for analytics_widget', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_analytics' => 1,
    ]);

    $response = $this->postJson('/api/v1/reorder', [
        'model_type' => 'analytics_widget',
        'sort_field' => 'not_a_valid_sort_field',
        'items'      => [
            ['id' => $widget->id, 'sort_order' => 1],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_field']);
});

test('reorder still works for task model_type without sort_field as regression check', function () {
    /** @var \Tests\TestCase $this */
    $taskA = Task::factory()->create(['user_id' => $this->user->id, 'sort_order' => 1]);
    $taskB = Task::factory()->create(['user_id' => $this->user->id, 'sort_order' => 2]);

    $response = $this->postJson('/api/v1/reorder', [
        'model_type' => 'task',
        'items'      => [
            ['id' => $taskA->id, 'sort_order' => 2],
            ['id' => $taskB->id, 'sort_order' => 1],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Reordered successfully.',
        ]);

    $this->assertDatabaseHas('tasks', ['id' => $taskA->id, 'sort_order' => 2]);
    $this->assertDatabaseHas('tasks', ['id' => $taskB->id, 'sort_order' => 1]);
});

test('reorder response data is null on success for analytics_widget', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_analytics' => 1,
    ]);

    $response = $this->postJson('/api/v1/reorder', [
        'model_type' => 'analytics_widget',
        'sort_field' => 'sort_order_analytics',
        'items'      => [
            ['id' => $widget->id, 'sort_order' => 5],
        ],
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data'    => null,
        ]);
});
