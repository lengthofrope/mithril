<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use App\Models\Traits\Searchable;
use App\Observers\AgreementObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agreement model representing a commitment made with a team member.
 *
 * @property int $id
 * @property int $team_member_id
 * @property string $description
 * @property \Illuminate\Support\Carbon $agreed_date
 * @property \Illuminate\Support\Carbon|null $follow_up_date
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[ObservedBy(AgreementObserver::class)]
class Agreement extends Model
{
    use BelongsToUser;
    use HasFactory;
    use Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_member_id',
        'description',
        'agreed_date',
        'follow_up_date',
    ];

    /**
     * Fields available for search.
     *
     * @var list<string>
     */
    protected array $searchableFields = ['description'];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreed_date' => 'date',
            'follow_up_date' => 'date',
        ];
    }

    /**
     * Get the team member this agreement belongs to.
     *
     * @return BelongsTo<TeamMember, Agreement>
     */
    public function teamMember(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class);
    }
}
