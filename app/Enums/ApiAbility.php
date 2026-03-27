<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Granular API abilities in resource:action format.
 *
 * Each case represents a specific permission that can be assigned
 * to a Sanctum personal access token.
 */
enum ApiAbility: string
{
    case TasksRead = 'tasks:read';
    case TasksWrite = 'tasks:write';
    case TasksDelete = 'tasks:delete';

    case TeamsRead = 'teams:read';
    case TeamsWrite = 'teams:write';
    case TeamsDelete = 'teams:delete';

    case TeamMembersRead = 'team-members:read';
    case TeamMembersWrite = 'team-members:write';
    case TeamMembersDelete = 'team-members:delete';

    case NotesRead = 'notes:read';
    case NotesWrite = 'notes:write';
    case NotesDelete = 'notes:delete';

    case FollowUpsRead = 'follow-ups:read';
    case FollowUpsWrite = 'follow-ups:write';
    case FollowUpsDelete = 'follow-ups:delete';

    case MeetingsRead = 'meetings:read';
    case MeetingsWrite = 'meetings:write';
    case MeetingsDelete = 'meetings:delete';

    case AgreementsRead = 'agreements:read';
    case AgreementsWrite = 'agreements:write';
    case AgreementsDelete = 'agreements:delete';

    case ActivitiesRead = 'activities:read';
    case ActivitiesWrite = 'activities:write';
    case ActivitiesDelete = 'activities:delete';

    case SearchRead = 'search:read';

    case CountersRead = 'counters:read';

    case ExportRead = 'export:read';
    case ExportWrite = 'export:write';

    /**
     * Return all read abilities.
     *
     * @return array<int, self>
     */
    public static function readAbilities(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $ability): bool => str_ends_with($ability->value, ':read'),
        ));
    }

    /**
     * Return all write abilities.
     *
     * @return array<int, self>
     */
    public static function writeAbilities(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $ability): bool => str_ends_with($ability->value, ':write'),
        ));
    }

    /**
     * Return all delete abilities.
     *
     * @return array<int, self>
     */
    public static function deleteAbilities(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $ability): bool => str_ends_with($ability->value, ':delete'),
        ));
    }

    /**
     * Return all abilities for a given resource name.
     *
     * @param string $resource The resource name (e.g. 'tasks', 'meetings').
     * @return array<int, self>
     */
    public static function forResource(string $resource): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $ability): bool => str_starts_with($ability->value, $resource . ':'),
        ));
    }

    /**
     * Return all abilities grouped by resource name.
     *
     * @return array<string, array<int, self>>
     */
    public static function groupedByResource(): array
    {
        $grouped = [];

        foreach (self::cases() as $ability) {
            $resource = explode(':', $ability->value, 2)[0];
            $grouped[$resource][] = $ability;
        }

        return $grouped;
    }
}
