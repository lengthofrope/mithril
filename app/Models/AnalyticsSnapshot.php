<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Daily metric snapshot for a single user.
 *
 * Stores one aggregated integer value per metric per date, enabling historical
 * trend charts without querying live data. Rows are written via upsert so the
 * unique index on (user_id, metric, snapshot_date) guarantees idempotent runs.
 *
 * @property int                         $id
 * @property int                         $user_id
 * @property \Illuminate\Support\Carbon  $snapshot_date
 * @property string                      $metric
 * @property int                         $value
 * @property \Illuminate\Support\Carbon  $created_at
 * @property \Illuminate\Support\Carbon  $updated_at
 */
class AnalyticsSnapshot extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'snapshot_date',
        'metric',
        'value',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'value'         => 'integer',
        ];
    }
}
