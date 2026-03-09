import { apiClient } from '../utils/api-client';
import type { Agreement } from '../types/models';
import type { ApiError } from '../types/api';

/**
 * Configuration for the agreementManager Alpine component.
 */
interface AgreementManagerConfig {
    teamMemberId: number;
    agreements: Agreement[];
}

/**
 * Shape of the new/edit agreement form data.
 */
interface AgreementFormData {
    description: string;
    agreed_date: string;
    follow_up_date: string;
}

/**
 * Alpine.js component for managing agreements on a team member profile page.
 * Supports inline add, edit, and delete via the agreements API.
 */
function agreementManager(config: AgreementManagerConfig): Record<string, unknown> {
    const API_BASE = '/api/v1/agreements';

    return {
        agreements: config.agreements as Agreement[],
        isAdding: false,
        editingId: null as number | null,
        isSubmitting: false,
        deleteConfirmId: null as number | null,
        form: {
            description: '',
            agreed_date: new Date().toISOString().slice(0, 10),
            follow_up_date: '',
        } as AgreementFormData,

        /**
         * Opens the add agreement form with default values.
         */
        openAddForm(this: { isAdding: boolean; editingId: number | null; resetForm: () => void }): void {
            this.editingId = null;
            this.resetForm();
            this.isAdding = true;
        },

        /**
         * Closes the add/edit form and resets state.
         */
        closeForm(this: { isAdding: boolean; editingId: number | null; resetForm: () => void }): void {
            this.isAdding = false;
            this.editingId = null;
            this.resetForm();
        },

        /**
         * Populates the form with an existing agreement's data for editing.
         */
        startEdit(this: {
            agreements: Agreement[];
            editingId: number | null;
            isAdding: boolean;
            form: AgreementFormData;
        }, id: number): void {
            const agreement = this.agreements.find((a: Agreement) => a.id === id);
            if (!agreement) {
                return;
            }

            this.isAdding = false;
            this.editingId = id;
            this.form = {
                description: agreement.description,
                agreed_date: agreement.agreed_date.slice(0, 10),
                follow_up_date: agreement.follow_up_date ? agreement.follow_up_date.slice(0, 10) : '',
            };
        },

        /**
         * Resets the form to its default empty state.
         */
        resetForm(this: { form: AgreementFormData }): void {
            this.form = {
                description: '',
                agreed_date: new Date().toISOString().slice(0, 10),
                follow_up_date: '',
            };
        },

        /**
         * Submits the form — creates a new agreement or updates an existing one.
         */
        async submitForm(this: {
            form: AgreementFormData;
            isSubmitting: boolean;
            editingId: number | null;
            agreements: Agreement[];
            isAdding: boolean;
            resetForm: () => void;
        }): Promise<void> {
            if (this.isSubmitting) {
                return;
            }

            this.isSubmitting = true;

            const payload: Record<string, unknown> = {
                team_member_id: config.teamMemberId,
                description: this.form.description,
                agreed_date: this.form.agreed_date,
                follow_up_date: this.form.follow_up_date || null,
            };

            try {
                if (this.editingId) {
                    const response = await apiClient.patch<Agreement>(
                        `${API_BASE}/${this.editingId}`,
                        payload,
                    );
                    const index = this.agreements.findIndex((a: Agreement) => a.id === this.editingId);
                    if (index !== -1) {
                        this.agreements[index] = response.data;
                    }
                    this.editingId = null;
                } else {
                    const response = await apiClient.post<Agreement>(API_BASE, payload);
                    this.agreements.unshift(response.data);
                    this.isAdding = false;
                }

                this.resetForm();
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[agreementManager] Save failed:', apiError.message);
            } finally {
                this.isSubmitting = false;
            }
        },

        /**
         * Deletes an agreement after confirmation.
         */
        async deleteAgreement(this: {
            agreements: Agreement[];
            deleteConfirmId: number | null;
        }, id: number): Promise<void> {
            try {
                await apiClient.delete(`${API_BASE}/${id}`);
                this.agreements = this.agreements.filter((a: Agreement) => a.id !== id);
            } catch (err) {
                const apiError = err as ApiError;
                console.error('[agreementManager] Delete failed:', apiError.message);
            } finally {
                this.deleteConfirmId = null;
            }
        },

        /**
         * Formats a date string to a human-readable format (dd MMM yyyy).
         */
        formatDate(dateString: string): string {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        },
    };
}

export { agreementManager };
export type { AgreementManagerConfig, AgreementFormData };
