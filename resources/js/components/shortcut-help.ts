/**
 * A single keyboard shortcut entry for display in the help overlay.
 */
interface ShortcutEntry {
    keys: string[];
    description: string;
}

/**
 * A named group of related shortcuts shown together in the help overlay.
 */
interface ShortcutGroup {
    label: string;
    shortcuts: ShortcutEntry[];
}

/**
 * All shortcut groups displayed in the help overlay, defined statically.
 */
const SHORTCUT_GROUPS: ShortcutGroup[] = [
    {
        label: 'Navigation',
        shortcuts: [
            { keys: ['1'], description: 'Dashboard' },
            { keys: ['2'], description: 'Meetings' },
            { keys: ['3'], description: 'Tasks' },
            { keys: ['4'], description: 'Follow-ups' },
            { keys: ['5'], description: 'Notes' },
            { keys: ['6'], description: 'Calendar' },
            { keys: ['7'], description: 'E-mail' },
            { keys: ['8'], description: 'Jira' },
            { keys: ['9'], description: 'Teams' },
        ],
    },
    {
        label: 'General',
        shortcuts: [
            { keys: ['/'], description: 'Focus search' },
            { keys: ['?'], description: 'Keyboard shortcuts' },
        ],
    },
    {
        label: 'Create (c + key)',
        shortcuts: [
            { keys: ['c', 't'], description: 'New task' },
            { keys: ['c', 'f'], description: 'New follow-up' },
            { keys: ['c', 'n'], description: 'New note' },
            { keys: ['c', 'm'], description: 'New meeting' },
        ],
    },
];

/**
 * Alpine.js component that manages the keyboard shortcut help overlay.
 * Listens for the toggle-shortcut-help custom window event and exposes
 * open/close/toggle methods for use in the Blade component.
 */
function shortcutHelp(): Record<string, unknown> {
    return {
        isOpen: false,
        groups: SHORTCUT_GROUPS as ShortcutGroup[],

        /**
         * Registers the toggle-shortcut-help window event listener.
         */
        init(this: { toggle: () => void }): void {
            window.addEventListener('toggle-shortcut-help', () => {
                this.toggle();
            });
        },

        /**
         * Opens the shortcut help overlay.
         */
        open(this: { isOpen: boolean }): void {
            this.isOpen = true;
        },

        /**
         * Closes the shortcut help overlay.
         */
        close(this: { isOpen: boolean }): void {
            this.isOpen = false;
        },

        /**
         * Toggles the shortcut help overlay open or closed.
         */
        toggle(this: { isOpen: boolean }): void {
            this.isOpen = !this.isOpen;
        },
    };
}

export { shortcutHelp };
export type { ShortcutGroup, ShortcutEntry };
