interface DiarizedSegment {
    speaker: string;
    text: string;
    start?: number;
    end?: number;
}

interface TranscriptionViewerConfig {
    meetingId: number;
    csrfToken: string;
    status: string | null;
    content: string;
    errorMessage: string;
    diarizationStatus: string | null;
    diarizedContent: string;
    diarizationError: string;
    diarizationEnabled: boolean;
    processingStartedAt: string | null;
    estimatedDurationSeconds: number | null;
    diarizationStartedAt: string | null;
    estimatedDiarizationSeconds: number | null;
    transcriptionEnabled: boolean;
    canChooseMode: boolean;
    hasRecordings: boolean;
    provider: string | null;
}

interface TranscriptionViewerState {
    status: string | null;
    content: string;
    errorMessage: string;
    provider: string | null;
    diarizationStatus: string | null;
    diarizedContent: string;
    diarizationError: string;
    diarizationEnabled: boolean;
    processingStartedAt: string | null;
    estimatedDurationSeconds: number | null;
    elapsedTimer: ReturnType<typeof setInterval> | null;
    elapsedSeconds: number;
    diarizationStartedAt: string | null;
    estimatedDiarizationSeconds: number | null;
    diarizationElapsedTimer: ReturnType<typeof setInterval> | null;
    diarizationElapsedSeconds: number;
    transcriptionEnabled: boolean;
    canChooseMode: boolean;
    hasRecordings: boolean;
    showDeletePrompt: boolean;
    showDeleteModal: boolean;
    showManualConfirmModal: boolean;
    showRetranscribeModal: boolean;
    showDeleteTranscriptionModal: boolean;
    processingMode: 'transcribe' | 'diarize';
    showManualInput: boolean;
    manualContent: string;
    polling: boolean;
    speakerColors: string[];

    init(): void;
    refreshData(): Promise<void>;
    startPolling(): Promise<void>;
    startElapsedTimer(): void;
    stopElapsedTimer(): void;
    startDiarizationTimer(): void;
    stopDiarizationTimer(): void;
    resetTimers(): void;
    applyData(data: Record<string, unknown>): void;
    retry(): Promise<void>;
    retranscribeAll(): Promise<void>;
    saveManual(): Promise<void>;
    deleteTranscription(): Promise<void>;
    deleteRecordings(): Promise<void>;
    isManual: boolean;

    segments: DiarizedSegment[];
    hasDiarization: boolean;
    isDiarizing: boolean;
    shouldPoll: boolean;
    totalPhases: number;
    currentPhase: number | null;
    currentPhaseLabel: string;
    currentElapsedSeconds: number;
    currentEstimatedSeconds: number | null;
    currentStartedAt: string | null;
    isProcessing: boolean;
    totalEstimatedSeconds: number | null;
    totalElapsedSeconds: number;
    totalRemainingSeconds: number | null;
    overallProgressPercent: number | null;
}

/**
 * Alpine.js component for transcription display with processing progress.
 *
 * Handles status polling, elapsed timers, progress estimation, speaker
 * diarization display, and manual transcription input.
 */
function transcriptionViewer(config: TranscriptionViewerConfig): Record<string, unknown> {
    const baseUrl = `/api/v1/meetings/${config.meetingId}/transcription`;

    return {
        status: config.status,
        content: config.content,
        errorMessage: config.errorMessage,
        provider: config.provider,
        diarizationStatus: config.diarizationStatus,
        diarizedContent: config.diarizedContent,
        diarizationError: config.diarizationError,
        diarizationEnabled: config.diarizationEnabled,
        processingStartedAt: config.processingStartedAt,
        estimatedDurationSeconds: config.estimatedDurationSeconds,
        elapsedTimer: null as ReturnType<typeof setInterval> | null,
        elapsedSeconds: 0,
        diarizationStartedAt: config.diarizationStartedAt,
        estimatedDiarizationSeconds: config.estimatedDiarizationSeconds,
        diarizationElapsedTimer: null as ReturnType<typeof setInterval> | null,
        diarizationElapsedSeconds: 0,
        transcriptionEnabled: config.transcriptionEnabled,
        canChooseMode: config.canChooseMode,
        hasRecordings: config.hasRecordings,
        showDeletePrompt: false,
        showDeleteModal: false,
        showManualConfirmModal: false,
        showRetranscribeModal: false,
        showDeleteTranscriptionModal: false,
        processingMode: (config.canChooseMode ? 'diarize' : (config.diarizationEnabled ? 'diarize' : 'transcribe')) as 'transcribe' | 'diarize',
        showManualInput: !config.transcriptionEnabled,
        manualContent: '',
        polling: false,

        speakerColors: [
            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
        ],

        /**
         * Parse diarized content into speaker segments.
         */
        get segments(): DiarizedSegment[] {
            const self = this as unknown as TranscriptionViewerState;
            if (!self.diarizedContent) return [];
            try {
                const parsed = typeof self.diarizedContent === 'string'
                    ? JSON.parse(self.diarizedContent)
                    : self.diarizedContent;
                return parsed.segments ?? [];
            } catch { return []; }
        },

        /**
         * Whether completed diarization data is available.
         */
        /**
         * Whether the current transcription is manual input.
         */
        get isManual(): boolean {
            const self = this as unknown as TranscriptionViewerState;
            return self.provider === 'manual';
        },

        get hasDiarization(): boolean {
            const self = this as unknown as TranscriptionViewerState;
            return self.diarizationStatus === 'completed' && self.segments.length > 0;
        },

        /**
         * Whether diarization is currently in progress.
         */
        get isDiarizing(): boolean {
            const self = this as unknown as TranscriptionViewerState;
            return self.diarizationEnabled
                && (self.diarizationStatus === 'pending' || self.diarizationStatus === 'processing');
        },

        /**
         * Whether polling should be active.
         */
        get shouldPoll(): boolean {
            const self = this as unknown as TranscriptionViewerState;
            return self.status === 'pending'
                || self.status === 'processing'
                || self.isDiarizing;
        },

        /**
         * Get CSS classes for a speaker label.
         */
        speakerColor(this: TranscriptionViewerState, speaker: string): string {
            const speakers = [...new Set(this.segments.map(s => s.speaker))];
            const index = speakers.indexOf(speaker);
            return this.speakerColors[index % this.speakerColors.length];
        },

        /**
         * Get display label for a speaker.
         */
        speakerLabel(this: TranscriptionViewerState, speaker: string): string {
            const speakers = [...new Set(this.segments.map(s => s.speaker))];
            return 'Speaker ' + (speakers.indexOf(speaker) + 1);
        },

        /**
         * Format seconds as m:ss timestamp.
         */
        formatTime(seconds: number): string {
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);
            return m + ':' + String(s).padStart(2, '0');
        },

        /**
         * Format seconds as human-readable duration.
         */
        formatDuration(totalSeconds: number): string {
            const m = Math.floor(totalSeconds / 60);
            const s = totalSeconds % 60;
            if (m === 0) return s + 's';
            return m + 'm ' + s + 's';
        },

        /**
         * Total number of processing phases.
         */
        get totalPhases(): number {
            return 1;
        },

        /**
         * Current active phase number, or null if not processing.
         */
        get currentPhase(): number | null {
            const self = this as unknown as TranscriptionViewerState;
            if (self.status === 'pending' || self.status === 'processing') return 1;
            if (self.isDiarizing) return 2;
            return null;
        },

        /**
         * Human-readable label for the current phase.
         */
        get currentPhaseLabel(): string {
            const self = this as unknown as TranscriptionViewerState;
            if (self.status === 'pending') return 'Waiting to start…';
            if (self.status === 'processing' && self.processingMode === 'diarize') return 'Transcribing & identifying speakers…';
            if (self.status === 'processing') return 'Transcribing audio…';
            if (self.isDiarizing) return 'Transcribing & identifying speakers…';
            return '';
        },

        /**
         * Elapsed seconds for the current phase.
         */
        get currentElapsedSeconds(): number {
            const self = this as unknown as TranscriptionViewerState;
            return self.currentPhase === 2 ? self.diarizationElapsedSeconds : self.elapsedSeconds;
        },

        /**
         * Estimated seconds for the current phase.
         */
        get currentEstimatedSeconds(): number | null {
            const self = this as unknown as TranscriptionViewerState;
            return self.currentPhase === 2 ? self.estimatedDiarizationSeconds : self.estimatedDurationSeconds;
        },

        /**
         * Started-at timestamp for the current phase.
         */
        get currentStartedAt(): string | null {
            const self = this as unknown as TranscriptionViewerState;
            return self.currentPhase === 2 ? self.diarizationStartedAt : self.processingStartedAt;
        },

        /**
         * Whether any processing is active.
         */
        get isProcessing(): boolean {
            const self = this as unknown as TranscriptionViewerState;
            return self.currentPhase !== null;
        },

        /**
         * Total estimated seconds across all phases.
         */
        get totalEstimatedSeconds(): number | null {
            const self = this as unknown as TranscriptionViewerState;
            const transcription = self.estimatedDurationSeconds || 0;
            const diarization = self.diarizationEnabled ? (self.estimatedDiarizationSeconds || 0) : 0;
            return transcription + diarization || null;
        },

        /**
         * Total elapsed seconds across all phases.
         */
        get totalElapsedSeconds(): number {
            const self = this as unknown as TranscriptionViewerState;
            if (self.currentPhase === 1) {
                return self.elapsedSeconds;
            }
            if (self.currentPhase === 2) {
                const phase1 = self.estimatedDurationSeconds || self.elapsedSeconds;
                return phase1 + self.diarizationElapsedSeconds;
            }
            return 0;
        },

        /**
         * Total remaining seconds across all phases.
         */
        get totalRemainingSeconds(): number | null {
            const self = this as unknown as TranscriptionViewerState;
            if (!self.totalEstimatedSeconds) return null;
            return Math.max(0, self.totalEstimatedSeconds - self.totalElapsedSeconds);
        },

        /**
         * Overall progress percentage across all phases.
         */
        get overallProgressPercent(): number | null {
            const self = this as unknown as TranscriptionViewerState;
            if (!self.totalEstimatedSeconds) return null;
            return Math.min(95, Math.round((self.totalElapsedSeconds / self.totalEstimatedSeconds) * 100));
        },

        /**
         * Start the transcription elapsed timer.
         */
        startElapsedTimer(this: TranscriptionViewerState): void {
            this.stopElapsedTimer();
            if (!this.processingStartedAt) return;
            const started = new Date(this.processingStartedAt).getTime();
            this.elapsedSeconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
            this.elapsedTimer = setInterval(() => {
                this.elapsedSeconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
            }, 1000);
        },

        /**
         * Stop the transcription elapsed timer.
         */
        stopElapsedTimer(this: TranscriptionViewerState): void {
            if (this.elapsedTimer) {
                clearInterval(this.elapsedTimer);
                this.elapsedTimer = null;
            }
        },

        /**
         * Start the diarization elapsed timer.
         */
        startDiarizationTimer(this: TranscriptionViewerState): void {
            this.stopDiarizationTimer();
            if (!this.diarizationStartedAt) return;
            const started = new Date(this.diarizationStartedAt).getTime();
            this.diarizationElapsedSeconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
            this.diarizationElapsedTimer = setInterval(() => {
                this.diarizationElapsedSeconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
            }, 1000);
        },

        /**
         * Stop the diarization elapsed timer.
         */
        stopDiarizationTimer(this: TranscriptionViewerState): void {
            if (this.diarizationElapsedTimer) {
                clearInterval(this.diarizationElapsedTimer);
                this.diarizationElapsedTimer = null;
            }
        },

        /**
         * Reset all timing state for a fresh run.
         */
        resetTimers(this: TranscriptionViewerState): void {
            this.processingStartedAt = null;
            this.estimatedDurationSeconds = null;
            this.diarizationStartedAt = null;
            this.estimatedDiarizationSeconds = null;
            this.stopElapsedTimer();
            this.stopDiarizationTimer();
        },

        /**
         * Apply API response data to component state.
         */
        applyData(this: TranscriptionViewerState, data: Record<string, unknown>): void {
            const newStatus = data.status as string | null;
            this.status = (newStatus === null && this.status === 'pending') ? 'pending' : newStatus;
            this.content = (data.content as string) ?? '';
            this.errorMessage = (data.error_message as string) ?? '';
            this.diarizationStatus = data.diarization_status as string | null;
            this.diarizedContent = (data.diarized_content as string) ?? '';
            this.diarizationError = (data.diarization_error as string) ?? '';
            this.processingStartedAt = data.processing_started_at as string | null;
            this.estimatedDurationSeconds = data.estimated_duration_seconds as number | null;
            this.diarizationStartedAt = data.diarization_started_at as string | null;
            this.estimatedDiarizationSeconds = data.estimated_diarization_seconds as number | null;
            this.provider = (data.provider as string) ?? null;
        },

        /**
         * Initialize the component.
         */
        init(this: TranscriptionViewerState): void {
            if (this.shouldPoll) {
                this.startPolling();
            }
            if (this.status === 'processing') {
                this.startElapsedTimer();
            }
            if (this.isDiarizing) {
                this.startDiarizationTimer();
            }
        },

        /**
         * Fetch fresh transcription data from the API.
         */
        async refreshData(this: TranscriptionViewerState): Promise<void> {
            try {
                const response = await fetch(baseUrl, {
                    headers: { 'Accept': 'application/json' },
                });
                if (response.ok) {
                    const json = await response.json();
                    this.applyData(json.data ?? {});

                    if (this.status === 'processing') {
                        this.startElapsedTimer();
                    } else {
                        this.stopElapsedTimer();
                    }

                    if (this.isDiarizing) {
                        this.startDiarizationTimer();
                    } else {
                        this.stopDiarizationTimer();
                    }

                    if (this.shouldPoll && !this.polling) {
                        this.startPolling();
                    }
                }
            } catch { /* silent */ }
        },

        /**
         * Poll the API until processing completes.
         */
        async startPolling(this: TranscriptionViewerState): Promise<void> {
            this.polling = true;
            while (this.polling && this.shouldPoll) {
                await new Promise(r => setTimeout(r, 3000));
                try {
                    const response = await fetch(baseUrl, {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (response.ok) {
                        const json = await response.json();
                        const prev = this.status;
                        const prevDiarizationStatus = this.diarizationStatus;
                        this.applyData(json.data ?? {});

                        if (this.status === 'processing' && prev !== 'processing') {
                            this.startElapsedTimer();
                        } else if (this.status !== 'processing') {
                            this.stopElapsedTimer();
                        }

                        const prevDiarizing = prevDiarizationStatus === 'pending' || prevDiarizationStatus === 'processing';
                        if (this.isDiarizing && !prevDiarizing) {
                            this.startDiarizationTimer();
                        } else if (!this.isDiarizing) {
                            this.stopDiarizationTimer();
                        }

                        if (!this.shouldPoll) {
                            this.polling = false;

                            if (this.status === 'completed' && prev !== 'completed' && this.hasRecordings) {
                                this.showDeletePrompt = true;
                            }
                        }
                    }
                } catch { /* continue polling */ }
            }
        },

        /**
         * Retry a failed transcription.
         */
        async retry(this: TranscriptionViewerState): Promise<void> {
            const response = await fetch(`${baseUrl}/retry`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mode: this.processingMode }),
            });
            if (response.ok) {
                this.status = 'pending';
                this.errorMessage = '';
                this.diarizationStatus = null;
                this.diarizedContent = '';
                this.resetTimers();
                this.startPolling();
            }
        },

        /**
         * Retranscribe all recordings from scratch.
         */
        async retranscribeAll(this: TranscriptionViewerState): Promise<void> {
            const response = await fetch(`${baseUrl}/retranscribe`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mode: this.processingMode }),
            });
            if (response.ok) {
                this.status = 'pending';
                this.content = '';
                this.errorMessage = '';
                this.diarizationStatus = null;
                this.diarizedContent = '';
                this.resetTimers();
                this.startPolling();
            }
        },

        /**
         * Save manually entered transcription content.
         */
        async saveManual(this: TranscriptionViewerState): Promise<void> {
            const response = await fetch(`${baseUrl}/manual`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ content: this.manualContent }),
            });
            if (response.ok) {
                window.location.reload();
            }
        },

        /**
         * Delete the manual transcription, resetting to empty state.
         */
        async deleteTranscription(this: TranscriptionViewerState): Promise<void> {
            const response = await fetch(`${baseUrl}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json',
                },
            });
            if (response.ok) {
                this.status = null;
                this.content = '';
                this.provider = null;
                this.diarizedContent = '';
                this.diarizationStatus = null;
                this.showManualInput = false;
            }
        },

        /**
         * Delete all recordings for this meeting.
         */
        async deleteRecordings(this: TranscriptionViewerState): Promise<void> {
            const recordingsUrl = `/api/v1/meetings/${config.meetingId}/recordings`;
            const response = await fetch(recordingsUrl, {
                headers: { 'Accept': 'application/json' },
            });

            if (!response.ok) return;

            const json = await response.json();
            const recordings = json.data ?? [];

            for (const recording of recordings) {
                await fetch(`${recordingsUrl}/${recording.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json',
                    },
                });
            }

            this.hasRecordings = false;
            this.showDeletePrompt = false;
        },
    };
}

export { transcriptionViewer };
