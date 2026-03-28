/**
 * Represents a team member option for select dropdowns.
 */
interface MemberOption {
    value: string;
    label: string;
    team_id: string | number;
}

/**
 * Configuration for the teamMemberFilter Alpine component.
 */
interface TeamMemberFilterConfig {
    memberOptions: MemberOption[];
    initialTeamId?: string;
}

/**
 * Alpine.js component that provides team-based member filtering.
 * Used on show pages (tasks, notes, follow-ups) where a team select
 * filters the available member options in a sibling select.
 *
 * Usage in Blade:
 *   x-data="teamMemberFilter({ memberOptions: @js($memberOptions), initialTeamId: @js((string) ($task->team_id ?? '')) })"
 */
function teamMemberFilter(config: TeamMemberFilterConfig): Record<string, unknown> {
    return {
        allMembers: config.memberOptions,
        selectedTeamId: config.initialTeamId ?? '',

        /**
         * Returns member options filtered by the currently selected team.
         * When no team is selected, all members are returned.
         */
        get filteredMemberOptions(): MemberOption[] {
            const self = this as { selectedTeamId: string; allMembers: MemberOption[] };
            return self.selectedTeamId
                ? self.allMembers.filter((m: MemberOption) => String(m.team_id) === String(self.selectedTeamId))
                : self.allMembers;
        },
    };
}

export { teamMemberFilter };
export type { TeamMemberFilterConfig, MemberOption };
