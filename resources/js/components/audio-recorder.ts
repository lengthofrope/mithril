/**
 * Supported recording states for the audio recorder.
 */
type RecorderState = 'idle' | 'recording' | 'paused' | 'uploading' | 'done' | 'error';

interface AudioRecorderConfig {
    meetingId: number;
    uploadEndpoint: string;
    csrfToken: string;
}

interface AudioRecorderState {
    state: RecorderState;
    elapsedSeconds: number;
    errorMessage: string;
    mediaRecorder: MediaRecorder | null;
    audioChunks: Blob[];
    timerInterval: ReturnType<typeof setInterval> | null;
    stream: MediaStream | null;

    init(): void;
    startRecording(): Promise<void>;
    pauseRecording(): void;
    resumeRecording(): void;
    stopRecording(): Promise<void>;
    uploadRecording(blob: Blob): Promise<void>;
    cleanup(): void;
    formattedTime: string;
}

/**
 * Alpine.js component for in-browser audio recording via MediaRecorder API.
 *
 * Provides start/stop/pause controls, a live timer, and uploads the recorded
 * audio blob to the server upon stop.
 */
function audioRecorder(config: AudioRecorderConfig): Record<string, unknown> {
    return {
        state: 'idle' as RecorderState,
        elapsedSeconds: 0,
        errorMessage: '',
        mediaRecorder: null as MediaRecorder | null,
        audioChunks: [] as Blob[],
        timerInterval: null as ReturnType<typeof setInterval> | null,
        stream: null as MediaStream | null,

        /**
         * Initialize the component.
         */
        init(this: AudioRecorderState): void {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.state = 'error';
                this.errorMessage = 'Your browser does not support audio recording.';
            }
        },

        /**
         * Request microphone access and start recording.
         */
        async startRecording(this: AudioRecorderState): Promise<void> {
            try {
                this.errorMessage = '';
                this.audioChunks = [];
                this.elapsedSeconds = 0;

                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });

                const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                    ? 'audio/webm;codecs=opus'
                    : 'audio/webm';

                this.mediaRecorder = new MediaRecorder(this.stream, { mimeType });

                this.mediaRecorder.ondataavailable = (event: BlobEvent) => {
                    if (event.data.size > 0) {
                        this.audioChunks.push(event.data);
                    }
                };

                this.mediaRecorder.onstop = async () => {
                    const blob = new Blob(this.audioChunks, { type: mimeType });
                    await this.uploadRecording(blob);
                };

                this.mediaRecorder.start(1000);
                this.state = 'recording';

                this.timerInterval = setInterval(() => {
                    this.elapsedSeconds++;
                }, 1000);
            } catch {
                this.state = 'error';
                this.errorMessage = 'Microphone access denied. Please allow microphone access and try again.';
            }
        },

        /**
         * Pause the current recording.
         */
        pauseRecording(this: AudioRecorderState): void {
            if (this.mediaRecorder?.state === 'recording') {
                this.mediaRecorder.pause();
                this.state = 'paused';

                if (this.timerInterval) {
                    clearInterval(this.timerInterval);
                    this.timerInterval = null;
                }
            }
        },

        /**
         * Resume a paused recording.
         */
        resumeRecording(this: AudioRecorderState): void {
            if (this.mediaRecorder?.state === 'paused') {
                this.mediaRecorder.resume();
                this.state = 'recording';

                this.timerInterval = setInterval(() => {
                    this.elapsedSeconds++;
                }, 1000);
            }
        },

        /**
         * Stop recording and trigger upload.
         */
        async stopRecording(this: AudioRecorderState): Promise<void> {
            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
            }

            this.cleanup();
        },

        /**
         * Upload the recorded audio blob to the server.
         */
        async uploadRecording(this: AudioRecorderState, blob: Blob): Promise<void> {
            this.state = 'uploading';

            const formData = new FormData();
            formData.append('audio', blob, `recording-${Date.now()}.webm`);
            formData.append('duration_seconds', String(this.elapsedSeconds));

            try {
                const response = await fetch(config.uploadEndpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    this.state = 'done';
                    window.location.reload();
                } else {
                    const data = await response.json();
                    this.state = 'error';
                    this.errorMessage = data.message ?? 'Upload failed. Please try again.';
                }
            } catch {
                this.state = 'error';
                this.errorMessage = 'Network error. Please try again.';
            }
        },

        /**
         * Stop media tracks and clear the timer.
         */
        cleanup(this: AudioRecorderState): void {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }

            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
        },

        /**
         * Format elapsed seconds as MM:SS.
         */
        get formattedTime(): string {
            const self = this as unknown as AudioRecorderState;
            const minutes = Math.floor(self.elapsedSeconds / 60);
            const seconds = self.elapsedSeconds % 60;
            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        },
    };
}

export { audioRecorder };
