<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailImportance;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cached email metadata synced from Microsoft Graph.
 *
 * @property int                          $id
 * @property int                          $user_id
 * @property string                       $microsoft_message_id
 * @property string                       $subject
 * @property string|null                  $sender_name
 * @property string|null                  $sender_email
 * @property \Illuminate\Support\Carbon   $received_at
 * @property string|null                  $body_preview
 * @property bool                         $is_read
 * @property bool                         $is_flagged
 * @property \Illuminate\Support\Carbon|null $flag_due_date
 * @property array<int, string>|null      $categories
 * @property EmailImportance              $importance
 * @property bool                         $has_attachments
 * @property string|null                  $web_link
 * @property array<int, string>           $sources
 * @property bool                         $is_dismissed
 * @property \Illuminate\Support\Carbon   $synced_at
 * @property \Illuminate\Support\Carbon   $created_at
 * @property \Illuminate\Support\Carbon   $updated_at
 */
class Email extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'microsoft_message_id',
        'subject',
        'sender_name',
        'sender_email',
        'received_at',
        'body_preview',
        'is_read',
        'is_flagged',
        'flag_due_date',
        'categories',
        'importance',
        'has_attachments',
        'web_link',
        'sources',
        'is_dismissed',
        'synced_at',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at'     => 'datetime',
            'is_read'         => 'boolean',
            'is_flagged'      => 'boolean',
            'flag_due_date'   => 'date',
            'categories'      => 'array',
            'importance'      => EmailImportance::class,
            'has_attachments' => 'boolean',
            'sources'         => 'array',
            'is_dismissed'    => 'boolean',
            'synced_at'       => 'datetime',
        ];
    }

    /**
     * Get all resource links for this email.
     *
     * @return HasMany<EmailLink>
     */
    public function emailLinks(): HasMany
    {
        return $this->hasMany(EmailLink::class);
    }
}
