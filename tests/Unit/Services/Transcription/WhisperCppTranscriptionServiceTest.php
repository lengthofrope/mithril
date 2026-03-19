<?php

declare(strict_types=1);

use App\Services\Transcription\WhisperCppTranscriptionService;
use Illuminate\Support\Facades\Http;

describe('WhisperCppTranscriptionService', function (): void {
    it('sends audio file to the whisper.cpp server inference endpoint', function (): void {
        Http::fake([
            'http://localhost:8080/inference' => Http::response('Hello world transcription'),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $result = $service->transcribe($path, 'nl');

        expect($result)->toBe('Hello world transcription');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/inference')
                && $request->hasFile('file');
        });

        @unlink($path);
    });

    it('sends the language parameter in the request', function (): void {
        Http::fake([
            'http://localhost:8080/inference' => Http::response('Transcribed'),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $service->transcribe($path, 'en');

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'language')
                && str_contains($request->body(), 'en');
        });

        @unlink($path);
    });

    it('requests json response format', function (): void {
        Http::fake([
            'http://localhost:8080/inference' => Http::response(json_encode(['text' => 'Hello'])),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $service->transcribe($path, 'nl');

        Http::assertSent(function ($request) {
            return str_contains($request->body(), 'response_format')
                && str_contains($request->body(), 'json');
        });

        @unlink($path);
    });

    it('extracts text from json response', function (): void {
        Http::fake([
            'http://localhost:8080/inference' => Http::response(json_encode(['text' => 'Parsed from JSON'])),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $result = $service->transcribe($path, 'nl');

        expect($result)->toBe('Parsed from JSON');

        @unlink($path);
    });

    it('falls back to raw body when json has no text key', function (): void {
        Http::fake([
            'http://localhost:8080/inference' => Http::response('Plain text response'),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $result = $service->transcribe($path, 'nl');

        expect($result)->toBe('Plain text response');

        @unlink($path);
    });

    it('throws when the audio file does not exist', function (): void {
        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $service->transcribe('/nonexistent/audio.webm', 'nl');
    })->throws(\RuntimeException::class, 'Audio file not found');

    it('throws when the server returns an error', function (): void {
        Http::fake([
            'http://localhost:8080/inference' => Http::response('Internal Server Error', 500),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        try {
            $service->transcribe($path, 'nl');
        } finally {
            @unlink($path);
        }
    })->throws(\RuntimeException::class);

    it('throws when the server returns empty transcription', function (): void {
        Http::fake([
            'http://localhost:8080/inference' => Http::response(''),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://localhost:8080',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        try {
            $service->transcribe($path, 'nl');
        } finally {
            @unlink($path);
        }
    })->throws(\RuntimeException::class, 'empty transcription');

    it('uses configurable base url', function (): void {
        Http::fake([
            'http://whisper.local:9000/inference' => Http::response('Custom host'),
        ]);

        $service = new WhisperCppTranscriptionService(
            baseUrl: 'http://whisper.local:9000',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $result = $service->transcribe($path, 'nl');

        expect($result)->toBe('Custom host');

        @unlink($path);
    });
});
