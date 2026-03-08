/**
 * Registers a delegated double-click handler on a container element.
 * When a child (or itself) with a `data-href` attribute is double-clicked,
 * navigates to that URL — unless the user selected text.
 */
function registerDoubleClickNavigation(container: HTMLElement): void {
    container.addEventListener('dblclick', (event: MouseEvent) => {
        const target = event.target as HTMLElement;
        const item = target.closest<HTMLElement>('[data-href]');

        if (item === null) {
            return;
        }

        const selection = window.getSelection();

        if (selection !== null && selection.toString().trim().length > 0) {
            return;
        }

        const href = item.dataset['href'];

        if (href === undefined || href === '') {
            return;
        }

        window.location.href = href;
    });
}

export { registerDoubleClickNavigation };
