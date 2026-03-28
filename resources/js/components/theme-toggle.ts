/**
 * Alpine.js component for toggling between dark and light themes.
 * Reads the current theme from localStorage and applies the `dark` class
 * to the document element accordingly.
 */
function themeToggle(): Record<string, unknown> {
    return {
        theme: 'dark' as string,

        /**
         * Reads the stored theme preference and applies it to the document.
         */
        init(this: ThemeToggleContext): void {
            this.theme = localStorage.getItem('theme') ?? 'dark';
            applyTheme(this.theme);
        },

        /**
         * Toggles between light and dark themes, persists the choice,
         * and updates the document element class.
         */
        toggle(this: ThemeToggleContext): void {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            localStorage.setItem('theme', this.theme);
            applyTheme(this.theme);
        },
    };
}

/**
 * Shape of the theme toggle Alpine component context.
 */
interface ThemeToggleContext {
    theme: string;
}

/**
 * Adds or removes the `dark` class on the document element based on the theme.
 */
function applyTheme(theme: string): void {
    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
}

export { themeToggle };
