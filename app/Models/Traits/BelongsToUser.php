<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes all queries to the authenticated user and auto-sets user_id on creation.
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
            if (auth()->check() && empty($model->user_id)) {
                $model->user_id = auth()->id();
            }
        });
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
