<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides full-text search capability for Eloquent models using LIKE queries.
 *
 * Models using this trait must define a $searchableFields array:
 * protected array $searchableFields = ['title', 'description'];
 */
trait Searchable
{
    /**
     * Scope to search across all defined searchable fields.
     *
     * @param Builder $query
     * @param string $term
     * @return Builder
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $fields = $this->searchableFields ?? [];

        if (empty($fields) || $term === '') {
            return $query;
        }

        return $query->where(function (Builder $subQuery) use ($fields, $term): void {
            foreach ($fields as $field) {
                $subQuery->orWhere($field, 'like', '%' . $term . '%');
            }
        });
    }
}
