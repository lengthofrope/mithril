<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Historical log of transcription and diarization processing times.
 *
 * Used to estimate remaining time for in-progress jobs based on
 * the average ratio of processing time to audio duration.
 *
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property int $audio_duration_seconds
 * @property int $processing_duration_seconds
 * @property \Illuminate\Support\Carbon $created_at
 */
class ProcessingTimingLog extends Model
{
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'audio_duration_seconds',
        'processing_duration_seconds',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audio_duration_seconds' => 'integer',
            'processing_duration_seconds' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
