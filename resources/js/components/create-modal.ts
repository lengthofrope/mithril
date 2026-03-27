import type { MemberOption } from './team-member-filter';

/**
 * Represents a team option for select dropdowns.
 */
interface TeamOption {
    value: number;
    label: string;
}

/**
 * Configuration for the createModal Alpine component.
 */
interface CreateModalConfig {
    memberOptions: MemberOption[];
    teamOptions?: TeamOption[];
}

/**
 * Alpine.js component that provides shared state and logic for create modals.
 * Handles modal toggle, team-based member filtering, and optional team meeting
 * features (multi-team selection, type switching) when teamOptions is provided.
 *
 * Usage in Blade:
 *   x-data="createModal({ memberOptions: @js($memberOptions) })"
 *   x-data="createModal({ memberOptions: @js($memberOptions), teamOptions: @js($teamOptions) })"
 */
function createModal(config: CreateModalConfig): Record<string, unknown> {
    const allTeams: TeamOption[] = config.teamOptions ?? [];

    return {
        addOpen: false,
        selectedTeamId: '',
        allMembers: config.memberOptions,
        selectedType: 'one_on_one',
        selectedTeamIds: [] as number[],
        allTeams,

        /**
         * Returns member options filtered by the currently selected team.
         * When no team is selected, all members are returned.
         */
        get filteredMembers(): MemberOption[] {
            const self = this as { selectedTeamId: string; allMembers: MemberOption[] };
            return self.selectedTeamId
                ? self.allMembers.filter((m: MemberOption) => String(m.team_id) === String(self.selectedTeamId))
                : self.allMembers;
        },

        /**
         * Returns true when the meeting type is one-on-one.
         */
        get isOneOnOne(): boolean {
            return (this as { selectedType: string }).selectedType === 'one_on_one';
        },

        /**
         * Adds a team to the selected teams list for team meetings.
         */
        addTeam(this: { selectedTeamIds: number[] }, teamId: string | number): void {
            const numericId = Number(teamId);
            if (teamId && !this.selectedTeamIds.includes(numericId)) {
                this.selectedTeamIds.push(numericId);
            }
        },

        /**
         * Removes a team from the selected teams list.
         */
        removeTeam(this: { selectedTeamIds: number[] }, teamId: number): void {
            this.selectedTeamIds = this.selectedTeamIds.filter((id: number) => id !== teamId);
        },

        /**
         * Returns the display label for a team by its ID.
         */
        teamLabel(teamId: number): string {
            const team = allTeams.find((t: TeamOption) => t.value === teamId);
            return team ? team.label : '';
        },

        /**
         * Returns teams not yet selected, for the team picker dropdown.
         */
        get availableTeams(): TeamOption[] {
            const self = this as { selectedTeamIds: number[] };
            return allTeams.filter((t: TeamOption) => !self.selectedTeamIds.includes(t.value));
        },
    };
}

export { createModal };
export type { CreateModalConfig, TeamOption };
