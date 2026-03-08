<?php

declare(strict_types=1);

use App\Enums\ChartType;
use App\Enums\DataSource;
use App\Models\AnalyticsWidget;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

describe('AnalyticsWidget model', function (): void {
    describe('traits', function (): void {
        it('uses the BelongsToUser trait', function (): void {
            expect(in_array(BelongsToUser::class, class_uses_recursive(AnalyticsWidget::class)))->toBeTrue();
        });
    });

    describe('fillable attributes', function (): void {
        it('allows mass assignment of all defined fields', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source'           => DataSource::TasksByStatus,
                'chart_type'            => ChartType::Bar,
                'title'                 => 'Status Overview',
                'column_span'           => 2,
                'show_on_analytics'     => true,
                'show_on_dashboard'     => true,
                'sort_order_analytics'  => 3,
                'sort_order_dashboard'  => 5,
            ]);

            expect($widget->title)->toBe('Status Overview')
                ->and($widget->column_span)->toBe(2)
                ->and($widget->show_on_analytics)->toBeTrue()
                ->and($widget->show_on_dashboard)->toBeTrue()
                ->and($widget->sort_order_analytics)->toBe(3)
                ->and($widget->sort_order_dashboard)->toBe(5);
        });
    });

    describe('enum casts', function (): void {
        it('casts data_source to DataSource enum', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source' => DataSource::TasksByPriority,
                'chart_type'  => ChartType::Donut,
            ]);

            expect($widget->fresh()->data_source)->toBe(DataSource::TasksByPriority);
        });

        it('casts chart_type to ChartType enum', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source' => DataSource::TasksByStatus,
                'chart_type'  => ChartType::BarHorizontal,
            ]);

            expect($widget->fresh()->chart_type)->toBe(ChartType::BarHorizontal);
        });
    });

    describe('boolean casts', function (): void {
        it('casts show_on_analytics to boolean', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source'       => DataSource::TasksByStatus,
                'chart_type'        => ChartType::Bar,
                'show_on_analytics' => true,
            ]);

            expect($widget->fresh()->show_on_analytics)->toBeTrue();
        });

        it('casts show_on_dashboard to boolean', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source'       => DataSource::TasksByStatus,
                'chart_type'        => ChartType::Bar,
                'show_on_dashboard' => true,
            ]);

            expect($widget->fresh()->show_on_dashboard)->toBeTrue();
        });
    });

    describe('integer casts', function (): void {
        it('casts column_span to integer', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source' => DataSource::TasksByStatus,
                'chart_type'  => ChartType::Bar,
                'column_span' => 2,
            ]);

            expect($widget->fresh()->column_span)->toBe(2);
        });

        it('casts sort_order_analytics to integer', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source'          => DataSource::TasksByStatus,
                'chart_type'           => ChartType::Bar,
                'sort_order_analytics' => 7,
            ]);

            expect($widget->fresh()->sort_order_analytics)->toBe(7);
        });

        it('casts sort_order_dashboard to integer', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source'          => DataSource::TasksByStatus,
                'chart_type'           => ChartType::Bar,
                'sort_order_dashboard' => 4,
            ]);

            expect($widget->fresh()->sort_order_dashboard)->toBe(4);
        });
    });

    describe('relationships', function (): void {
        it('belongs to a User', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widget = AnalyticsWidget::create([
                'data_source' => DataSource::TasksByStatus,
                'chart_type'  => ChartType::Bar,
            ]);

            expect($widget->user())->toBeInstanceOf(BelongsTo::class)
                ->and($widget->user->id)->toBe($user->id);
        });
    });

    describe('scopes', function (): void {
        it('forAnalytics scope returns only widgets with show_on_analytics true', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            AnalyticsWidget::create(['data_source' => DataSource::TasksByStatus, 'chart_type' => ChartType::Bar, 'show_on_analytics' => true, 'show_on_dashboard' => false]);
            AnalyticsWidget::create(['data_source' => DataSource::TasksByPriority, 'chart_type' => ChartType::Donut, 'show_on_analytics' => false, 'show_on_dashboard' => true]);

            $results = AnalyticsWidget::forAnalytics()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->data_source)->toBe(DataSource::TasksByStatus);
        });

        it('forAnalytics scope orders by sort_order_analytics', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            AnalyticsWidget::create(['data_source' => DataSource::TasksByStatus, 'chart_type' => ChartType::Bar, 'show_on_analytics' => true, 'sort_order_analytics' => 2]);
            AnalyticsWidget::create(['data_source' => DataSource::TasksByPriority, 'chart_type' => ChartType::Bar, 'show_on_analytics' => true, 'sort_order_analytics' => 1]);
            AnalyticsWidget::create(['data_source' => DataSource::TasksByCategory, 'chart_type' => ChartType::Bar, 'show_on_analytics' => true, 'sort_order_analytics' => 3]);

            $results = AnalyticsWidget::forAnalytics()->get();

            expect($results->pluck('sort_order_analytics')->all())->toBe([1, 2, 3]);
        });

        it('forDashboard scope returns only widgets with show_on_dashboard true', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            AnalyticsWidget::create(['data_source' => DataSource::TasksByStatus, 'chart_type' => ChartType::Bar, 'show_on_analytics' => false, 'show_on_dashboard' => true]);
            AnalyticsWidget::create(['data_source' => DataSource::TasksByPriority, 'chart_type' => ChartType::Donut, 'show_on_analytics' => true, 'show_on_dashboard' => false]);

            $results = AnalyticsWidget::forDashboard()->get();

            expect($results)->toHaveCount(1)
                ->and($results->first()->data_source)->toBe(DataSource::TasksByStatus);
        });

        it('forDashboard scope orders by sort_order_dashboard', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            AnalyticsWidget::create(['data_source' => DataSource::TasksByStatus, 'chart_type' => ChartType::Bar, 'show_on_dashboard' => true, 'sort_order_dashboard' => 3]);
            AnalyticsWidget::create(['data_source' => DataSource::TasksByPriority, 'chart_type' => ChartType::Bar, 'show_on_dashboard' => true, 'sort_order_dashboard' => 1]);
            AnalyticsWidget::create(['data_source' => DataSource::TasksByCategory, 'chart_type' => ChartType::Bar, 'show_on_dashboard' => true, 'sort_order_dashboard' => 2]);

            $results = AnalyticsWidget::forDashboard()->get();

            expect($results->pluck('sort_order_dashboard')->all())->toBe([1, 2, 3]);
        });
    });

    describe('reorderForContext', function (): void {
        it('reorderForContext updates sort_order_analytics when context is analytics', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widgetA = AnalyticsWidget::create(['data_source' => DataSource::TasksByStatus, 'chart_type' => ChartType::Bar, 'sort_order_analytics' => 1]);
            $widgetB = AnalyticsWidget::create(['data_source' => DataSource::TasksByPriority, 'chart_type' => ChartType::Bar, 'sort_order_analytics' => 2]);

            AnalyticsWidget::reorderForContext([
                ['id' => $widgetA->id, 'sort_order' => 5],
                ['id' => $widgetB->id, 'sort_order' => 3],
            ], 'analytics');

            expect($widgetA->fresh()->sort_order_analytics)->toBe(5)
                ->and($widgetB->fresh()->sort_order_analytics)->toBe(3);
        });

        it('reorderForContext updates sort_order_dashboard when context is dashboard', function (): void {
            $user = User::factory()->create();
            $this->actingAs($user);

            $widgetA = AnalyticsWidget::create(['data_source' => DataSource::TasksByStatus, 'chart_type' => ChartType::Bar, 'sort_order_dashboard' => 1]);
            $widgetB = AnalyticsWidget::create(['data_source' => DataSource::TasksByPriority, 'chart_type' => ChartType::Bar, 'sort_order_dashboard' => 2]);

            AnalyticsWidget::reorderForContext([
                ['id' => $widgetA->id, 'sort_order' => 10],
                ['id' => $widgetB->id, 'sort_order' => 7],
            ], 'dashboard');

            expect($widgetA->fresh()->sort_order_dashboard)->toBe(10)
                ->and($widgetB->fresh()->sort_order_dashboard)->toBe(7);
        });

        it('reorderForContext throws InvalidArgumentException for unknown context', function (): void {
            expect(fn () => AnalyticsWidget::reorderForContext([], 'sidebar'))
                ->toThrow(\InvalidArgumentException::class);
        });
    });
});
