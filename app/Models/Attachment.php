<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Attachment model representing a file uploaded to an activity.
 *
 * @property int $id
 * @property int $user_id
 * @property int $activity_id
 * @property string $filename
 * @property string $path
 * @property string $disk
 * @property string $mime_type
 * @property int $size
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Attachment extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'activity_id',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
    ];

    /**
     * Register model event listeners.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::deleting(function (self $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }

    /**
     * Get the activity this attachment belongs to.
     *
     * @return BelongsTo<Activity, self>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Determine whether this attachment is an image.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Determine whether this attachment is a PDF.
     *
     * @return bool
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Format the file size as a human-readable string.
     *
     * @return string
     */
    public function humanSize(): string
    {
        $bytes = $this->size;

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576) . ' MB';
        }

        return round($bytes / 1073741824) . ' GB';
    }

    /**
     * Generate a signed inline preview URL valid for 30 minutes.
     *
     * @return string
     */
    public function previewUrl(): string
    {
        return URL::signedRoute(
            'attachments.preview',
            ['attachment' => $this->id],
            now()->addMinutes(30),
        );
    }

    /**
     * Generate a signed download URL valid for 30 minutes.
     *
     * @return string
     */
    public function downloadUrl(): string
    {
        return URL::signedRoute(
            'attachments.download',
            ['attachment' => $this->id],
            now()->addMinutes(30),
        );
    }
}
