<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChartType;
use App\Enums\DataSource;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Analytics widget configuration owned by a user.
 *
 * Each widget maps a DataSource to a ChartType and can appear on the analytics
 * page, the dashboard, or both. The two contexts have independent sort orders,
 * so HasSortOrder is not used here — reordering is handled by reorderForContext().
 *
 * @property int        $id
 * @property int        $user_id
 * @property DataSource $data_source
 * @property ChartType  $chart_type
 * @property string     $title
 * @property int        $column_span
 * @property bool       $show_on_analytics
 * @property bool       $show_on_dashboard
 * @property int        $sort_order_analytics
 * @property int        $sort_order_dashboard
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AnalyticsWidget extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'data_source',
        'chart_type',
        'title',
        'column_span',
        'show_on_analytics',
        'show_on_dashboard',
        'sort_order_analytics',
        'sort_order_dashboard',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_source'           => DataSource::class,
            'chart_type'            => ChartType::class,
            'show_on_analytics'     => 'boolean',
            'show_on_dashboard'     => 'boolean',
            'column_span'           => 'integer',
            'sort_order_analytics'  => 'integer',
            'sort_order_dashboard'  => 'integer',
        ];
    }

    /**
     * Scope to widgets visible on the analytics page, ordered by their analytics sort position.
     *
     * @param Builder<AnalyticsWidget> $query
     * @return Builder<AnalyticsWidget>
     */
    public function scopeForAnalytics(Builder $query): Builder
    {
        return $query->where('show_on_analytics', true)->orderBy('sort_order_analytics');
    }

    /**
     * Scope to widgets visible on the dashboard, ordered by their dashboard sort position.
     *
     * @param Builder<AnalyticsWidget> $query
     * @return Builder<AnalyticsWidget>
     */
    public function scopeForDashboard(Builder $query): Builder
    {
        return $query->where('show_on_dashboard', true)->orderBy('sort_order_dashboard');
    }

    /**
     * Reorder widgets for a given display context in a single batch operation.
     *
     * Accepts an array of id/sort_order pairs and writes them to the column
     * that corresponds to the requested context ('analytics' or 'dashboard').
     *
     * @param array<int, array{id: int, sort_order: int}> $items
     * @param string                                       $context Either 'analytics' or 'dashboard'.
     * @return void
     * @throws \InvalidArgumentException When an unrecognised context is supplied.
     */
    public static function reorderForContext(array $items, string $context): void
    {
        $column = match ($context) {
            'analytics' => 'sort_order_analytics',
            'dashboard'  => 'sort_order_dashboard',
            default      => throw new \InvalidArgumentException(
                "Unknown analytics widget context \"{$context}\". Expected 'analytics' or 'dashboard'."
            ),
        };

        foreach ($items as $item) {
            static::query()->where('id', $item['id'])->update([$column => $item['sort_order']]);
        }
    }
}
