<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Models\Traits\HasSortOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BilaPrepItem model representing a preparation item for a bila meeting.
 *
 * @property int $id
 * @property int $team_member_id
 * @property int|null $bila_id
 * @property string $content
 * @property bool $is_discussed
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class BilaPrepItem extends Model
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
        'user_id',
        'team_member_id',
        'bila_id',
        'content',
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
            'is_discussed' => 'boolean',
        ];
    }

    /**
     * Get the team member this prep item belongs to.
     *
     * @return BelongsTo<TeamMember, BilaPrepItem>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Get the bila this prep item belongs to.
     *
     * @return BelongsTo<Bila, BilaPrepItem>
     */
    public function bila(): BelongsTo
    {
        return $this->belongsTo(Bila::class);
    }
}
