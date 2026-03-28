import { computePosition, offset, flip, shift } from '@floating-ui/dom';

/**
 * Alpine.js component for a dropdown menu positioned using Floating UI.
 * Uses `$refs.button` as the reference element and `$refs.content` as
 * the floating element.
 */
function popperDropdown(): Record<string, unknown> {
    return {
        isOpen: false as boolean,

        /**
         * Positions the dropdown content relative to the button on the
         * next tick, once Alpine has rendered the DOM refs.
         */
        init(this: PopperDropdownContext): void {
            this.$nextTick(() => {
                updatePosition(this.$refs.button, this.$refs.content);
            });
        },

        /**
         * Toggles the dropdown open state and repositions the floating element.
         */
        toggle(this: PopperDropdownContext): void {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                updatePosition(this.$refs.button, this.$refs.content);
            }
        },
    };
}

/**
 * Shape of the Alpine component context including refs and state.
 */
interface PopperDropdownContext {
    isOpen: boolean;
    $refs: { button: HTMLElement; content: HTMLElement };
    $nextTick: (callback: () => void) => void;
}

/**
 * Computes and applies the floating position for the dropdown content
 * relative to the button reference element.
 */
async function updatePosition(reference: HTMLElement, floating: HTMLElement): Promise<void> {
    const { x, y } = await computePosition(reference, floating, {
        placement: 'bottom-end',
        strategy: 'fixed',
        middleware: [offset(4), flip(), shift()],
    });

    Object.assign(floating.style, {
        left: `${x}px`,
        top: `${y}px`,
    });
}

export { popperDropdown };
