<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves Atlassian account IDs to display names via the Jira bulk user API,
 * with application-level caching to prevent N+1 API calls on list pages.
 */
class JiraUserService
{
    private const string CACHE_PREFIX = 'jira_user:';

    private const int CACHE_TTL_SECONDS = 3600;

    private const string FALLBACK_NAME = 'Unknown user';

    /**
     * Create a new JiraUserService instance.
     *
     * @param JiraCloudService $jiraCloudService
     */
    public function __construct(
        private readonly JiraCloudService $jiraCloudService,
    ) {}

    /**
     * Resolve an array of Atlassian account IDs to their display names.
     *
     * Checks the application cache first, then fetches any missing IDs from the
     * Jira bulk user API in a single request. Results are cached individually.
     *
     * @param User                     $user       The user whose Jira connection should be used.
     * @param array<int, string|null>  $accountIds Atlassian account IDs to resolve.
     * @return array<string, string> Map of account ID → display name.
     */
    public function resolveDisplayNames(User $user, array $accountIds): array
    {
        $uniqueIds = collect($accountIds)
            ->filter(fn (mixed $id) => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($uniqueIds)) {
            return [];
        }

        $cloudId  = $user->jira_cloud_id;
        $resolved = [];
        $uncached = [];

        foreach ($uniqueIds as $accountId) {
            $cacheKey = self::CACHE_PREFIX . $cloudId . ':' . $accountId;
            $cached   = Cache::get($cacheKey);

            if ($cached !== null) {
                $resolved[$accountId] = $cached;
            } else {
                $uncached[] = $accountId;
            }
        }

        if (!empty($uncached)) {
            $resolved = $this->fetchAndCache($user, $cloudId, $uncached, $resolved);
        }

        return $resolved;
    }

    /**
     * Fetch uncached account IDs from the Jira API and cache the results.
     *
     * @param User                  $user     The user whose Jira connection should be used.
     * @param string                $cloudId  The Jira cloud ID for cache key construction.
     * @param array<int, string>    $uncached Account IDs not found in cache.
     * @param array<string, string> $resolved Already-resolved names to merge into.
     * @return array<string, string> Merged map of account ID → display name.
     */
    private function fetchAndCache(User $user, string $cloudId, array $uncached, array $resolved): array
    {
        try {
            $users = $this->jiraCloudService->fetchUsersBulk($user, $uncached);

            foreach ($users as $userData) {
                $id   = $userData['accountId'];
                $name = $userData['displayName'] ?? self::FALLBACK_NAME;

                $resolved[$id] = $name;

                Cache::put(
                    self::CACHE_PREFIX . $cloudId . ':' . $id,
                    $name,
                    self::CACHE_TTL_SECONDS,
                );
            }
        } catch (\Throwable) {
            // API failure — fall through to fallback
        }

        foreach ($uncached as $id) {
            if (!isset($resolved[$id])) {
                $resolved[$id] = self::FALLBACK_NAME;
            }
        }

        return $resolved;
    }
}
