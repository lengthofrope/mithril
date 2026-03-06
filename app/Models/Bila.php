<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bila model representing a 1-on-1 meeting between team lead and a team member.
 *
 * @property int $id
 * @property int $team_member_id
 * @property \Illuminate\Support\Carbon $scheduled_date
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Bila extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'team_member_id',
        'scheduled_date',
        'notes',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
        ];
    }

    /**
     * Get the team member this bila belongs to.
     *
     * @return BelongsTo<TeamMember, Bila>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Get all prep items for this bila.
     *
     * @return HasMany<BilaPrepItem>
     */
    public function prepItems(): HasMany
    {
        return $this->hasMany(BilaPrepItem::class);
    }
}
