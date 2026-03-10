<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * CalendarEventLink model representing a polymorphic link between a calendar event and a resource.
 *
 * A calendar event can be linked to multiple resources of different types (Bila, Task, FollowUp, Note).
 * This model intentionally does not use BelongsToUser — it is scoped through the CalendarEvent.
 *
 * @property int    $id
 * @property int    $calendar_event_id
 * @property string $linkable_type
 * @property int    $linkable_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CalendarEventLink extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'calendar_event_id',
        'linkable_type',
        'linkable_id',
    ];

    /**
     * Get the calendar event this link belongs to.
     *
     * @return BelongsTo<CalendarEvent, CalendarEventLink>
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    /**
     * Get the linked resource (Bila, Task, FollowUp, or Note).
     *
     * @return MorphTo<Model, CalendarEventLink>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
