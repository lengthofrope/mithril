<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasActivityFeed;
use App\Models\Traits\HasResourceLinks;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * FollowUp model for tracking items that require a follow-up action.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $task_id
 * @property int|null $team_member_id
 * @property string $title
 * @property string|null $description
 * @property Priority $priority
 * @property bool $is_private
 * @property string|null $waiting_on
 * @property \Illuminate\Support\Carbon|null $follow_up_date
 * @property \Illuminate\Support\Carbon|null $snoozed_until
 * @property FollowUpStatus $status
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class FollowUp extends Model
{
    use BelongsToUser;
    use Filterable;
    use HasActivityFeed;
    use HasFactory;
    use HasResourceLinks;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'task_id',
        'team_member_id',
        'title',
        'description',
        'priority',
        'is_private',
        'waiting_on',
        'follow_up_date',
        'snoozed_until',
        'status',
        'meeting_id',
    ];

    /**
     * Fields available for filtering.
     *
     * @var array<string, string>
     */
    protected array $filterableFields = [
        'status' => 'exact',
        'team_member_id' => 'exact',
        'priority' => 'exact',
        'is_private' => 'boolean',
        'follow_up_date' => 'date_range',
    ];

    /**
     * Fields available for search.
     *
     * @var list<string>
     */
    protected array $searchableFields = ['title', 'description', 'waiting_on'];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FollowUpStatus::class,
            'priority' => Priority::class,
            'is_private' => 'boolean',
            'follow_up_date' => 'date',
            'snoozed_until' => 'date',
        ];
    }

    /**
     * Get the task that originated this follow-up.
     *
     * @return BelongsTo<Task, FollowUp>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the team member associated with this follow-up.
     *
     * @return BelongsTo<TeamMember, FollowUp>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Scope to follow-ups that are past their due date and not done.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', now()->toDateString())
            ->where('status', '!=', FollowUpStatus::Done->value);
    }

    /**
     * Scope to follow-ups due today and not done.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', now()->toDateString())
            ->where('status', '!=', FollowUpStatus::Done->value);
    }

    /**
     * Scope to follow-ups due within this week (excluding today) and not done.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDueThisWeek(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>', now()->toDateString())
            ->whereDate('follow_up_date', '<=', now()->endOfWeek()->toDateString())
            ->where('status', '!=', FollowUpStatus::Done->value);
    }

    /**
     * Scope to follow-ups due after the current week and not done.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '>', now()->endOfWeek()->toDateString())
            ->where('status', '!=', FollowUpStatus::Done->value);
    }

    /**
     * Scope to follow-ups with no due date set (prep items).
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUndated(Builder $query): Builder
    {
        return $query->whereNull('follow_up_date')
            ->where('status', '!=', FollowUpStatus::Done->value);
    }

    /**
     * Scope to order follow-ups by priority (urgent first, null last).
     *
     * Mirrors the priority-sort expression used for tasks on the dashboard so
     * date-grouped sections order same-date follow-ups urgent to low.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePriorityOrdered(Builder $query): Builder
    {
        return $query->orderByRaw("CASE
            WHEN priority = 'urgent' THEN 0
            WHEN priority = 'high' THEN 1
            WHEN priority = 'normal' THEN 2
            WHEN priority = 'low' THEN 3
            ELSE 4
        END ASC");
    }

    /**
     * Get all calendar event links for this follow-up.
     *
     * @return MorphMany<CalendarEventLink>
     */
    public function calendarEventLinks(): MorphMany
    {
        return $this->morphMany(CalendarEventLink::class, 'linkable');
    }
}
