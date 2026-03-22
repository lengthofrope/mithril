<?php

declare(strict_types=1);

use App\Services\Diarization\UnifiedSpeechDiarizationService;
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
        'localhost:8090/diarize' => Http::response([
            'segments' => [
                ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 3.0, 'text' => 'Hello.'],
            ],
            'speakers' => ['SPEAKER_00'],
        ]),
    ]);

    $service = new UnifiedSpeechDiarizationService(
        baseUrl: 'http://localhost:8090',
        authToken: 'my-secret-token',
    );

    $service->diarize($this->audioFile, 'en');

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Speech-Token', 'my-secret-token');
    });
});

it('does not send X-Speech-Token header when auth token is empty', function (): void {
    Http::fake([
        'localhost:8090/diarize' => Http::response([
            'segments' => [
                ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 3.0, 'text' => 'Hello.'],
            ],
            'speakers' => ['SPEAKER_00'],
        ]),
    ]);

    $service = new UnifiedSpeechDiarizationService(
        baseUrl: 'http://localhost:8090',
        authToken: '',
    );

    $service->diarize($this->audioFile, 'en');

    Http::assertSent(function ($request) {
        return !$request->hasHeader('X-Speech-Token');
    });
});

it('does not send X-Speech-Token header when auth token is not provided', function (): void {
    Http::fake([
        'localhost:8090/diarize' => Http::response([
            'segments' => [
                ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 3.0, 'text' => 'Hello.'],
            ],
            'speakers' => ['SPEAKER_00'],
        ]),
    ]);

    $service = new UnifiedSpeechDiarizationService(
        baseUrl: 'http://localhost:8090',
    );

    $service->diarize($this->audioFile, 'en');

    Http::assertSent(function ($request) {
        return !$request->hasHeader('X-Speech-Token');
    });
});
