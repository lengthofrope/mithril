declare module 'alpinejs' {
    type AlpineDataFactory = (...args: unknown[]) => Record<string, unknown>;
    type AlpinePlugin = (Alpine: typeof import('alpinejs').default) => void;

    const Alpine: {
        start(): void;
        plugin(plugin: AlpinePlugin): void;
        data(name: string, callback: AlpineDataFactory): void;
        store(name: string, value?: unknown): unknown;
        morph(existing: HTMLElement, newHtml: string): void;
    };

    export default Alpine;
}

declare module '@alpinejs/morph' {
    import type Alpine from 'alpinejs';
    type AlpinePlugin = (alpine: typeof Alpine) => void;
    const morph: AlpinePlugin;
    export default morph;
}
