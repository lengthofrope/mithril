/**
 * Configuration for the confirmDialog Alpine component.
 */
interface ConfirmDialogConfig {
    title: string;
    message: string;
    onConfirm: () => void;
}

/**
 * Alpine.js component that drives a confirmation modal dialog.
 * Open the dialog with `open()`, confirm with `confirm()`, and cancel with `cancel()`.
 */
function confirmDialog(config: ConfirmDialogConfig): Record<string, unknown> {
    return {
        isOpen: false,
        title: config.title,
        message: config.message,

        /**
         * Opens the confirmation dialog.
         */
        open(this: { isOpen: boolean }): void {
            this.isOpen = true;
        },

        /**
         * Closes the dialog without triggering the confirm action.
         */
        cancel(this: { isOpen: boolean }): void {
            this.isOpen = false;
        },

        /**
         * Executes the configured onConfirm callback and closes the dialog.
         */
        confirm(this: { isOpen: boolean }): void {
            this.isOpen = false;
            config.onConfirm();
        },
    };
}

export { confirmDialog };
export type { ConfirmDialogConfig };
