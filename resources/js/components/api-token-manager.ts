/**
 * Configuration for the apiTokenManager Alpine component.
 */
interface ApiTokenManagerConfig {
    tokens: TokenData[];
    scopeAbilityMap: Record<string, string[]>;
    allAbilities: string[];
    groupedAbilities: Record<string, string[]>;
    storeUrl: string;
    destroyUrl: string;
    destroyAllUrl: string;
}

/**
 * Shape of a token as received from the server.
 */
interface TokenData {
    id: number;
    name: string;
    abilities: string[];
    created_at: string | null;
    last_used_at: string | null;
}

/**
 * Shape of the confirmation dialog state.
 */
interface ConfirmDialogState {
    show: boolean;
    title: string;
    message: string;
    onConfirm: () => void;
}

/**
 * Reads the CSRF token from the meta tag injected by Laravel.
 */
function readCsrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Alpine.js component for managing API tokens on the settings page.
 * Handles creation with scope/ability selection, revocation, and revoke-all.
 */
function apiTokenManager(config: ApiTokenManagerConfig): Record<string, unknown> {
    return {
        tokens: config.tokens,
        selectedScope: '' as string,
        showAbilities: false,
        isSubmitting: false,
        plaintextToken: '' as string,
        copied: false,
        errorMessage: '' as string,
        errors: {} as Record<string, string[]>,

        form: {
            name: '',
            abilities: [] as string[],
        },

        confirmDialog: {
            show: false,
            title: '',
            message: '',
            onConfirm: () => {},
        } as ConfirmDialogState,

        /**
         * Applies a scope tier by setting the corresponding abilities.
         */
        applyScope(this: {
            selectedScope: string;
            form: { abilities: string[] };
        }, scope: string): void {
            this.selectedScope = scope;

            if (scope === 'custom') {
                return;
            }

            const abilities = config.scopeAbilityMap[scope];

            if (abilities) {
                this.form.abilities = [...abilities];
            }
        },

        /**
         * Toggles an individual ability and switches to custom mode if needed.
         */
        toggleAbility(this: {
            form: { abilities: string[] };
            selectedScope: string;
            detectScope: () => void;
        }, ability: string): void {
            const index = this.form.abilities.indexOf(ability);

            if (index === -1) {
                this.form.abilities.push(ability);
            } else {
                this.form.abilities.splice(index, 1);
            }

            this.detectScope();
        },

        /**
         * Detects whether the current abilities match a predefined scope tier.
         */
        detectScope(this: {
            form: { abilities: string[] };
            selectedScope: string;
        }): void {
            const sorted = [...this.form.abilities].sort();

            for (const [scope, abilities] of Object.entries(config.scopeAbilityMap)) {
                const scopeSorted = [...abilities].sort();

                if (sorted.length === scopeSorted.length && sorted.every((a, i) => a === scopeSorted[i])) {
                    this.selectedScope = scope;
                    return;
                }
            }

            this.selectedScope = 'custom';
        },

        /**
         * Creates a new token via POST request.
         */
        async createToken(this: {
            form: { name: string; abilities: string[] };
            selectedScope: string;
            isSubmitting: boolean;
            plaintextToken: string;
            copied: boolean;
            errors: Record<string, string[]>;
            errorMessage: string;
            tokens: TokenData[];
        }): Promise<void> {
            this.isSubmitting = true;
            this.errors = {};
            this.errorMessage = '';
            this.plaintextToken = '';
            this.copied = false;

            const body: Record<string, unknown> = {
                name: this.form.name,
            };

            if (this.selectedScope && this.selectedScope !== 'custom') {
                body.scope = this.selectedScope;
            } else {
                body.abilities = this.form.abilities;
            }

            try {
                const response = await fetch(config.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': readCsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });

                const json = await response.json();

                if (!response.ok) {
                    this.errors = json.errors ?? {};
                    this.errorMessage = json.message ?? 'Failed to create token.';
                    return;
                }

                this.plaintextToken = json.data.plaintext_token;
                this.tokens.unshift(json.data.token);
                this.form.name = '';
            } catch {
                this.errorMessage = 'An unexpected error occurred.';
            } finally {
                this.isSubmitting = false;
            }
        },

        /**
         * Copies the plaintext token to the clipboard.
         */
        async copyToken(this: { plaintextToken: string; copied: boolean }): Promise<void> {
            try {
                await navigator.clipboard.writeText(this.plaintextToken);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
            } catch {
                /* Clipboard API may not be available */
            }
        },

        /**
         * Shows a confirmation dialog for revoking a specific token.
         */
        confirmRevoke(this: {
            confirmDialog: ConfirmDialogState;
            revokeToken: (id: number) => Promise<void>;
        }, id: number, name: string): void {
            this.confirmDialog = {
                show: true,
                title: 'Revoke token',
                message: `Are you sure you want to revoke "${name}"? This action cannot be undone.`,
                onConfirm: () => this.revokeToken(id),
            };
        },

        /**
         * Shows a confirmation dialog for revoking all tokens.
         */
        confirmRevokeAll(this: {
            confirmDialog: ConfirmDialogState;
            revokeAllTokens: () => Promise<void>;
        }): void {
            this.confirmDialog = {
                show: true,
                title: 'Revoke all tokens',
                message: 'Are you sure you want to revoke all tokens? This action cannot be undone.',
                onConfirm: () => this.revokeAllTokens(),
            };
        },

        /**
         * Revokes a specific token via DELETE request.
         */
        async revokeToken(this: { tokens: TokenData[]; errorMessage: string }, id: number): Promise<void> {
            const url = config.destroyUrl.replace('__ID__', String(id));

            try {
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': readCsrfToken(),
                    },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    this.tokens = this.tokens.filter((t: TokenData) => t.id !== id);
                }
            } catch {
                this.errorMessage = 'Failed to revoke token.';
            }
        },

        /**
         * Revokes all tokens via DELETE request.
         */
        async revokeAllTokens(this: { tokens: TokenData[]; errorMessage: string }): Promise<void> {
            try {
                const response = await fetch(config.destroyAllUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': readCsrfToken(),
                    },
                    credentials: 'same-origin',
                });

                if (response.ok) {
                    this.tokens = [];
                }
            } catch {
                this.errorMessage = 'Failed to revoke all tokens.';
            }
        },

        /**
         * Derives a human-readable scope description from a token's abilities.
         */
        getScopeDescription(abilities: string[]): string {
            const sorted = [...abilities].sort();

            for (const [scope, scopeAbilities] of Object.entries(config.scopeAbilityMap)) {
                const scopeSorted = [...scopeAbilities].sort();

                if (sorted.length === scopeSorted.length && sorted.every((a, i) => a === scopeSorted[i])) {
                    return scope.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
                }
            }

            return `${abilities.length} ${abilities.length === 1 ? 'ability' : 'abilities'}`;
        },

        /**
         * Formats an ISO date string to a human-readable locale date.
         */
        formatDate(dateString: string): string {
            const date = new Date(dateString);

            return date.toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    };
}

export { apiTokenManager };
export type { ApiTokenManagerConfig, TokenData };
