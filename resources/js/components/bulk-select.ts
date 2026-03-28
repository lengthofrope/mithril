/**
 * Alpine.js component for managing bulk task selection on the tasks index page.
 * Tracks selected task IDs and provides toggle/clear operations.
 */
function bulkSelect(): Record<string, unknown> {
    return {
        selectedIds: [] as number[],

        /**
         * Toggles a task ID in the selection array. Adds it if not present;
         * removes it if already selected.
         */
        toggleTask(this: { selectedIds: number[] }, id: number): void {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) {
                this.selectedIds.push(id);
            } else {
                this.selectedIds.splice(idx, 1);
            }
        },

        /**
         * Clears all selected task IDs.
         */
        clearSelection(this: { selectedIds: number[] }): void {
            this.selectedIds = [];
        },
    };
}

export { bulkSelect };
