interface Extraction {
    id: number;
    type: string;
    content: string;
    assignee_id: number | null;
    assignee?: { id: number; name: string } | null;
    priority: string | null;
    deadline: string | null;
    status: string;
}

interface SelectOption {
    value: number;
    label: string;
    team_id?: number;
}

interface ExtractionReviewConfig {
    meetingId: number;
    initialExtractions: Extraction[];
    hasTranscription: boolean;
    summary: string;
    csrfToken: string;
    teamOptions: SelectOption[];
    memberOptions: SelectOption[];
}

interface ExtractionReviewState {
    extractions: Extraction[];
    hasTranscription: boolean;
    summary: string;
    selectedIds: number[];
    loading: boolean;
    editingId: number | null;
    editContent: string;
    editAssigneeId: number | string;
    editPriority: string;
    editDeadline: string;
    editTeamId: number | string;
    showReExtractConfirm: boolean;
    teamOptions: SelectOption[];
    memberOptions: SelectOption[];

    init(): void;
    refreshData(): Promise<void>;
    accept(extraction: Extraction): Promise<void>;
    reject(extraction: Extraction): Promise<void>;
    startEdit(extraction: Extraction): void;
    cancelEdit(): void;
    acceptWithEdits(extraction: Extraction): Promise<void>;
    bulkAccept(): Promise<void>;
    bulkReject(): Promise<void>;
    reExtract(): Promise<void>;
    toggleSelection(id: number): void;
    selectAll(): void;
    deselectAll(): void;
    pendingExtractions: Extraction[];
    filteredMemberOptions: SelectOption[];
}

/**
 * Alpine.js component for reviewing AI-extracted meeting items.
 *
 * Provides accept/reject/modify per item, bulk actions, and re-extract functionality.
 */
function extractionReview(config: ExtractionReviewConfig): Record<string, unknown> {
    const baseUrl = `/api/v1/meetings/${config.meetingId}/extractions`;

    return {
        extractions: config.initialExtractions,
        hasTranscription: config.hasTranscription,
        summary: config.summary,
        selectedIds: [] as number[],
        loading: false,
        editingId: null as number | null,
        editContent: '',
        editAssigneeId: '' as number | string,
        editPriority: '',
        editDeadline: '',
        editTeamId: '' as number | string,
        showReExtractConfirm: false,
        teamOptions: config.teamOptions,
        memberOptions: config.memberOptions,

        /**
         * Initialize the component.
         */
        init(this: ExtractionReviewState): void {
            const el = (this as unknown as { $el: HTMLElement }).$el;
            el.closest('[x-data]')?.addEventListener('tab-activated', ((e: CustomEvent) => {
                if (e.detail?.tab === 'extractions') {
                    this.refreshData();
                }
            }) as EventListener);
        },

        /**
         * Refresh extractions and transcription status from the API.
         */
        async refreshData(this: ExtractionReviewState): Promise<void> {
            try {
                const response = await fetch(`${baseUrl}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    const json = await response.json();
                    const data = json.data ?? [];
                    this.extractions = data.map((e: Record<string, unknown>) => ({
                        id: e.id,
                        type: e.type,
                        content: e.content,
                        assignee_id: e.assignee_id,
                        assignee: e.assignee ?? null,
                        priority: e.priority,
                        deadline: e.deadline,
                        status: e.status,
                    }));
                }
            } catch { /* silent */ }

            try {
                const response = await fetch(`/api/v1/meetings/${config.meetingId}/transcription`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    const json = await response.json();
                    this.hasTranscription = json.data?.status === 'completed';
                }
            } catch { /* silent */ }
        },

        /**
         * Accept an extraction as-is.
         */
        async accept(this: ExtractionReviewState, extraction: Extraction): Promise<void> {
            this.loading = true;

            const response = await fetch(`${baseUrl}/${extraction.id}/accept`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: '{}',
            });

            if (response.ok) {
                extraction.status = 'accepted';
            }

            this.loading = false;
        },

        /**
         * Reject an extraction.
         */
        async reject(this: ExtractionReviewState, extraction: Extraction): Promise<void> {
            this.loading = true;

            const response = await fetch(`${baseUrl}/${extraction.id}/reject`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                extraction.status = 'rejected';
            }

            this.loading = false;
        },

        /**
         * Start editing an extraction before accepting.
         */
        startEdit(this: ExtractionReviewState, extraction: Extraction): void {
            this.editingId = extraction.id;
            this.editContent = extraction.content;
            this.editAssigneeId = extraction.assignee_id ?? '';
            this.editPriority = extraction.priority ?? '';
            this.editDeadline = extraction.deadline ?? '';

            const member = extraction.assignee_id
                ? this.memberOptions.find(m => m.value === extraction.assignee_id)
                : null;
            this.editTeamId = member?.team_id ?? '';
        },

        /**
         * Cancel editing.
         */
        cancelEdit(this: ExtractionReviewState): void {
            this.editingId = null;
        },

        /**
         * Accept an extraction with modified values.
         */
        async acceptWithEdits(this: ExtractionReviewState, extraction: Extraction): Promise<void> {
            this.loading = true;

            const body: Record<string, unknown> = {};
            if (this.editContent !== extraction.content) body.content = this.editContent;
            if (String(this.editAssigneeId) !== String(extraction.assignee_id ?? '')) {
                body.assignee_id = this.editAssigneeId === '' ? null : Number(this.editAssigneeId);
            }
            if (this.editPriority !== (extraction.priority ?? '')) body.priority = this.editPriority || null;
            if (this.editDeadline !== (extraction.deadline ?? '')) body.deadline = this.editDeadline || null;

            const response = await fetch(`${baseUrl}/${extraction.id}/accept`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(body),
            });

            if (response.ok) {
                extraction.status = Object.keys(body).length > 0 ? 'modified' : 'accepted';
                extraction.content = this.editContent;
                this.editingId = null;
            }

            this.loading = false;
        },

        /**
         * Bulk accept selected extractions.
         */
        async bulkAccept(this: ExtractionReviewState): Promise<void> {
            if (this.selectedIds.length === 0) return;
            this.loading = true;

            const response = await fetch(`${baseUrl}/bulk`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'accept', extraction_ids: this.selectedIds }),
            });

            if (response.ok) {
                this.extractions.forEach(e => {
                    if (this.selectedIds.includes(e.id)) e.status = 'accepted';
                });
                this.selectedIds = [];
            }

            this.loading = false;
        },

        /**
         * Bulk reject selected extractions.
         */
        async bulkReject(this: ExtractionReviewState): Promise<void> {
            if (this.selectedIds.length === 0) return;
            this.loading = true;

            const response = await fetch(`${baseUrl}/bulk`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'reject', extraction_ids: this.selectedIds }),
            });

            if (response.ok) {
                this.extractions.forEach(e => {
                    if (this.selectedIds.includes(e.id)) e.status = 'rejected';
                });
                this.selectedIds = [];
            }

            this.loading = false;
        },

        /**
         * Re-extract insights from the transcription.
         */
        async reExtract(this: ExtractionReviewState): Promise<void> {
            this.showReExtractConfirm = false;
            this.loading = true;

            const response = await fetch(`${baseUrl}/re-extract`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                window.location.reload();
            }

            this.loading = false;
        },

        /**
         * Toggle selection of an extraction.
         */
        toggleSelection(this: ExtractionReviewState, id: number): void {
            const index = this.selectedIds.indexOf(id);
            if (index === -1) {
                this.selectedIds.push(id);
            } else {
                this.selectedIds.splice(index, 1);
            }
        },

        /**
         * Select all pending extractions.
         */
        selectAll(this: ExtractionReviewState): void {
            this.selectedIds = this.pendingExtractions.map(e => e.id);
        },

        /**
         * Deselect all.
         */
        deselectAll(this: ExtractionReviewState): void {
            this.selectedIds = [];
        },

        /**
         * Get only pending extractions.
         */
        get pendingExtractions(): Extraction[] {
            const self = this as unknown as ExtractionReviewState;
            return self.extractions.filter(e => e.status === 'pending');
        },

        /**
         * Get member options filtered by the selected team.
         */
        get filteredMemberOptions(): SelectOption[] {
            const self = this as unknown as ExtractionReviewState;
            return self.editTeamId
                ? self.memberOptions.filter(m => String(m.team_id) === String(self.editTeamId))
                : self.memberOptions;
        },
    };
}

export { extractionReview };
