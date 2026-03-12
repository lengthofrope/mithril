<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * EmailLink model representing a polymorphic link between an email and a resource.
 *
 * Uses SET NULL FK on email_id so links survive email pruning. The denormalized
 * email_subject preserves provenance for display when the email record is gone.
 *
 * @property int         $id
 * @property int|null    $email_id
 * @property string      $email_subject
 * @property string      $linkable_type
 * @property int         $linkable_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class EmailLink extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email_id',
        'email_subject',
        'linkable_type',
        'linkable_id',
    ];

    /**
     * Get the email this link belongs to.
     *
     * @return BelongsTo<Email, EmailLink>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Get the linked resource (Task, FollowUp, Note, or Bila).
     *
     * @return MorphTo<Model, EmailLink>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
