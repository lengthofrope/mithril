/**
 * Configuration for a single sub-item within a menu item.
 */
interface SubItem {
    name: string;
    path: string;
    new?: boolean;
    pro?: boolean;
}

/**
 * Configuration for a menu item in the sidebar navigation.
 */
interface MenuItem {
    icon?: string;
    name: string;
    path?: string;
    separator?: boolean;
    subItems?: SubItem[];
    viewPreference?: string;
    viewPaths?: Record<string, string>;
    new?: boolean;
}

/**
 * Configuration for a group of menu items.
 */
interface MenuGroup {
    title: string;
    items: MenuItem[];
}

/**
 * Configuration passed from Blade to the sidebarNav Alpine component.
 * Menu groups are loaded from a script tag to avoid HTML attribute parsing issues with complex JSON.
 */
interface SidebarNavConfig {
    currentPath: string;
}

/**
 * Internal context shape for the sidebar navigation Alpine component.
 */
interface SidebarNavContext {
    menuGroups: MenuGroup[];
    currentPath: string;
    openSubmenus: Record<string, boolean>;
    init(): void;
    initializeActiveMenus(): void;
    toggleSubmenu(groupIndex: number, itemIndex: number, firstSubItemPath?: string | null): void;
    isSubmenuOpen(groupIndex: number, itemIndex: number): boolean;
    isActive(path: string): boolean;
    readonly isCollapsed: boolean;
}

/**
 * Alpine.js component for sidebar navigation.
 * Manages submenu open/close state and active path detection
 * based on menu group configuration passed from PHP.
 */
function sidebarNav(config: SidebarNavConfig): Record<string, unknown> {
    const menuDataEl = document.getElementById('sidebar-menu-data');
    const menuGroups: MenuGroup[] = menuDataEl ? JSON.parse(menuDataEl.textContent ?? '[]') : [];

    return {
        menuGroups,
        currentPath: config.currentPath,
        openSubmenus: {} as Record<string, boolean>,

        /**
         * Initializes the component by detecting which submenus should be open.
         */
        init(this: SidebarNavContext): void {
            this.initializeActiveMenus();
        },

        /**
         * Iterates through menu groups to find submenus that contain the current path
         * and opens them automatically.
         */
        initializeActiveMenus(this: SidebarNavContext): void {
            const pathname = window.location.pathname;

            for (const [groupIndex, group] of this.menuGroups.entries()) {
                for (const [itemIndex, item] of group.items.entries()) {
                    if (item.subItems) {
                        for (const sub of item.subItems) {
                            if (
                                this.currentPath === sub.path.replace(/^\//, '') ||
                                pathname === sub.path ||
                                pathname.startsWith(sub.path + '/')
                            ) {
                                this.openSubmenus[`${groupIndex}-${itemIndex}`] = true;
                            }
                        }
                    }
                }
            }
        },

        /**
         * Toggles a submenu open/closed. When opening, closes all other submenus first.
         * Optionally navigates to the first sub-item path when opening.
         */
        toggleSubmenu(
            this: SidebarNavContext,
            groupIndex: number,
            itemIndex: number,
            firstSubItemPath: string | null = null,
        ): void {
            const key = `${groupIndex}-${itemIndex}`;
            const newState = !this.openSubmenus[key];

            if (newState) {
                this.openSubmenus = {};
            }

            this.openSubmenus[key] = newState;

            if (newState && firstSubItemPath) {
                window.location.href = firstSubItemPath;
            }
        },

        /**
         * Returns whether the submenu at the given group and item index is open.
         */
        isSubmenuOpen(this: SidebarNavContext, groupIndex: number, itemIndex: number): boolean {
            const key = `${groupIndex}-${itemIndex}`;
            return this.openSubmenus[key] ?? false;
        },

        /**
         * Checks if the given path matches the current page location.
         */
        isActive(this: SidebarNavContext, path: string): boolean {
            return window.location.pathname === path || this.currentPath === path.replace(/^\//, '');
        },

        /**
         * Computed getter that returns true when the sidebar is collapsed (not expanded and not mobile open).
         */
        get isCollapsed(): boolean {
            const store = (window.Alpine?.store('sidebar') as Record<string, boolean>) ?? {};
            return !store.isExpanded && !store.isMobileOpen;
        },
    };
}

export { sidebarNav };
export type { SidebarNavConfig, MenuGroup, MenuItem, SubItem };
