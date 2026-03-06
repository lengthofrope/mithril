<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides dynamic filter scopes for Eloquent models.
 *
 * Models using this trait must define a $filterableFields array in the format:
 * [
 *   'field_name' => 'type', // exact|like|date_range|boolean
 * ]
 */
trait Filterable
{
    /**
     * Apply an array of filters to the query.
     *
     * @param Builder $query
     * @param array<string, mixed> $filters
     * @return Builder
     */
    public function scopeApplyFilters(Builder $query, array $filters): Builder
    {
        $fields = $this->filterableFields ?? [];

        foreach ($filters as $field => $value) {
            if (!isset($fields[$field]) || $value === null || $value === '') {
                continue;
            }

            $type = $fields[$field];

            match ($type) {
                'exact' => $query->where($field, $value),
                'like' => $query->where($field, 'like', '%' . $value . '%'),
                'boolean' => $query->where($field, (bool) $value),
                'date_range' => $this->applyDateRangeFilter($query, $field, $value),
                default => null,
            };
        }

        return $query;
    }

    /**
     * Apply a date range filter for a given field.
     *
     * @param Builder $query
     * @param string $field
     * @param array{from?: string, to?: string} $value
     * @return void
     */
    private function applyDateRangeFilter(Builder $query, string $field, array $value): void
    {
        if (!empty($value['from'])) {
            $query->whereDate($field, '>=', $value['from']);
        }

        if (!empty($value['to'])) {
            $query->whereDate($field, '<=', $value['to']);
        }
    }
}
