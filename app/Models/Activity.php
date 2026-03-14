<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityType;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Activity model representing an entry in an entity's activity feed.
 *
 * @property int $id
 * @property int $user_id
 * @property string $activityable_type
 * @property int $activityable_id
 * @property ActivityType $type
 * @property string|null $body
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Activity extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * Activity records may be created with explicit timestamps when importing
     * historical data or seeding, so created_at is intentionally fillable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'activityable_type',
        'activityable_id',
        'type',
        'body',
        'metadata',
        'created_at',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * Get the parent activityable model.
     *
     * @return MorphTo<Model, self>
     */
    public function activityable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get all attachments for this activity.
     *
     * @return HasMany<Attachment>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Scope to activities of a specific type.
     *
     * @param Builder $query
     * @param ActivityType $type
     * @return Builder
     */
    public function scopeOfType(Builder $query, ActivityType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /**
     * Scope to order activities chronologically ascending.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Scope to order activities with the latest first.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Determine whether this activity is a comment.
     *
     * @return bool
     */
    public function isComment(): bool
    {
        return $this->type === ActivityType::Comment;
    }

    /**
     * Determine whether this activity is a link.
     *
     * @return bool
     */
    public function isLink(): bool
    {
        return $this->type === ActivityType::Link;
    }

    /**
     * Determine whether this activity is an attachment.
     *
     * @return bool
     */
    public function isAttachment(): bool
    {
        return $this->type === ActivityType::Attachment;
    }

    /**
     * Determine whether this activity is a system event.
     *
     * @return bool
     */
    public function isSystem(): bool
    {
        return $this->type === ActivityType::System;
    }

    /**
     * Get the URL from the metadata, if present.
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->metadata['url'] ?? null;
    }

    /**
     * Get the link title from the metadata, if present.
     *
     * @return string|null
     */
    public function getLinkTitle(): ?string
    {
        return $this->metadata['title'] ?? null;
    }
}
