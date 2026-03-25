<?php

declare(strict_types=1);

describe('Meeting feature flags', function (): void {
    it('has recording.enabled defaulting to true', function (): void {
        expect(config()->has('meetings.recording.enabled'))->toBeTrue();
    });

    it('has transcription.enabled defaulting to true', function (): void {
        expect(config()->has('meetings.transcription.enabled'))->toBeTrue();
    });

    it('has speech.server_enabled defaulting to true', function (): void {
        expect(config()->has('meetings.speech.server_enabled'))->toBeTrue();
    });

    it('has ai.enabled defaulting to true', function (): void {
        expect(config()->has('ai.enabled'))->toBeTrue();
    });
});
