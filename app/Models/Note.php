<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Models\Traits\Filterable;
use App\Models\Traits\HasActivityFeed;
use App\Models\Traits\HasResourceLinks;
use App\Models\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Note model for storing markdown-based notes.
 *
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int|null $team_id
 * @property int|null $team_member_id
 * @property bool $is_pinned
 * @property \Illuminate\Support\Carbon|null $date
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Note extends Model
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
        'title',
        'content',
        'team_id',
        'team_member_id',
        'is_pinned',
        'date',
    ];

    /**
     * Fields available for filtering.
     *
     * @var array<string, string>
     */
    protected array $filterableFields = [
        'team_id' => 'exact',
        'team_member_id' => 'exact',
        'is_pinned' => 'boolean',
    ];

    /**
     * Fields available for search.
     *
     * @var list<string>
     */
    protected array $searchableFields = ['title', 'content'];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'date' => 'date',
        ];
    }

    /**
     * Get the team this note belongs to.
     *
     * @return BelongsTo<Team, Note>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the team member this note belongs to.
     *
     * @return BelongsTo<TeamMember, Note>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }

    /**
     * Get all tags for this note.
     *
     * @return HasMany<NoteTag>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(NoteTag::class);
    }

    /**
     * Get all calendar event links for this note.
     *
     * @return MorphMany<CalendarEventLink>
     */
    public function calendarEventLinks(): MorphMany
    {
        return $this->morphMany(CalendarEventLink::class, 'linkable');
    }
}
