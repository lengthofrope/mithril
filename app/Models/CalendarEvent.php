<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarEventStatus;
use App\Models\Traits\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
