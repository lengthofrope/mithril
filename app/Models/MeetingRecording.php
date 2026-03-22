<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Audio recording associated with a meeting.
 *
 * Stores metadata about the recording file; the actual audio is on disk.
 *
 * @property int $id
 * @property int $user_id
 * @property int $meeting_id
 * @property string $disk
 * @property string $path
 * @property string|null $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $duration_seconds
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class MeetingRecording extends Model
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
        'disk',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'duration_seconds',
    ];

    /**
     * Get the meeting this recording belongs to.
     *
     * @return BelongsTo<Meeting, MeetingRecording>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the full URL for streaming/downloading this recording.
     */
    public function getUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Delete the recording file from disk.
     */
    public function deleteFile(): bool
    {
        return Storage::disk($this->disk)->delete($this->path);
    }
}
