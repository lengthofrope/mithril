<?php

declare(strict_types=1);

use App\Services\Transcription\UnifiedSpeechTranscriptionService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->audioFile = tempnam(sys_get_temp_dir(), 'test_audio_') . '.wav';
    file_put_contents($this->audioFile, 'fake audio content');
});

afterEach(function (): void {
    if (file_exists($this->audioFile)) {
        unlink($this->audioFile);
    }
});

it('sends X-Speech-Token header when auth token is provided', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => Http::response(['text' => 'Hello world.']),
    ]);

    $service = new UnifiedSpeechTranscriptionService(
        baseUrl: 'http://localhost:8090',
        authToken: 'my-secret-token',
    );

    $service->transcribe($this->audioFile, 'en');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Speech-Token', 'my-secret-token');
    });
});

it('does not send X-Speech-Token header when auth token is empty', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => Http::response(['text' => 'Hello world.']),
    ]);

    $service = new UnifiedSpeechTranscriptionService(
        baseUrl: 'http://localhost:8090',
        authToken: '',
    );

    $service->transcribe($this->audioFile, 'en');

    Http::assertSent(function ($request) {
        return !$request->hasHeader('X-Speech-Token');
    });
});

it('does not send X-Speech-Token header when auth token is not provided', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => Http::response(['text' => 'Hello world.']),
    ]);

    $service = new UnifiedSpeechTranscriptionService(
        baseUrl: 'http://localhost:8090',
    );

    $service->transcribe($this->audioFile, 'en');

    Http::assertSent(function ($request) {
        return !$request->hasHeader('X-Speech-Token');
    });
});
