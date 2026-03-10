<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MemberStatus;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasFollowUp;
use App\Models\Traits\HasSortOrder;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TeamMember model representing an individual within a team.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $role
 * @property string|null $email
 * @property string|null $notes
 * @property MemberStatus $status
 * @property string|null $avatar_path
 * @property int $bila_interval_days
 * @property \Illuminate\Support\Carbon|null $next_bila_date
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TeamMember extends Model
{
    use BelongsToUser;
    use Filterable;
    use HasFactory;
    use HasFollowUp;
    use HasSortOrder;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'name',
        'role',
        'email',
        'notes',
        'status',
        'avatar_path',
        'bila_interval_days',
        'next_bila_date',
        'sort_order',
    ];

    /**
     * Fields available for filtering.
     *
     * @var array<string, string>
     */
    protected array $filterableFields = [
        'team_id' => 'exact',
        'status' => 'exact',
    ];

    /**
     * Fields available for search.
     *
     * @var list<string>
     */
    protected array $searchableFields = ['name', 'role', 'email'];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'next_bila_date' => 'date',
        ];
    }

    /**
     * Get the team this member belongs to.
     *
     * @return BelongsTo<Team, TeamMember>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get all tasks assigned to this member.
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }


    /**
     * Get all bilas for this member.
     *
     * @return HasMany<Bila>
     */
    public function bilas(): HasMany
    {
        return $this->hasMany(Bila::class);
    }

    /**
     * Get all agreements for this member.
     *
     * @return HasMany<Agreement>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    /**
     * Get all bila prep items for this member.
     *
     * @return HasMany<BilaPrepItem>
     */
    public function bilaPrepItems(): HasMany
    {
        return $this->hasMany(BilaPrepItem::class);
    }

    /**
     * Get all notes for this member.
     *
     * @return HasMany<Note>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
