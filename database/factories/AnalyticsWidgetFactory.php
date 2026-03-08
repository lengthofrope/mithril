<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChartType;
use App\Enums\DataSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AnalyticsWidget>
 */
class AnalyticsWidgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'                => \App\Models\User::factory(),
            'data_source'            => DataSource::TasksByStatus,
            'chart_type'             => ChartType::Bar,
            'title'                  => null,
            'column_span'            => 1,
            'show_on_analytics'      => true,
            'show_on_dashboard'      => false,
            'sort_order_analytics'   => 0,
            'sort_order_dashboard'   => 0,
        ];
    }
}
