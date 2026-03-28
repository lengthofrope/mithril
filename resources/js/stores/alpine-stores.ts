import type Alpine from 'alpinejs';
import { apiClient } from '../utils/api-client';

/**
 * Shape of the theme Alpine store.
 */
interface ThemeStore {
    theme: string;
    init(): void;
    toggle(): void;
    updateTheme(): void;
}

/**
 * Shape of the sidebar Alpine store.
 */
interface SidebarStore {
    sidebarCollapsed: boolean;
    isExpanded: boolean;
    isMobileOpen: boolean;
    toggleExpanded(): void;
    toggleMobileOpen(): void;
    setMobileOpen(val: boolean): void;
    persistCollapsed(collapsed: boolean): void;
    handleResize(): void;
}

/**
 * Reads a boolean value from a meta tag. Returns the fallback when the tag is absent.
 */
function readMetaBoolean(name: string, fallback: boolean): boolean {
    const meta = document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`);
    if (!meta) {
        return fallback;
    }
    return meta.content === '1';
}

/**
 * Registers the global Alpine stores for theme and sidebar.
 * Must be called before Alpine.start().
 */
function registerAlpineStores(alpine: typeof Alpine): void {
    alpine.store('theme', {
        theme: 'dark',

        /**
         * Reads the stored theme preference from localStorage and applies it.
         */
        init(this: ThemeStore): void {
            const savedTheme = localStorage.getItem('theme');
            this.theme = savedTheme ?? 'dark';
            this.updateTheme();
        },

        /**
         * Toggles between light and dark theme, persists the choice, and updates the DOM.
         */
        toggle(this: ThemeStore): void {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            localStorage.setItem('theme', this.theme);
            this.updateTheme();
        },

        /**
         * Applies or removes the dark class on both html and body elements.
         */
        updateTheme(this: ThemeStore): void {
            const html = document.documentElement;
            const body = document.body;
            if (this.theme === 'dark') {
                html.classList.add('dark');
                body.classList.add('dark', 'bg-gray-900');
            } else {
                html.classList.remove('dark');
                body.classList.remove('dark', 'bg-gray-900');
            }
        },
    } as ThemeStore);

    const sidebarCollapsed = readMetaBoolean('sidebar-collapsed', false);
    const hasPersistence = document.querySelector<HTMLMetaElement>('meta[name="sidebar-collapsed"]') !== null;

    alpine.store('sidebar', {
        sidebarCollapsed,
        isExpanded: window.innerWidth >= 1280 && !sidebarCollapsed,
        isMobileOpen: false,

        /**
         * Toggles sidebar expanded state and persists the preference.
         */
        toggleExpanded(this: SidebarStore): void {
            this.isExpanded = !this.isExpanded;
            this.isMobileOpen = false;
            this.persistCollapsed(!this.isExpanded);
        },

        /**
         * Toggles the mobile sidebar overlay.
         */
        toggleMobileOpen(this: SidebarStore): void {
            this.isMobileOpen = !this.isMobileOpen;
        },

        /**
         * Sets the mobile sidebar open state to the given value.
         */
        setMobileOpen(this: SidebarStore, val: boolean): void {
            this.isMobileOpen = val;
        },

        /**
         * Persists the collapsed state to the server when a meta tag is present.
         */
        persistCollapsed(this: SidebarStore, collapsed: boolean): void {
            if (!hasPersistence) {
                return;
            }
            apiClient.patch('/settings/sidebar-collapsed', { sidebar_collapsed: collapsed });
        },

        /**
         * Handles window resize to adjust sidebar state for mobile/desktop breakpoints.
         */
        handleResize(this: SidebarStore): void {
            if (window.innerWidth < 1280) {
                this.isMobileOpen = false;
                this.isExpanded = false;
            } else {
                this.isMobileOpen = false;
                this.isExpanded = !this.sidebarCollapsed;
            }
        },
    } as SidebarStore);

    window.addEventListener('resize', () => {
        (alpine.store('sidebar') as SidebarStore).handleResize();
    });
}

export { registerAlpineStores };
export type { ThemeStore, SidebarStore };
