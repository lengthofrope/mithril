import { apiClient } from '../utils/api-client';
import type { ApiError } from '../types/api';

/**
 * Configuration for the settingsSpeechService Alpine component.
 */
interface SettingsSpeechServiceConfig {
    endpoint: string;
    mode: string;
    url: string;
    token: string;
    serverTranscriptionEnabled: boolean;
}

/**
 * Result of a connection test to the local speech service.
 */
interface TestResult {
    success: boolean;
    device?: string;
    ready?: boolean;
    error?: string;
}

/**
 * Alpine.js component for configuring the speech service mode and connection settings.
 * Uses raw fetch for the external service test (not apiClient).
 */
function settingsSpeechService(config: SettingsSpeechServiceConfig): Record<string, unknown> {
    return {
        mode: config.mode,
        url: config.url,
        token: config.token,
        saving: false as boolean,
        saved: false as boolean,
        error: '' as string,
        testResult: null as TestResult | null,
        testing: false as boolean,
        serverTranscriptionEnabled: config.serverTranscriptionEnabled,

        /**
         * Saves the speech service settings to the server.
         */
        async save(this: Record<string, unknown>): Promise<void> {
            this.saving = true;
            this.saved = false;
            this.error = '';
            try {
                await apiClient.patch(config.endpoint, {
                    speech_service_mode: this.mode as string,
                    speech_service_url: this.url as string,
                    speech_service_token: this.token as string,
                });
                this.saved = true;
                setTimeout(() => { this.saved = false; }, 2000);
            } catch (err) {
                const apiError = err as ApiError;
                const errors = apiError.errors ?? {};
                const firstError = Object.values(errors).flat()[0];
                this.error = firstError ?? 'Failed to save.';
            } finally {
                this.saving = false;
            }
        },

        /**
         * Tests the connection to the local speech service via raw fetch to the external URL.
         */
        async testConnection(this: Record<string, unknown>): Promise<void> {
            const serviceUrl = this.url as string;
            if (!serviceUrl) return;

            this.testing = true;
            this.testResult = null;

            try {
                const headers: Record<string, string> = {};
                const serviceToken = this.token as string;
                if (serviceToken) headers['X-Speech-Token'] = serviceToken;

                const response = await fetch(serviceUrl.replace(/\/+$/, '') + '/health', { headers });

                if (response.ok) {
                    const data = await response.json() as Record<string, unknown>;
                    this.testResult = {
                        success: true,
                        device: (data.device as string) ?? 'unknown',
                        ready: (data.ready as boolean) ?? false,
                    };
                } else if (response.status === 401) {
                    this.testResult = { success: false, error: 'Authentication failed (401). Check your token.' };
                } else {
                    this.testResult = { success: false, error: 'Service returned status ' + String(response.status) + '.' };
                }
            } catch {
                this.testResult = { success: false, error: 'Could not connect to ' + (this.url as string) + '. Is the service running?' };
            } finally {
                this.testing = false;
            }
        },
    };
}

export { settingsSpeechService };
export type { SettingsSpeechServiceConfig, TestResult };
