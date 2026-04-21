/**
 * Route map for number-key navigation shortcuts.
 */
type ShortcutRoutes = Record<string, string>;

/**
 * Route map for chord-create shortcuts (c + key).
 */
interface ChordCreateEntry {
    type: string;
    route: string;
}

/**
 * Internal shape of the keyboardShortcuts Alpine component,
 * used for explicit `this` annotations on cross-calling methods.
 */
interface KeyboardShortcutsComponent {
    _chordActive: boolean;
    _chordTimer: ReturnType<typeof setTimeout> | null;
    _isEditableTarget(target: HTMLElement): boolean;
    _handleKeydown(event: KeyboardEvent): void;
    _enterChordMode(): void;
    _cancelChordMode(): void;
    _dispatchCreateEntity(type: string, route: string): void;
}

/**
 * Route definitions for the number keys 1 to 9.
 */
const SHORTCUT_ROUTES: ShortcutRoutes = {
    '1': '/',
    '2': '/meetings',
    '3': '/tasks',
    '4': '/follow-ups',
    '5': '/notes',
    '6': '/calendar',
    '7': '/mail',
    '8': '/jira',
    '9': '/teams',
};

/**
 * Chord create entries keyed by the second key press after `c`.
 */
const CHORD_CREATE_MAP: Record<string, ChordCreateEntry> = {
    t: { type: 'task', route: '/tasks' },
    f: { type: 'follow-up', route: '/follow-ups' },
    n: { type: 'note', route: '/notes' },
    m: { type: 'meeting', route: '/meetings' },
};

/**
 * Duration in milliseconds before chord mode times out without a second keypress.
 */
const CHORD_TIMEOUT_MS = 500;

/**
 * Alpine.js component that registers global keyboard shortcuts for navigation,
 * chord-based entity creation, and the shortcut help overlay.
 * Shortcuts are suppressed when focus is inside any editable element.
 */
function keyboardShortcuts(): Record<string, unknown> {
    return {
        _chordActive: false,
        _chordTimer: null as ReturnType<typeof setTimeout> | null,

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
         * Enters chord mode, starting the timeout to cancel it automatically.
         */
        _enterChordMode(this: KeyboardShortcutsComponent): void {
            this._chordActive = true;

            if (this._chordTimer !== null) {
                clearTimeout(this._chordTimer);
            }

            this._chordTimer = setTimeout(() => {
                this._cancelChordMode();
            }, CHORD_TIMEOUT_MS);
        },

        /**
         * Exits chord mode and clears the timeout.
         */
        _cancelChordMode(this: KeyboardShortcutsComponent): void {
            this._chordActive = false;

            if (this._chordTimer !== null) {
                clearTimeout(this._chordTimer);
                this._chordTimer = null;
            }
        },

        /**
         * Dispatches a create-entity custom event, or navigates to the entity
         * route with ?create=1 when no matching modal element is found in the DOM.
         */
        _dispatchCreateEntity(type: string, route: string): void {
            const modal = document.querySelector(`[data-create-modal="${type}"]`);

            if (modal) {
                window.dispatchEvent(new CustomEvent('create-entity', { detail: { type } }));
            } else {
                window.location.href = `${route}?create=1`;
            }
        },

        /**
         * Processes a keydown event and triggers the matching navigation,
         * chord, or overlay action.
         */
        _handleKeydown(this: KeyboardShortcutsComponent, event: KeyboardEvent): void {
            const target = event.target as HTMLElement;

            if (this._isEditableTarget(target)) {
                return;
            }

            if (event.altKey || event.metaKey) {
                return;
            }

            if (event.key === '?' && event.shiftKey) {
                event.preventDefault();
                window.dispatchEvent(new CustomEvent('toggle-shortcut-help'));
                return;
            }

            if (event.shiftKey) {
                return;
            }

            if (this._chordActive) {
                this._cancelChordMode();
                const chord = CHORD_CREATE_MAP[event.key];

                if (chord) {
                    event.preventDefault();
                    this._dispatchCreateEntity(chord.type, chord.route);
                }

                return;
            }

            if (event.key === 'c' && !event.ctrlKey) {
                event.preventDefault();
                this._enterChordMode();
                return;
            }

            if (event.key === '/') {
                event.preventDefault();
                const searchInput = document.querySelector('[data-global-search-input]') as HTMLElement | null;
                searchInput?.focus();
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
export type { ShortcutRoutes, ChordCreateEntry };
