<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Enums\ActivityType;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Provides activity feed functionality for Eloquent models.
 *
 * Models using this trait gain a polymorphic activity feed with helpers
 * for creating comments, links, and system event entries.
 */
trait HasActivityFeed
{
    /**
     * Get all activities for this model.
     *
     * @return MorphMany<Activity>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'activityable');
    }

    /**
     * Add a comment activity to the feed.
     *
     * @param string $body
     * @return Activity
     */
    public function addComment(string $body): Activity
    {
        return $this->activities()->create([
            'type' => ActivityType::Comment,
            'body' => $body,
        ]);
    }

    /**
     * Add a link activity to the feed.
     *
     * @param string $url
     * @param string|null $title
     * @param string|null $body
     * @return Activity
     */
    public function addLink(string $url, ?string $title = null, ?string $body = null): Activity
    {
        return $this->activities()->create([
            'type' => ActivityType::Link,
            'body' => $body,
            'metadata' => [
                'url' => $url,
                'title' => $title,
            ],
        ]);
    }

    /**
     * Log a system event activity to the feed.
     *
     * @param string $body
     * @param string $action
     * @param array<string, mixed> $changes
     * @return Activity
     */
    public function logSystemEvent(string $body, string $action, array $changes): Activity
    {
        return $this->activities()->create([
            'type' => ActivityType::System,
            'body' => $body,
            'metadata' => [
                'action' => $action,
                'changes' => $changes,
            ],
        ]);
    }

    /**
     * Get the activity feed in chronological order.
     *
     * @param int $limit
     * @return Collection<int, Activity>
     */
    public function getActivityFeed(int $limit = 50): Collection
    {
        return $this->activities()
            ->chronological()
            ->limit($limit)
            ->get();
    }
}
