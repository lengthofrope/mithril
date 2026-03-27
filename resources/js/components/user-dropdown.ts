/**
 * Alpine.js component for the user dropdown in the application header.
 * Manages the open/closed state of the dropdown menu.
 */
function userDropdown(): Record<string, unknown> {
    return {
        dropdownOpen: false,

        /**
         * Toggles the dropdown visibility.
         */
        toggleDropdown(): void {
            (this as Record<string, unknown>).dropdownOpen = !(this as Record<string, unknown>).dropdownOpen;
        },

        /**
         * Closes the dropdown.
         */
        closeDropdown(): void {
            (this as Record<string, unknown>).dropdownOpen = false;
        },
    };
}

export { userDropdown };
