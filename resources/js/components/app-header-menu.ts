/**
 * Alpine.js component for the application header mobile menu toggle.
 * Manages the open/closed state of the application menu on smaller screens.
 */
function appHeaderMenu(): Record<string, unknown> {
    return {
        isApplicationMenuOpen: false,

        /**
         * Toggles the application menu visibility.
         */
        toggleApplicationMenu(): void {
            (this as Record<string, unknown>).isApplicationMenuOpen =
                !(this as Record<string, unknown>).isApplicationMenuOpen;
        },
    };
}

export { appHeaderMenu };
