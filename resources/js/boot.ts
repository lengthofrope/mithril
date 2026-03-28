/**
 * Boot utilities for the application.
 * Handles service worker registration, view transition click-origin capture,
 * and the pagereveal handler for view transitions.
 */

/**
 * Registers the service worker, sets up click-origin capture for view transitions,
 * and attaches the pagereveal handler.
 */
function initBoot(): void {
    registerServiceWorker();
    registerClickOriginCapture();
    registerPageRevealHandler();
}

/**
 * Registers the PWA service worker when the browser supports it.
 */
function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

/**
 * Captures click coordinates and stores them in sessionStorage
 * for use by the view transition animation origin.
 */
function registerClickOriginCapture(): void {
    document.addEventListener('click', (e: MouseEvent) => {
        let x = e.clientX;
        let y = e.clientY;

        if (!x && !y) {
            const btn = (e.target as HTMLElement).closest('button, a, [type="submit"]');
            if (btn) {
                const rect = btn.getBoundingClientRect();
                x = rect.left + rect.width / 2;
                y = rect.top + rect.height / 2;
            }
        }

        sessionStorage.setItem('click-x', (x / window.innerWidth * 100).toFixed(1) + '%');
        sessionStorage.setItem('click-y', (y / window.innerHeight * 100).toFixed(1) + '%');
    });
}

/**
 * Registers the pagereveal event handler that sets CSS custom properties
 * for the view transition clip-path origin based on stored click coordinates.
 */
function registerPageRevealHandler(): void {
    window.addEventListener('pagereveal', (e: Event) => {
        if (!(e as PageRevealEvent).viewTransition) {
            return;
        }
        const x = sessionStorage.getItem('click-x') ?? '50%';
        const y = sessionStorage.getItem('click-y') ?? '50%';
        document.documentElement.style.setProperty('--click-x', x);
        document.documentElement.style.setProperty('--click-y', y);
    });
}

/**
 * Extended Event interface for the pagereveal event.
 */
interface PageRevealEvent extends Event {
    viewTransition: unknown;
}

export { initBoot };
