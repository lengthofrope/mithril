<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExtractionStatus;
use App\Enums\ExtractionType;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * AI-extracted item from a meeting transcription awaiting user review.
 *
 * @property int $id
 * @property int $user_id
 * @property int $meeting_id
 * @property ExtractionType $type
 * @property string $content
 * @property int|null $assignee_id
 * @property string|null $priority
 * @property \Illuminate\Support\Carbon|null $deadline
 * @property ExtractionStatus $status
 * @property string|null $created_model_type
 * @property int|null $created_model_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class MeetingExtraction extends Model
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
        'type',
        'content',
        'assignee_id',
        'priority',
        'deadline',
        'status',
        'created_model_type',
        'created_model_id',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExtractionType::class,
            'status' => ExtractionStatus::class,
            'deadline' => 'date',
        ];
    }

    /**
     * Get the meeting this extraction belongs to.
     *
     * @return BelongsTo<Meeting, MeetingExtraction>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /**
     * Get the suggested assignee for this extraction.
     *
     * @return BelongsTo<TeamMember, MeetingExtraction>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assignee_id');
    }

    /**
     * Get the created resource (Task, FollowUp, or Agreement) after acceptance.
     *
     * @return MorphTo<Model, MeetingExtraction>
     */
    public function createdModel(): MorphTo
    {
        return $this->morphTo('created_model');
    }
}
