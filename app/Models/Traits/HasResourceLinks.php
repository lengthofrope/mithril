<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\CalendarEventLink;
use App\Models\EmailLink;
use Illuminate\Database\Eloquent\Model;

/**
 * Cleans up polymorphic link records when a resource is deleted.
 *
 * Handles CalendarEventLink and EmailLink cleanup on the deleting event,
 * preventing orphaned link records that would render as broken references
 * in the frontend.
 */
trait HasResourceLinks
{
    /**
     * Boot the trait: register deleting event to clean up link records.
     *
     * @return void
     */
    public static function bootHasResourceLinks(): void
    {
        static::deleting(function (Model $model): void {
            CalendarEventLink::where('linkable_type', $model->getMorphClass())
                ->where('linkable_id', $model->getKey())
                ->delete();

            EmailLink::where('linkable_type', $model->getMorphClass())
                ->where('linkable_id', $model->getKey())
                ->delete();
        });
    }
}
