<?php

declare(strict_types=1);

use App\Enums\ChartType;
use App\Enums\DataSource;
use App\Models\AnalyticsWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ---------------------------------------------------------------------------
// index
// ---------------------------------------------------------------------------

test('analytics index returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/analytics');

    $response->assertOk();
});

test('analytics index renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/analytics');

    $response->assertViewIs('pages.analytics');
});

test('analytics index passes widgets to the view', function () {
    /** @var \Tests\TestCase $this */
    AnalyticsWidget::factory()->count(2)->create([
        'user_id'           => $this->user->id,
        'show_on_analytics' => true,
    ]);

    $response = $this->get('/analytics');

    $response->assertViewHas('widgets');
    expect($response->viewData('widgets'))->toHaveCount(2);
});

test('analytics index only passes show_on_analytics widgets to the view', function () {
    /** @var \Tests\TestCase $this */
    AnalyticsWidget::factory()->create([
        'user_id'           => $this->user->id,
        'show_on_analytics' => true,
    ]);
    AnalyticsWidget::factory()->create([
        'user_id'           => $this->user->id,
        'show_on_analytics' => false,
    ]);

    $response = $this->get('/analytics');

    expect($response->viewData('widgets'))->toHaveCount(1);
});

test('analytics index requires authentication and redirects guest to login', function () {
    /** @var \Tests\TestCase $this */
    auth()->logout();

    $response = $this->get('/analytics');

    $response->assertRedirect('/login');
});

// ---------------------------------------------------------------------------
// widgetData
// ---------------------------------------------------------------------------

test('widget data returns chart data for a single source', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/analytics/widget-data?sources[]=' . DataSource::TasksByStatus->value);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'sources' => [
                    DataSource::TasksByStatus->value => ['labels', 'series', 'colors'],
                ],
            ],
        ]);
});

test('widget data returns chart data for multiple sources', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson(
        '/analytics/widget-data?sources[]=' . DataSource::TasksByStatus->value
        . '&sources[]=' . DataSource::TasksByPriority->value
    );

    $response->assertOk();
    $sources = $response->json('data.sources');

    expect($sources)->toHaveKey(DataSource::TasksByStatus->value);
    expect($sources)->toHaveKey(DataSource::TasksByPriority->value);
});

test('widget data response has success true and sources key in data', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/analytics/widget-data?sources[]=' . DataSource::FollowUpsByStatus->value);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data'    => ['sources' => []],
        ]);

    expect($response->json('data'))->toHaveKey('sources');
});

test('widget data validates sources is required', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/analytics/widget-data');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sources']);
});

test('widget data validates sources must not be empty', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/analytics/widget-data?sources[]=');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sources.0']);
});

test('widget data validates sources contain only valid DataSource values', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/analytics/widget-data?sources[]=invalid_source');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sources.0']);
});

test('widget data returns chart data for a time-series source', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/analytics/widget-data?sources[]=' . DataSource::TasksOverTime->value);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'sources' => [
                    DataSource::TasksOverTime->value => ['labels', 'series', 'colors'],
                ],
            ],
        ]);
});

test('widget data time-series source returns named series with data arrays', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/analytics/widget-data?sources[]=' . DataSource::TasksOverTime->value);

    $response->assertOk();
    $series = $response->json('data.sources.' . DataSource::TasksOverTime->value . '.series');

    expect($series)->toBeArray()->toHaveCount(4);
    expect($series[0])->toHaveKeys(['name', 'data']);
});

test('widget data accepts time_range parameter for time-series source', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson(
        '/analytics/widget-data?sources[]=' . DataSource::TasksOverTime->value . '&time_range=7d'
    );

    $response->assertOk();
    $labels = $response->json('data.sources.' . DataSource::TasksOverTime->value . '.labels');

    expect($labels)->toHaveCount(7);
});

test('widget data defaults to 30d time range when not specified', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson(
        '/analytics/widget-data?sources[]=' . DataSource::TasksOverTime->value
    );

    $response->assertOk();
    $labels = $response->json('data.sources.' . DataSource::TasksOverTime->value . '.labels');

    expect($labels)->toHaveCount(30);
});

test('widget data validates time_range must be a valid value', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson(
        '/analytics/widget-data?sources[]=' . DataSource::TasksOverTime->value . '&time_range=999d'
    );

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['time_range']);
});

test('widget data can mix point-in-time and time-series sources', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson(
        '/analytics/widget-data?sources[]=' . DataSource::TasksByStatus->value
        . '&sources[]=' . DataSource::TasksOverTime->value
    );

    $response->assertOk();
    $sources = $response->json('data.sources');

    expect($sources)->toHaveKey(DataSource::TasksByStatus->value);
    expect($sources)->toHaveKey(DataSource::TasksOverTime->value);

    $pointInTime = $sources[DataSource::TasksByStatus->value];
    expect($pointInTime['series'])->toBeArray();

    $timeSeries = $sources[DataSource::TasksOverTime->value];
    expect($timeSeries['series'])->toBeArray();
    expect($timeSeries['series'][0])->toHaveKeys(['name', 'data']);
});

test('store creates a time-series widget with time_range', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksOverTime->value,
        'chart_type'  => ChartType::Line->value,
        'column_span' => 2,
        'time_range'  => '7d',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('analytics_widgets', [
        'user_id'     => $this->user->id,
        'data_source' => DataSource::TasksOverTime->value,
        'chart_type'  => ChartType::Line->value,
        'time_range'  => '7d',
    ]);
});

test('update persists time_range change', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create([
        'user_id'     => $this->user->id,
        'data_source' => DataSource::TasksOverTime,
        'chart_type'  => ChartType::Line,
        'time_range'  => '30d',
    ]);

    $response = $this->patchJson("/analytics/widgets/{$widget->id}", [
        'time_range' => '90d',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('analytics_widgets', [
        'id'         => $widget->id,
        'time_range' => '90d',
    ]);
});

test('store validates time_range must be a valid value', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksOverTime->value,
        'chart_type'  => ChartType::Line->value,
        'column_span' => 2,
        'time_range'  => '999d',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['time_range']);
});

// ---------------------------------------------------------------------------
// store
// ---------------------------------------------------------------------------

test('store creates a widget and returns 201', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByStatus->value,
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 2,
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('analytics_widgets', [
        'user_id'     => $this->user->id,
        'data_source' => DataSource::TasksByStatus->value,
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 2,
    ]);
});

test('store auto-assigns sort_order_analytics based on current maximum', function () {
    /** @var \Tests\TestCase $this */
    AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_analytics' => 3,
    ]);

    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByPriority->value,
        'chart_type'  => ChartType::Donut->value,
        'column_span' => 1,
    ]);

    $response->assertStatus(201);

    $widget = AnalyticsWidget::query()
        ->where('data_source', DataSource::TasksByPriority->value)
        ->firstOrFail();

    expect($widget->sort_order_analytics)->toBe(4);
});

test('store auto-assigns sort_order_dashboard based on current maximum', function () {
    /** @var \Tests\TestCase $this */
    AnalyticsWidget::factory()->create([
        'user_id'              => $this->user->id,
        'sort_order_dashboard' => 5,
    ]);

    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByCategory->value,
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 1,
    ]);

    $response->assertStatus(201);

    $widget = AnalyticsWidget::query()
        ->where('data_source', DataSource::TasksByCategory->value)
        ->firstOrFail();

    expect($widget->sort_order_dashboard)->toBe(6);
});

test('store auto-assigns sort_order_analytics to 1 when no widgets exist', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByStatus->value,
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 1,
    ]);

    $response->assertStatus(201);

    $widget = AnalyticsWidget::query()->firstOrFail();
    expect($widget->sort_order_analytics)->toBe(1);
    expect($widget->sort_order_dashboard)->toBe(1);
});

test('store validates data_source is required', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['data_source']);
});

test('store validates chart_type is required', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByStatus->value,
        'column_span' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['chart_type']);
});

test('store validates column_span is between 1 and 3', function () {
    /** @var \Tests\TestCase $this */
    $responseTooLow = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByStatus->value,
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 0,
    ]);

    $responseTooHigh = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByStatus->value,
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 4,
    ]);

    $responseTooLow->assertUnprocessable()->assertJsonValidationErrors(['column_span']);
    $responseTooHigh->assertUnprocessable()->assertJsonValidationErrors(['column_span']);
});

test('store validates data_source must be a valid enum value', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => 'not_a_real_source',
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['data_source']);
});

test('store validates chart_type must be a valid enum value', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByStatus->value,
        'chart_type'  => 'not_a_real_chart_type',
        'column_span' => 1,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['chart_type']);
});

test('store response includes saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->postJson('/analytics/widgets', [
        'data_source' => DataSource::TasksByStatus->value,
        'chart_type'  => ChartType::Bar->value,
        'column_span' => 1,
    ]);

    $response->assertStatus(201);
    expect($response->json('saved_at'))->toBeString()->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

test('update updates widget fields and returns updated widget', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create([
        'user_id'     => $this->user->id,
        'chart_type'  => ChartType::Bar,
        'column_span' => 1,
    ]);

    $response = $this->patchJson("/analytics/widgets/{$widget->id}", [
        'chart_type'  => ChartType::Donut->value,
        'column_span' => 3,
    ]);

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('analytics_widgets', [
        'id'          => $widget->id,
        'chart_type'  => ChartType::Donut->value,
        'column_span' => 3,
    ]);
});

test('update allows partial updates with only chart_type', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create([
        'user_id'      => $this->user->id,
        'chart_type'   => ChartType::Bar,
        'column_span'  => 2,
        'data_source'  => DataSource::TasksByStatus,
    ]);

    $response = $this->patchJson("/analytics/widgets/{$widget->id}", [
        'chart_type' => ChartType::BarHorizontal->value,
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('analytics_widgets', [
        'id'          => $widget->id,
        'chart_type'  => ChartType::BarHorizontal->value,
        'column_span' => 2,
        'data_source' => DataSource::TasksByStatus->value,
    ]);
});

test('update returns updated widget with saved_at timestamp', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create(['user_id' => $this->user->id]);

    $response = $this->patchJson("/analytics/widgets/{$widget->id}", [
        'chart_type' => ChartType::Donut->value,
    ]);

    $response->assertOk();
    expect($response->json('saved_at'))->toBeString()->not->toBeEmpty();
    expect($response->json('success'))->toBeTrue();
});

test('update validates column_span between 1 and 3 when provided', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create(['user_id' => $this->user->id]);

    $responseTooLow = $this->patchJson("/analytics/widgets/{$widget->id}", [
        'column_span' => 0,
    ]);

    $responseTooHigh = $this->patchJson("/analytics/widgets/{$widget->id}", [
        'column_span' => 4,
    ]);

    $responseTooLow->assertUnprocessable()->assertJsonValidationErrors(['column_span']);
    $responseTooHigh->assertUnprocessable()->assertJsonValidationErrors(['column_span']);
});

test('update returns 404 for non-existent widget', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->patchJson('/analytics/widgets/99999', [
        'chart_type' => ChartType::Bar->value,
    ]);

    $response->assertNotFound();
});

// ---------------------------------------------------------------------------
// destroy
// ---------------------------------------------------------------------------

test('destroy deletes the widget from the database', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create(['user_id' => $this->user->id]);

    $response = $this->deleteJson("/analytics/widgets/{$widget->id}");

    $response->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseMissing('analytics_widgets', ['id' => $widget->id]);
});

test('destroy returns success with null data', function () {
    /** @var \Tests\TestCase $this */
    $widget = AnalyticsWidget::factory()->create(['user_id' => $this->user->id]);

    $response = $this->deleteJson("/analytics/widgets/{$widget->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data'    => null,
        ]);
});

test('destroy returns 404 for non-existent widget', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->deleteJson('/analytics/widgets/99999');

    $response->assertNotFound();
});
