<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WeeklyReflection model for storing weekly summaries and personal reflections.
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $week_start
 * @property \Illuminate\Support\Carbon $week_end
 * @property string|null $summary
 * @property string|null $reflection
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WeeklyReflection extends Model
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
        'week_start',
        'week_end',
        'summary',
        'reflection',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
        ];
    }
}
