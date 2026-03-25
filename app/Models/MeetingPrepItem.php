<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PrepItemType;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\HasSortOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preparation item for a meeting with type, duration estimate, and attendee assignment.
 *
 * @property int $id
 * @property int $user_id
 * @property int $meeting_id
 * @property int|null $team_member_id
 * @property string $content
 * @property PrepItemType $type
 * @property int|null $duration_minutes
 * @property bool $is_discussed
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class MeetingPrepItem extends Model
{
    use BelongsToUser;
    use HasFactory;
    use HasSortOrder;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'team_member_id',
        'content',
        'type',
        'duration_minutes',
        'is_discussed',
        'sort_order',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PrepItemType::class,
            'is_discussed' => 'boolean',
        ];
    }

    /**
     * Get the meeting this prep item belongs to.
     *
     * @return BelongsTo<Meeting, MeetingPrepItem>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the team member this prep item is assigned to.
     *
     * @return BelongsTo<TeamMember, MeetingPrepItem>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
