/**
 * Configuration for the flashMessage Alpine component.
 */
interface FlashMessageConfig {
    duration?: number;
}

/**
 * Default auto-dismiss duration in milliseconds.
 */
const DEFAULT_DURATION = 3000;

/**
 * Alpine.js component for auto-dismissing flash messages.
 * Automatically hides the element after the configured duration.
 */
function flashMessage(config: FlashMessageConfig = {}): Record<string, unknown> {
    const duration = config.duration ?? DEFAULT_DURATION;

    return {
        show: true,

        /**
         * Starts the auto-dismiss timer when the component is mounted.
         */
        init(): void {
            setTimeout(() => {
                (this as Record<string, unknown>).show = false;
            }, duration);
        },
    };
}

export { flashMessage };
export type { FlashMessageConfig };
