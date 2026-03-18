<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasActivityFeed;
use App\Models\Traits\HasResourceLinks;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Meeting model representing any type of meeting (team, 1-on-1, or other).
 *
 * Replaces the legacy Bila model with expanded capabilities for multi-attendee
 * meetings, recording, transcription, and AI-powered extraction.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $team_id
 * @property string $title
 * @property MeetingType $type
 * @property MeetingStatus $status
 * @property \Illuminate\Support\Carbon $scheduled_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property string|null $notes
 * @property string|null $summary
 * @property string $transcription_language
 * @property string|null $output_language
 * @property bool $is_done
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Meeting extends Model
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
        'team_id',
        'title',
        'type',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'notes',
        'summary',
        'transcription_language',
        'output_language',
        'is_done',
    ];

    /**
     * Fields available for filtering.
     *
     * @var array<string, string>
     */
    protected array $filterableFields = [
        'team_id' => 'exact',
        'type' => 'exact',
        'status' => 'exact',
        'is_done' => 'boolean',
    ];

    /**
     * Fields available for search.
     *
     * @var list<string>
     */
    protected array $searchableFields = ['title', 'notes'];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MeetingType::class,
            'status' => MeetingStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_done' => 'boolean',
        ];
    }

    /**
     * Get the team this meeting optionally belongs to.
     *
     * @return BelongsTo<Team, Meeting>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the team members attending this meeting.
     *
     * @return BelongsToMany<TeamMember>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(TeamMember::class, 'meeting_attendees')
            ->withTimestamps();
    }

    /**
     * Get all prep items for this meeting.
     *
     * @return HasMany<MeetingPrepItem>
     */
    public function prepItems(): HasMany
    {
        return $this->hasMany(MeetingPrepItem::class);
    }

    /**
     * Get all recordings for this meeting.
     *
     * @return HasMany<MeetingRecording>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(MeetingRecording::class);
    }

    /**
     * Get all calendar event links for this meeting.
     *
     * @return MorphMany<CalendarEventLink>
     */
    public function calendarEventLinks(): MorphMany
    {
        return $this->morphMany(CalendarEventLink::class, 'linkable');
    }
}
