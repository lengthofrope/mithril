<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provides follow-up relationship and timeline scopes for Eloquent models.
 */
trait HasFollowUp
{
    /**
     * Get all follow-ups related to this model.
     *
     * @return HasMany<FollowUp>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Scope to follow-ups that are past their due date.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereDate('follow_up_date', '<', now()->toDateString())
            ->where('status', '!=', 'done');
    }

    /**
     * Scope to follow-ups due today.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('follow_up_date', now()->toDateString())
            ->where('status', '!=', 'done');
    }

    /**
     * Scope to follow-ups due within the current week (excluding today).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDueThisWeek(Builder $query): Builder
    {
        return $query->whereDate('follow_up_date', '>', now()->toDateString())
            ->whereDate('follow_up_date', '<=', now()->endOfWeek()->toDateString())
            ->where('status', '!=', 'done');
    }

    /**
     * Scope to follow-ups due after the current week.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('follow_up_date', '>', now()->endOfWeek()->toDateString())
            ->where('status', '!=', 'done');
    }
}
