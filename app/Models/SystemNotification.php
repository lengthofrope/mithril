<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Broadcast notification visible to all users until individually dismissed.
 *
 * @property int                             $id
 * @property string                          $title
 * @property string                          $message
 * @property NotificationVariant             $variant
 * @property string|null                     $link_url
 * @property string|null                     $link_text
 * @property bool                            $is_active
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
class SystemNotification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'message',
        'variant',
        'link_url',
        'link_text',
        'is_active',
        'expires_at',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant'    => NotificationVariant::class,
            'is_active'  => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Users who have dismissed this notification.
     *
     * @return BelongsToMany<User>
     */
    public function dismissals(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'system_notification_dismissals')
            ->withPivot('dismissed_at');
    }

    /**
     * Check whether a specific user has dismissed this notification.
     */
    public function isDismissedBy(User $user): bool
    {
        return $this->dismissals()->where('user_id', $user->id)->exists();
    }

    /**
     * Scope to active, non-expired notifications.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to exclude notifications dismissed by the given user.
     */
    public function scopeNotDismissedBy(Builder $query, User $user): void
    {
        $query->whereDoesntHave('dismissals', function (Builder $q) use ($user): void {
            $q->where('user_id', $user->id);
        });
    }
}
