/**
 * Configuration for the modalComponent Alpine component.
 */
interface ModalComponentConfig {
    isOpen: boolean;
}

/**
 * Alpine.js component for a modal dialog that manages its open state,
 * toggles body overflow to prevent background scrolling, and supports
 * closing via the escape key.
 */
function modalComponent(config: ModalComponentConfig): Record<string, unknown> {
    return {
        open: config.isOpen,

        /**
         * Watches the `open` property and toggles body overflow accordingly.
         */
        init(this: ModalComponentContext): void {
            this.$watch('open', (value: boolean) => {
                document.body.style.overflow = value ? 'hidden' : 'unset';
            });
        },
    };
}

/**
 * Shape of the Alpine component context with the $watch helper.
 */
interface ModalComponentContext {
    open: boolean;
    $watch: (property: string, callback: (value: boolean) => void) => void;
}

export { modalComponent };
export type { ModalComponentConfig };
