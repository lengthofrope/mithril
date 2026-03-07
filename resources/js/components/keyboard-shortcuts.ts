/**
 * Route map for number-key navigation shortcuts.
 */
type ShortcutRoutes = Record<string, string>;

/**
 * Internal shape of the keyboardShortcuts Alpine component,
 * used for explicit `this` annotations on cross-calling methods.
 */
interface KeyboardShortcutsComponent {
    _isEditableTarget(target: HTMLElement): boolean;
    _handleKeydown(event: KeyboardEvent): void;
}

/**
 * Route definitions for the number keys 1–7.
 */
const SHORTCUT_ROUTES: ShortcutRoutes = {
    '1': '/',
    '2': '/tasks',
    '3': '/follow-ups',
    '4': '/teams',
    '5': '/notes',
    '6': '/bilas',
    '7': '/weekly',
};

/**
 * Alpine.js component that registers global keyboard shortcuts for navigation.
 * Shortcuts are suppressed when focus is inside any editable element.
 */
function keyboardShortcuts(): Record<string, unknown> {
    return {
        /**
         * Binds the keydown listener on document initialisation.
         */
        init(this: KeyboardShortcutsComponent): void {
            document.addEventListener('keydown', (event: KeyboardEvent) => {
                this._handleKeydown(event);
            });
        },

        /**
         * Determines whether the event originated from an editable element
         * where keyboard input must not be intercepted.
         */
        _isEditableTarget(target: HTMLElement): boolean {
            return (
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable
            );
        },

        /**
         * Processes a keydown event and triggers the matching navigation action.
         */
        _handleKeydown(this: KeyboardShortcutsComponent, event: KeyboardEvent): void {
            const target = event.target as HTMLElement;

            if (this._isEditableTarget(target)) {
                return;
            }

            if (event.altKey || event.metaKey || event.shiftKey) {
                return;
            }

            if (SHORTCUT_ROUTES[event.key]) {
                event.preventDefault();
                window.location.href = SHORTCUT_ROUTES[event.key];
                return;
            }

            if (event.key === 'n' && !event.ctrlKey) {
                event.preventDefault();
                window.location.href = '/tasks';
            }
        },
    };
}

export { keyboardShortcuts };
export type { ShortcutRoutes };
