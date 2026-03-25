<?php

declare(strict_types=1);

use App\Services\Transcription\UnifiedSpeechTranscriptionService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = new UnifiedSpeechTranscriptionService(
        baseUrl: 'http://localhost:8090',
    );
    $this->audioFile = tempnam(sys_get_temp_dir(), 'test_audio_') . '.wav';
    file_put_contents($this->audioFile, 'fake audio content');
});

afterEach(function (): void {
    if (file_exists($this->audioFile)) {
        unlink($this->audioFile);
    }
});

it('sends audio to /transcribe and returns text', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => Http::response([
            'text' => 'Hello world, this is a test.',
        ]),
    ]);

    $result = $this->service->transcribe($this->audioFile, 'en');

    expect($result)->toBe('Hello world, this is a test.');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/transcribe')
            && $request->hasFile('file');
    });
});

it('passes the language parameter', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => Http::response(['text' => 'Hallo wereld.']),
    ]);

    $this->service->transcribe($this->audioFile, 'nl');

    Http::assertSent(function ($request) {
        return str_contains($request->body(), 'language')
            && str_contains($request->body(), 'nl');
    });
});

it('throws RuntimeException on server error', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => Http::response('Internal Server Error', 500),
    ]);

    $this->service->transcribe($this->audioFile, 'en');
})->throws(\RuntimeException::class);

it('throws RuntimeException on empty response', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => Http::response(['text' => '']),
    ]);

    $this->service->transcribe($this->audioFile, 'en');
})->throws(\RuntimeException::class);

it('throws RuntimeException when file does not exist', function (): void {
    $this->service->transcribe('/nonexistent/audio.wav', 'en');
})->throws(\RuntimeException::class);

it('throws RuntimeException on connection failure', function (): void {
    Http::fake([
        'localhost:8090/transcribe' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused'),
    ]);

    $this->service->transcribe($this->audioFile, 'en');
})->throws(\RuntimeException::class);
