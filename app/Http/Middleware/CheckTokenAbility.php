<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces API ability checks on Sanctum token-authenticated requests.
 *
 * Resolves the required ability from the route name convention and verifies
 * the token has that ability. Session-authenticated requests bypass this
 * middleware entirely.
 */
class CheckTokenAbility
{
    /**
     * Route action suffixes mapped to ability action types.
     */
    private const array ACTION_MAP = [
        'index' => 'read',
        'show' => 'read',
        'stream' => 'read',
        'store' => 'write',
        'update' => 'write',
        'sync' => 'write',
        'manual' => 'write',
        'retry' => 'write',
        'retranscribe' => 'write',
        'start-local' => 'write',
        'client-result' => 'write',
        'accept' => 'write',
        'reject' => 'write',
        'bulk' => 'write',
        're-extract' => 'write',
        'dismiss' => 'write',
        'destroy' => 'delete',
    ];

    /**
     * Route resource prefixes mapped to their ability resource name.
     *
     * Sub-resources (e.g. meetings.recordings) map to the parent resource.
     */
    private const array RESOURCE_MAP = [
        'tasks' => 'tasks',
        'teams' => 'teams',
        'team-members' => 'team-members',
        'notes' => 'notes',
        'follow-ups' => 'follow-ups',
        'meetings' => 'meetings',
        'agreements' => 'agreements',
        'activities' => 'activities',
        'attachments' => 'attachments',
        'counters' => 'counters',
        'search' => 'search',
        'export' => 'export',
        'import' => 'export',
        'system-notifications' => 'system-notifications',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->isTokenAuthenticated($request)) {
            return $next($request);
        }

        $ability = $this->resolveAbility($request);

        if ($ability === null) {
            return $next($request);
        }

        if ($request->user()->tokenCan($ability)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'data' => null,
            'message' => "This token does not have the required ability: {$ability}",
        ], 403);
    }

    /**
     * Determine whether the request is authenticated via a Sanctum personal access token.
     *
     * @param Request $request
     * @return bool
     */
    private function isTokenAuthenticated(Request $request): bool
    {
        return $request->user()?->currentAccessToken() instanceof PersonalAccessToken;
    }

    /**
     * Resolve the required ability string from the current route name.
     *
     * @param Request $request
     * @return string|null The ability string (e.g. 'tasks:read') or null if unmapped.
     */
    private function resolveAbility(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null || !str_starts_with($routeName, 'api.')) {
            return null;
        }

        $segments = explode('.', substr($routeName, 4));

        $resource = $this->resolveResource($segments);
        $action = $this->resolveAction($segments);

        if ($resource === null || $action === null) {
            return null;
        }

        return "{$resource}:{$action}";
    }

    /**
     * Resolve the resource name from route name segments.
     *
     * @param array<int, string> $segments
     * @return string|null
     */
    private function resolveResource(array $segments): ?string
    {
        if (empty($segments)) {
            return null;
        }

        $primary = $segments[0];

        return self::RESOURCE_MAP[$primary] ?? null;
    }

    /**
     * Resolve the action type from route name segments.
     *
     * @param array<int, string> $segments
     * @return string|null
     */
    private function resolveAction(array $segments): ?string
    {
        $lastSegment = end($segments);

        return self::ACTION_MAP[$lastSegment] ?? null;
    }
}
