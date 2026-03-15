<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\CalendarEventLink;
use App\Models\EmailLink;
use Illuminate\Database\Eloquent\Model;

/**
 * Transfers polymorphic metadata (activities, calendar links, email links)
 * from one model to another during entity conversion.
 */
class MetadataTransferService
{
    /**
     * Transfer all polymorphic metadata from the source model to the target model.
     *
     * @param Model $source
     * @param Model $target
     * @return void
     */
    public function transfer(Model $source, Model $target): void
    {
        $sourceType = $source->getMorphClass();
        $sourceId = $source->getKey();
        $targetType = $target->getMorphClass();
        $targetId = $target->getKey();

        Activity::withoutGlobalScopes()
            ->where('activityable_type', $sourceType)
            ->where('activityable_id', $sourceId)
            ->update([
                'activityable_type' => $targetType,
                'activityable_id' => $targetId,
            ]);

        CalendarEventLink::where('linkable_type', $sourceType)
            ->where('linkable_id', $sourceId)
            ->update([
                'linkable_type' => $targetType,
                'linkable_id' => $targetId,
            ]);

        EmailLink::where('linkable_type', $sourceType)
            ->where('linkable_id', $sourceId)
            ->update([
                'linkable_type' => $targetType,
                'linkable_id' => $targetId,
            ]);
    }
}
