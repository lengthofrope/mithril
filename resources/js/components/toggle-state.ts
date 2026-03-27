/**
 * Configuration for the toggleState Alpine component.
 * Accepts an object with named properties and their initial values.
 */
interface ToggleStateConfig {
    [key: string]: boolean | string | number;
}

/**
 * Alpine.js component that provides reactive state for simple toggle patterns.
 * Replaces inline x-data objects that contain only boolean, string, or number properties
 * (no methods, no fetch calls, no computed properties).
 *
 * Usage in Blade:
 *   x-data="toggleState({ editOpen: false, deleteOpen: false })"
 *   x-data="toggleState({ activeTab: 'tasks' })"
 */
function toggleState(config: ToggleStateConfig): ToggleStateConfig {
    return { ...config };
}

export { toggleState };
export type { ToggleStateConfig };
