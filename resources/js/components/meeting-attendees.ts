import { apiClient } from '../utils/api-client';

/**
 * A single attendee in a meeting.
 */
interface Attendee {
    id: number;
    name: string;
    role: string;
}

/**
 * A team member option for the attendee picker.
 */
interface MemberOption {
    value: number;
    label: string;
    team_id: number;
    team_name?: string;
}

/**
 * A team option for the attendee picker.
 */
interface TeamOption {
    value: number;
    label: string;
}

/**
 * Configuration for the meetingAttendees Alpine component.
 */
interface MeetingAttendeesConfig {
    attendees: Attendee[];
    memberOptions: MemberOption[];
    teamOptions: TeamOption[];
    meetingType: string;
    syncEndpoint: string;
}

/**
 * Alpine.js component for managing meeting attendees.
 * Supports adding individual members and entire teams, removing members, and syncing to the server.
 */
function meetingAttendees(config: MeetingAttendeesConfig): Record<string, unknown> {
    return {
        attendees: config.attendees,
        allMembers: config.memberOptions,
        allTeams: config.teamOptions,
        meetingType: config.meetingType,
        showPicker: false as boolean,
        saving: false as boolean,

        /**
         * Whether the meeting is a one-on-one type.
         */
        get isOneOnOne(): boolean {
            return (this as unknown as { meetingType: string }).meetingType === 'one_on_one';
        },

        /**
         * Whether more attendees can be added based on meeting type constraints.
         */
        get canAddMore(): boolean {
            const self = this as unknown as { isOneOnOne: boolean; attendees: Attendee[] };
            if (self.isOneOnOne) return self.attendees.length < 1;
            return true;
        },

        /**
         * Members not yet added as attendees.
         */
        get availableMembers(): MemberOption[] {
            const self = this as unknown as { attendees: Attendee[]; allMembers: MemberOption[] };
            const currentIds = self.attendees.map((a: Attendee) => a.id);
            return self.allMembers.filter((m: MemberOption) => !currentIds.includes(m.value));
        },

        /**
         * Teams that still have at least one unadded member.
         */
        get availableTeams(): TeamOption[] {
            const self = this as unknown as { attendees: Attendee[]; allMembers: MemberOption[]; allTeams: TeamOption[] };
            const currentIds = self.attendees.map((a: Attendee) => a.id);
            return self.allTeams.filter((t: TeamOption) => {
                const teamMemberIds = self.allMembers
                    .filter((m: MemberOption) => m.team_id === t.value)
                    .map((m: MemberOption) => m.value);
                return teamMemberIds.some((id: number) => !currentIds.includes(id));
            });
        },

        /**
         * Syncs the current attendee list to the server.
         */
        async syncAttendees(this: Record<string, unknown>): Promise<void> {
            this.saving = true;
            try {
                const attendees = this.attendees as Attendee[];
                await apiClient.patch(config.syncEndpoint, {
                    attendee_ids: attendees.map((a: Attendee) => a.id),
                });
            } finally {
                this.saving = false;
            }
        },

        /**
         * Adds a single member by ID and syncs.
         */
        addMember(this: Record<string, unknown>, memberId: number | string): void {
            const allMembers = this.allMembers as MemberOption[];
            const member = allMembers.find((m: MemberOption) => m.value === Number(memberId));
            if (member) {
                const attendees = this.attendees as Attendee[];
                attendees.push({ id: member.value, name: member.label, role: '' });
                void (this.syncAttendees as () => Promise<void>).call(this);
            }
            this.showPicker = false;
        },

        /**
         * Adds all unadded members of a team and syncs.
         */
        addTeam(this: Record<string, unknown>, teamId: number | string): void {
            const allMembers = this.allMembers as MemberOption[];
            const attendees = this.attendees as Attendee[];
            const teamMembers = allMembers.filter((m: MemberOption) => m.team_id === Number(teamId));
            const currentIds = attendees.map((a: Attendee) => a.id);
            let added = false;
            teamMembers.forEach((m: MemberOption) => {
                if (!currentIds.includes(m.value)) {
                    attendees.push({ id: m.value, name: m.label, role: '' });
                    added = true;
                }
            });
            if (added) {
                void (this.syncAttendees as () => Promise<void>).call(this);
            }
            this.showPicker = false;
        },

        /**
         * Removes an attendee by ID and syncs.
         */
        removeMember(this: Record<string, unknown>, id: number): void {
            this.attendees = (this.attendees as Attendee[]).filter((a: Attendee) => a.id !== id);
            void (this.syncAttendees as () => Promise<void>).call(this);
        },
    };
}

export { meetingAttendees };
export type { MeetingAttendeesConfig, Attendee, MemberOption, TeamOption };
