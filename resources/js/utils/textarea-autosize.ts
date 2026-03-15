/**
 * Global textarea auto-resize utility.
 *
 * Automatically grows all textareas to fit their content so that
 * scrollbars never appear inside a textarea. Uses event delegation
 * on the document, so dynamically added textareas (e.g. by Alpine.js)
 * are covered without any per-element setup.
 */

/**
 * Adjusts the height of a textarea element to fit its content.
 */
function resize(textarea: HTMLTextAreaElement): void {
    textarea.style.overflow = 'hidden';
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight}px`;
}

/**
 * Initialises global textarea auto-resize behaviour.
 *
 * Listens for input events via delegation and applies initial sizing
 * to all existing textareas. Also observes the DOM for dynamically
 * added textareas.
 */
function initTextareaAutosize(): void {
    document.addEventListener('input', (event: Event) => {
        const target = event.target as HTMLElement;

        if (target.tagName === 'TEXTAREA') {
            resize(target as HTMLTextAreaElement);
        }
    });

    applyToExisting();
    observeNewTextareas();
}

/**
 * Applies auto-resize to all textareas currently in the DOM.
 */
function applyToExisting(): void {
    const textareas = document.querySelectorAll<HTMLTextAreaElement>('textarea');
    textareas.forEach((textarea) => resize(textarea));
}

/**
 * Observes the DOM for dynamically added textareas and resizes them.
 */
function observeNewTextareas(): void {
    const observer = new MutationObserver((mutations: MutationRecord[]) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node instanceof HTMLTextAreaElement) {
                    resize(node);
                }

                if (node instanceof HTMLElement) {
                    const nested = node.querySelectorAll<HTMLTextAreaElement>('textarea');
                    nested.forEach((textarea) => resize(textarea));
                }
            }
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
}

export { initTextareaAutosize, resize };
