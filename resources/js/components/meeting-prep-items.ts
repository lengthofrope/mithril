import { apiClient } from '../utils/api-client';

/**
 * A single prep item in a meeting.
 */
interface PrepItem {
    id: number;
    content: string;
    type: string;
    duration_minutes: number | null;
    is_discussed: boolean;
    team_member_name: string | null;
}

/**
 * A member option for the prep item assignee picker.
 */
interface PrepMemberOption {
    value: number;
    label: string;
}

/**
 * Configuration for the meetingPrepItems Alpine component.
 */
interface MeetingPrepItemsConfig {
    items: PrepItem[];
    meetingId: number;
    storeEndpoint: string;
    memberOptions: PrepMemberOption[];
}

/**
 * Visual configuration for prep item types.
 */
interface TypeConfig {
    icon: string;
    class: string;
}

/**
 * Alpine.js component for managing meeting prep items (agenda items, questions, actions).
 * Supports adding, toggling discussed status, and deleting prep items.
 */
function meetingPrepItems(config: MeetingPrepItemsConfig): Record<string, unknown> {
    return {
        items: config.items,
        newType: 'agenda_item' as string,
        newDuration: '' as string,
        newAssignee: '' as string,
        newContent: '' as string,

        typeConfig: {
            agenda_item: { icon: 'A', class: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' },
            question: { icon: 'Q', class: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400' },
            action: { icon: '!', class: 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' },
        } as Record<string, TypeConfig>,

        /**
         * Calculates the total duration in minutes of all prep items.
         */
        get totalMinutes(): number {
            return (this as unknown as { items: PrepItem[] }).items.reduce(
                (sum: number, item: PrepItem) => sum + (item.duration_minutes ?? 0),
                0,
            );
        },

        /**
         * Adds a new prep item via POST and appends it to the list.
         */
        async addPrepItem(this: Record<string, unknown>): Promise<void> {
            const payload: Record<string, unknown> = {
                meeting_id: config.meetingId,
                content: this.newContent as string,
                type: this.newType as string,
            };
            const duration = this.newDuration as string;
            const assignee = this.newAssignee as string;
            if (duration) payload.duration_minutes = parseInt(duration, 10);
            if (assignee) payload.team_member_id = parseInt(assignee, 10);

            try {
                const response = await apiClient.post<PrepItem>(config.storeEndpoint, payload);
                (this.items as PrepItem[]).push(response.data);
                this.newContent = '';
                this.newDuration = '';
                this.newAssignee = '';
            } catch {
                console.error('[meetingPrepItems] Failed to add prep item');
            }
        },

        /**
         * Toggles the discussed state of a prep item at the given index.
         */
        async toggleDiscussed(this: Record<string, unknown>, index: number): Promise<void> {
            const items = this.items as PrepItem[];
            const item = items[index];
            const newValue = !item.is_discussed;
            item.is_discussed = newValue;

            try {
                await apiClient.patch('/prep-items/' + String(item.id), { is_discussed: newValue });
            } catch {
                console.error('[meetingPrepItems] Failed to toggle discussed');
            }
        },

        /**
         * Deletes a prep item at the given index.
         */
        async deletePrepItem(this: Record<string, unknown>, index: number): Promise<void> {
            const items = this.items as PrepItem[];
            const item = items[index];

            try {
                await apiClient.delete('/prep-items/' + String(item.id));
                items.splice(index, 1);
            } catch {
                console.error('[meetingPrepItems] Failed to delete prep item');
            }
        },
    };
}

export { meetingPrepItems };
export type { MeetingPrepItemsConfig, PrepItem, PrepMemberOption };
