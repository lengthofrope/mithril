<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DiarizationStatus;
use App\Enums\TranscriptionStatus;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transcription result for a meeting recording.
 *
 * @property int $id
 * @property int $user_id
 * @property int $meeting_id
 * @property string|null $content
 * @property string|null $diarized_content
 * @property string|null $language
 * @property string|null $provider
 * @property TranscriptionStatus $status
 * @property DiarizationStatus|null $diarization_status
 * @property string|null $error_message
 * @property string|null $diarization_error
 * @property \Illuminate\Support\Carbon|null $diarization_started_at
 * @property int|null $diarization_duration_seconds
 * @property \Illuminate\Support\Carbon|null $processing_started_at
 * @property int|null $processing_duration_seconds
 * @property int|null $audio_duration_seconds
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class MeetingTranscription extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'meeting_id',
        'content',
        'diarized_content',
        'language',
        'provider',
        'status',
        'diarization_status',
        'error_message',
        'diarization_error',
        'diarization_started_at',
        'diarization_duration_seconds',
        'processing_started_at',
        'processing_duration_seconds',
        'audio_duration_seconds',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TranscriptionStatus::class,
            'diarization_status' => DiarizationStatus::class,
            'diarization_started_at' => 'datetime',
            'diarization_duration_seconds' => 'integer',
            'processing_started_at' => 'datetime',
            'processing_duration_seconds' => 'integer',
            'audio_duration_seconds' => 'integer',
        ];
    }

    /**
     * Get the meeting this transcription belongs to.
     *
     * @return BelongsTo<Meeting, MeetingTranscription>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
