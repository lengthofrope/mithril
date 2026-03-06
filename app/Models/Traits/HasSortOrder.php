<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides sort_order management for Eloquent models.
 *
 * Models using this trait get automatic sort_order assignment on creation,
 * an ordered scope, and a static reorder helper.
 */
trait HasSortOrder
{
    /**
     * Boot the trait and register the creating event listener.
     */
    protected static function bootHasSortOrder(): void
    {
        static::creating(function (self $model): void {
            if ($model->sort_order === null) {
                $model->sort_order = static::query()->max('sort_order') + 1;
            }
        });
    }

    /**
     * Scope to order records by sort_order ascending.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOrderBySortOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Reorder multiple records in a single operation.
     *
     * @param array<int, array{id: int, sort_order: int}> $items
     * @return void
     */
    public static function reorder(array $items): void
    {
        foreach ($items as $item) {
            static::query()->where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }
    }
}
