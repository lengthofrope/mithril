<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Priority;
use App\Enums\RecurrenceInterval;
use App\Enums\TaskStatus;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasActivityFeed;
use App\Models\Traits\HasFollowUp;
use App\Models\Traits\HasResourceLinks;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Task model representing a unit of work.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Priority $priority
 * @property string|null $category
 * @property TaskStatus $status
 * @property \Illuminate\Support\Carbon|null $deadline
 * @property int|null $team_id
 * @property int|null $team_member_id
 * @property int|null $task_group_id
 * @property int|null $task_category_id
 * @property bool $is_private
 * @property int $sort_order
 * @property bool $is_recurring
 * @property RecurrenceInterval|null $recurrence_interval
 * @property int|null $recurrence_custom_days
 * @property string|null $recurrence_series_id
 * @property int|null $recurrence_parent_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Task extends Model
{
    use BelongsToUser;
    use Filterable;
    use HasActivityFeed;
    use HasFactory;
    use HasFollowUp;
    use HasResourceLinks;
    use HasSortOrder;
    use Searchable;

    /**
     * Auto-generate recurrence_series_id when is_recurring is enabled.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task): void {
            if ($task->is_recurring && $task->recurrence_series_id === null) {
                $task->recurrence_series_id = (string) Str::uuid();
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'priority',
        'category',
        'status',
        'deadline',
        'team_id',
        'team_member_id',
        'task_group_id',
        'task_category_id',
        'is_private',
        'sort_order',
        'is_recurring',
        'recurrence_interval',
        'recurrence_custom_days',
        'recurrence_series_id',
        'recurrence_parent_id',
    ];

    /**
     * Fields available for filtering.
     *
     * @var array<string, string>
     */
    protected array $filterableFields = [
        'priority' => 'exact',
        'status' => 'exact',
        'team_id' => 'exact',
        'team_member_id' => 'exact',
        'task_group_id' => 'exact',
        'task_category_id' => 'exact',
        'is_private' => 'boolean',
        'is_recurring' => 'boolean',
        'deadline' => 'date_range',
    ];

    /**
     * Fields available for search.
     *
     * @var list<string>
     */
    protected array $searchableFields = ['title', 'description'];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => TaskStatus::class,
            'deadline' => 'date',
            'is_private' => 'boolean',
            'is_recurring' => 'boolean',
            'recurrence_interval' => RecurrenceInterval::class,
        ];
    }

    /**
     * Get the team this task belongs to.
     *
     * @return BelongsTo<Team, Task>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the team member assigned to this task.
     *
     * @return BelongsTo<TeamMember, Task>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Get the task group this task belongs to.
     *
     * @return BelongsTo<TaskGroup, Task>
     */
    public function taskGroup(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class);
    }

    /**
     * Get the task category this task belongs to.
     *
     * @return BelongsTo<TaskCategory, Task>
     */
    public function taskCategory(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class);
    }

    /**
     * Get all calendar event links for this task.
     *
     * @return MorphMany<CalendarEventLink>
     */
    public function calendarEventLinks(): MorphMany
    {
        return $this->morphMany(CalendarEventLink::class, 'linkable');
    }

    /**
     * Get the previous instance in this recurrence series.
     *
     * @return BelongsTo<Task, Task>
     */
    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'recurrence_parent_id');
    }

    /**
     * Get the next instance spawned from this task.
     *
     * @return HasOne<Task>
     */
    public function recurrenceChild(): HasOne
    {
        return $this->hasOne(Task::class, 'recurrence_parent_id');
    }

    /**
     * Get all tasks in the same recurrence series.
     *
     * @return HasMany<Task>
     */
    public function seriesTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'recurrence_series_id', 'recurrence_series_id');
    }
}
