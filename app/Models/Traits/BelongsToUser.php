<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes all queries to the authenticated user and auto-sets user_id on creation.
 *
 * The user_id field is intentionally excluded from $fillable to prevent mass
 * assignment via HTTP input. This trait handles user_id assignment in two ways:
 *
 * - When an authenticated user exists, the creating event always forces user_id
 *   to auth()->id(), overriding any previously set value.
 * - When no authenticated user exists (e.g. tests, console commands), a user_id
 *   passed directly via fill() is preserved by capturing it before Eloquent's
 *   mass-assignment guard strips it.
 */
trait BelongsToUser
{
    /**
     * Boot the trait: register global scope and creating event.
     *
     * @return void
     */
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', function (Builder $builder): void {
            if (auth()->check()) {
                $builder->where($builder->getModel()->getTable() . '.user_id', auth()->id());
            }
        });

        static::creating(function (Model $model): void {
            if (auth()->check()) {
                $model->user_id = auth()->id();

                return;
            }
        });
    }

    /**
     * Override fill to capture user_id before the mass-assignment guard strips it.
     *
     * When user_id is not fillable, Model::create(['user_id' => $id, ...]) would
     * silently discard the value. This override preserves it via forceFill so that
     * console commands and test helpers can still supply a user_id explicitly.
     *
     * When an authenticated user is present, the creating event in bootBelongsToUser
     * always overrides user_id with auth()->id(), so any value set here is replaced.
     *
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        if (array_key_exists('user_id', $attributes) && !in_array('user_id', $this->getFillable(), true)) {
            $this->setAttribute('user_id', $attributes['user_id']);
            unset($attributes['user_id']);
        }

        return parent::fill($attributes);
    }

    /**
     * Get the user that owns this record.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
