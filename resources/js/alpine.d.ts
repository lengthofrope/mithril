declare module 'alpinejs' {
    type AlpineDataFactory = (...args: unknown[]) => Record<string, unknown>;

    const Alpine: {
        start(): void;
        data(name: string, callback: AlpineDataFactory): void;
        store(name: string, value?: unknown): unknown;
    };

    export default Alpine;
}
