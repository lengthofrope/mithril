<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\FollowUpStatus;
use App\Models\FollowUp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provides follow-up relationship and timeline scopes for parent models (Task, TeamMember).
 *
 * Scopes filter the parent model based on the state of its related follow-ups
 * using whereHas, so they work correctly on any model with a followUps() relation.
 */
trait HasFollowUp
{
    /**
     * Get all follow-ups related to this model.
     *
     * @return HasMany<FollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Scope to models that have at least one overdue, non-done follow-up.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithOverdueFollowUps(Builder $query): Builder
    {
        return $query->whereHas('followUps', function (Builder $q): void {
            $q->whereDate('follow_up_date', '<', now()->toDateString())
                ->where('status', '!=', FollowUpStatus::Done);
        });
    }

    /**
     * Scope to models that have at least one follow-up due today and not done.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithFollowUpsDueToday(Builder $query): Builder
    {
        return $query->whereHas('followUps', function (Builder $q): void {
            $q->whereDate('follow_up_date', now()->toDateString())
                ->where('status', '!=', FollowUpStatus::Done);
        });
    }

    /**
     * Scope to models that have at least one follow-up due this week (excluding today) and not done.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithFollowUpsDueThisWeek(Builder $query): Builder
    {
        return $query->whereHas('followUps', function (Builder $q): void {
            $q->whereDate('follow_up_date', '>', now()->toDateString())
                ->whereDate('follow_up_date', '<=', now()->endOfWeek()->toDateString())
                ->where('status', '!=', FollowUpStatus::Done);
        });
    }

    /**
     * Scope to models that have at least one follow-up due after this week and not done.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithUpcomingFollowUps(Builder $query): Builder
    {
        return $query->whereHas('followUps', function (Builder $q): void {
            $q->whereDate('follow_up_date', '>', now()->endOfWeek()->toDateString())
                ->where('status', '!=', FollowUpStatus::Done);
        });
    }
}
