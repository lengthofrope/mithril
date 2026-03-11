<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarEventStatus;
use App\Models\Traits\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * CalendarEvent model representing a Microsoft Graph calendar event synced for a user.
 *
 * @property int                  $id
 * @property int                  $user_id
 * @property string               $microsoft_event_id
 * @property string               $subject
 * @property \Illuminate\Support\Carbon $start_at
 * @property \Illuminate\Support\Carbon $end_at
 * @property bool                 $is_all_day
 * @property string|null          $location
 * @property CalendarEventStatus  $status
 * @property bool                 $is_online_meeting
 * @property string|null          $online_meeting_url
 * @property string|null          $organizer_name
 * @property string|null          $organizer_email
 * @property array<int, array{email: string, name: string|null}>|null $attendees
 * @property \Illuminate\Support\Carbon $synced_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CalendarEvent extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'microsoft_event_id',
        'subject',
        'start_at',
        'end_at',
        'is_all_day',
        'location',
        'status',
        'is_online_meeting',
        'online_meeting_url',
        'organizer_name',
        'organizer_email',
        'attendees',
        'synced_at',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at'          => 'datetime',
            'end_at'            => 'datetime',
            'is_all_day'        => 'boolean',
            'is_online_meeting' => 'boolean',
            'status'            => CalendarEventStatus::class,
            'attendees'         => 'array',
            'synced_at'         => 'datetime',
        ];
    }

    /**
     * Scope to filter events whose start time is on or after a given date.
     *
     * @param Builder<CalendarEvent> $query
     * @param CarbonInterface        $date
     * @return Builder<CalendarEvent>
     */
    public function scopeStartingFrom(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where('start_at', '>=', $date);
    }

    /**
     * Scope to filter events whose start time is on or before a given date.
     *
     * @param Builder<CalendarEvent> $query
     * @param CarbonInterface        $date
     * @return Builder<CalendarEvent>
     */
    public function scopeUntil(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where('start_at', '<=', $date);
    }

    /**
     * Scope to filter events that have not yet ended at a given point in time.
     *
     * @param Builder<CalendarEvent> $query
     * @param CarbonInterface        $date
     * @return Builder<CalendarEvent>
     */
    public function scopeNotEndedAt(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where('end_at', '>=', $date);
    }

    /**
     * Get all resource links for this calendar event.
     *
     * @return HasMany<CalendarEventLink>
     */
    public function links(): HasMany
    {
        return $this->hasMany(CalendarEventLink::class);
    }

    /**
     * Get all linked Bilas through the polymorphic pivot.
     *
     * @return MorphToMany<Bila>
     */
    public function linkedBilas(): MorphToMany
    {
        return $this->morphedByMany(Bila::class, 'linkable', 'calendar_event_links');
    }

    /**
     * Get all linked Tasks through the polymorphic pivot.
     *
     * @return MorphToMany<Task>
     */
    public function linkedTasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'linkable', 'calendar_event_links');
    }

    /**
     * Get all linked FollowUps through the polymorphic pivot.
     *
     * @return MorphToMany<FollowUp>
     */
    public function linkedFollowUps(): MorphToMany
    {
        return $this->morphedByMany(FollowUp::class, 'linkable', 'calendar_event_links');
    }

    /**
     * Get all linked Notes through the polymorphic pivot.
     *
     * @return MorphToMany<Note>
     */
    public function linkedNotes(): MorphToMany
    {
        return $this->morphedByMany(Note::class, 'linkable', 'calendar_event_links');
    }
}
