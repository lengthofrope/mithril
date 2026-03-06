import { marked } from 'marked';

/**
 * Configuration for the markdownEditor Alpine component.
 */
interface MarkdownEditorConfig {
    field: string;
}

/**
 * Alpine.js component that pairs a textarea with a live HTML preview.
 * Uses the `marked` library to convert Markdown to sanitized HTML.
 * The preview pane is toggled via `togglePreview()`.
 */
function markdownEditor(config: MarkdownEditorConfig): Record<string, unknown> {
    return {
        content: '' as string,
        preview: '' as string,
        isPreview: false,

        /**
         * Wires a watcher that keeps the rendered preview in sync with the content.
         */
        init(this: {
            content: string;
            preview: string;
            isPreview: boolean;
            renderPreview: () => Promise<void>;
            $watch: (key: string, cb: () => void) => void;
        }): void {
            this.$watch('content', () => {
                void this.renderPreview();
            });
        },

        /**
         * Converts the current Markdown content to HTML and stores it in `preview`.
         */
        async renderPreview(this: { content: string; preview: string }): Promise<void> {
            const rendered = await marked.parse(this.content);
            this.preview = rendered;
        },

        /**
         * Toggles between the editor and the preview pane.
         * Ensures the preview is up to date before switching to it.
         */
        async togglePreview(this: {
            content: string;
            preview: string;
            isPreview: boolean;
            renderPreview: () => Promise<void>;
        }): Promise<void> {
            if (!this.isPreview) {
                await this.renderPreview();
            }

            this.isPreview = !this.isPreview;
        },

        /**
         * Returns the field name from the component configuration.
         * Useful for binding the textarea `name` attribute in Blade.
         */
        getFieldName(): string {
            return config.field;
        },
    };
}

export { markdownEditor };
export type { MarkdownEditorConfig };
