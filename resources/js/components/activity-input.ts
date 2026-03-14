import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the activityInput Alpine component.
 */
interface ActivityInputConfig {
    parentType: string;
    parentId: number;
}

/**
 * The active tab in the activity input area.
 */
type ActiveTab = 'comment' | 'link' | 'file';

const MAX_FILES = 5;

/**
 * Reads the CSRF token from the meta tag injected by Laravel's Blade layout.
 */
function readCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Alpine.js component for submitting activity feed entries (comments, links,
 * and file attachments) to a parent entity's activity endpoint.
 */
function activityInput(config: ActivityInputConfig): Record<string, unknown> {
    return {
        activeTab: 'comment' as ActiveTab,
        body: '' as string,
        url: '' as string,
        linkTitle: '' as string,
        linkBody: '' as string,
        files: [] as File[],
        isSubmitting: false as boolean,
        error: null as string | null,

        /**
         * Switches the visible input tab.
         */
        setTab(this: { activeTab: ActiveTab }, tab: ActiveTab): void {
            this.activeTab = tab;
        },

        /**
         * Appends files from a FileList to the files array, capped at MAX_FILES.
         */
        addFiles(this: { files: File[]; error: string | null }, fileList: FileList): void {
            const remaining = MAX_FILES - this.files.length;

            if (remaining <= 0) {
                this.error = `Maximum of ${MAX_FILES} files allowed.`;
                return;
            }

            const incoming = Array.from(fileList).slice(0, remaining);
            this.files = [...this.files, ...incoming];
            this.error = null;
        },

        /**
         * Removes a file from the files array by index.
         */
        removeFile(this: { files: File[] }, index: number): void {
            this.files = this.files.filter((_, i) => i !== index);
        },

        /**
         * Posts a comment activity to the API endpoint.
         */
        async submitComment(this: {
            body: string;
            isSubmitting: boolean;
            error: string | null;
        }): Promise<void> {
            if (!this.body.trim()) {
                return;
            }

            this.isSubmitting = true;
            this.error = null;

            try {
                await apiClient.post(
                    `/api/v1/${config.parentType}/${config.parentId}/activities`,
                    { type: 'comment', body: this.body },
                );

                this.body = '';
                apiClient.dispatchDataChanged('activities');
            } catch (err) {
                const apiError = err as ApiError;
                this.error = apiError.message ?? 'Failed to post comment.';
                console.error('[activityInput] submitComment failed:', apiError.message);
            } finally {
                this.isSubmitting = false;
            }
        },

        /**
         * Posts a link activity to the API endpoint.
         */
        async submitLink(this: {
            url: string;
            linkTitle: string;
            linkBody: string;
            isSubmitting: boolean;
            error: string | null;
        }): Promise<void> {
            if (!this.url.trim()) {
                return;
            }

            this.isSubmitting = true;
            this.error = null;

            try {
                await apiClient.post(
                    `/api/v1/${config.parentType}/${config.parentId}/activities`,
                    {
                        type: 'link',
                        url: this.url,
                        link_title: this.linkTitle,
                        body: this.linkBody,
                    },
                );

                this.url = '';
                this.linkTitle = '';
                this.linkBody = '';
                apiClient.dispatchDataChanged('activities');
            } catch (err) {
                const apiError = err as ApiError;
                this.error = apiError.message ?? 'Failed to post link.';
                console.error('[activityInput] submitLink failed:', apiError.message);
            } finally {
                this.isSubmitting = false;
            }
        },

        /**
         * Uploads file attachments using a multipart FormData POST. Uses raw
         * fetch because apiClient does not support multipart/form-data.
         */
        async submitFiles(this: {
            files: File[];
            isSubmitting: boolean;
            error: string | null;
        }): Promise<void> {
            if (this.files.length === 0) {
                return;
            }

            this.isSubmitting = true;
            this.error = null;

            const formData = new FormData();
            formData.append('type', 'attachment');
            this.files.forEach((file, i) => formData.append(`files[${i}]`, file));

            try {
                const response = await fetch(
                    `/api/v1/${config.parentType}/${config.parentId}/activities`,
                    {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': readCsrfToken(),
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: formData,
                    },
                );

                if (!response.ok) {
                    const json: unknown = await response.json();
                    const apiError = json as ApiError;
                    throw apiError;
                }

                this.files = [];
                apiClient.dispatchDataChanged('activities');
            } catch (err) {
                const apiError = err as ApiError;
                this.error = apiError.message ?? 'Failed to upload files.';
                console.error('[activityInput] submitFiles failed:', apiError.message);
            } finally {
                this.isSubmitting = false;
            }
        },

        /**
         * Deletes an activity entry via the API.
         */
        async deleteActivity(
            this: { error: string | null },
            activityId: number,
        ): Promise<void> {
            try {
                await apiClient.delete(
                    `/api/v1/${config.parentType}/${config.parentId}/activities/${activityId}`,
                );

                apiClient.dispatchDataChanged('activities');
            } catch (err) {
                const apiError = err as ApiError;
                this.error = apiError.message ?? 'Failed to delete activity.';
                console.error('[activityInput] deleteActivity failed:', apiError.message);
            }
        },
    };
}

export { activityInput };
export type { ActivityInputConfig, ActiveTab };
