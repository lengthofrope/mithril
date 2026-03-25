<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot model linking a meeting to a team member attendee.
 *
 * @property int $id
 * @property int $meeting_id
 * @property int $team_member_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class MeetingAttendee extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'team_member_id',
    ];

    /**
     * Get the meeting this attendee record belongs to.
     *
     * @return BelongsTo<Meeting, MeetingAttendee>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the team member this attendee record belongs to.
     *
     * @return BelongsTo<TeamMember, MeetingAttendee>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
