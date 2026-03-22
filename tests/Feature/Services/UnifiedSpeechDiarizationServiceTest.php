<?php

declare(strict_types=1);

use App\Services\Diarization\DiarizationResult;
use App\Services\Diarization\DiarizedSegment;
use App\Services\Diarization\UnifiedSpeechDiarizationService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = new UnifiedSpeechDiarizationService(
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

it('sends audio to /diarize and returns DiarizationResult', function (): void {
    Http::fake([
        'localhost:8090/diarize' => Http::response([
            'segments' => [
                ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 5.0, 'text' => 'Hello there.'],
                ['speaker' => 'SPEAKER_01', 'start' => 5.0, 'end' => 10.0, 'text' => 'How are you?'],
            ],
            'speakers' => ['SPEAKER_00', 'SPEAKER_01'],
        ]),
    ]);

    $result = $this->service->diarize($this->audioFile, 'en');

    expect($result)
        ->toBeInstanceOf(DiarizationResult::class)
        ->and($result->segments)->toHaveCount(2)
        ->and($result->speakers)->toBe(['SPEAKER_00', 'SPEAKER_01']);
});

it('returns DiarizedSegment objects with correct fields', function (): void {
    Http::fake([
        'localhost:8090/diarize' => Http::response([
            'segments' => [
                ['speaker' => 'SPEAKER_00', 'start' => 1.5, 'end' => 3.2, 'text' => 'Test text.'],
            ],
            'speakers' => ['SPEAKER_00'],
        ]),
    ]);

    $result = $this->service->diarize($this->audioFile, 'en');
    $segment = $result->segments[0];

    expect($segment)
        ->toBeInstanceOf(DiarizedSegment::class)
        ->and($segment->speaker)->toBe('SPEAKER_00')
        ->and($segment->start)->toBe(1.5)
        ->and($segment->end)->toBe(3.2)
        ->and($segment->text)->toBe('Test text.');
});

it('uses DiarizationResult::fromResponse without modification', function (): void {
    $data = [
        'segments' => [
            ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 5.0, 'text' => 'Hello.'],
        ],
        'speakers' => ['SPEAKER_00'],
    ];

    $result = DiarizationResult::fromResponse($data);

    expect($result)->toBeInstanceOf(DiarizationResult::class)
        ->and($result->segments)->toHaveCount(1)
        ->and($result->segments[0]->speaker)->toBe('SPEAKER_00');
});

it('passes the language parameter', function (): void {
    Http::fake([
        'localhost:8090/diarize' => Http::response([
            'segments' => [
                ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 3.0, 'text' => 'Hallo.'],
            ],
            'speakers' => ['SPEAKER_00'],
        ]),
    ]);

    $this->service->diarize($this->audioFile, 'nl');

    Http::assertSent(function ($request) {
        return str_contains($request->body(), 'language')
            && str_contains($request->body(), 'nl');
    });
});

it('throws RuntimeException on server error', function (): void {
    Http::fake([
        'localhost:8090/diarize' => Http::response('Internal Server Error', 500),
    ]);

    $this->service->diarize($this->audioFile, 'en');
})->throws(\RuntimeException::class);

it('throws RuntimeException when file does not exist', function (): void {
    $this->service->diarize('/nonexistent/audio.wav', 'en');
})->throws(\RuntimeException::class);

it('throws RuntimeException on empty segments', function (): void {
    Http::fake([
        'localhost:8090/diarize' => Http::response([
            'segments' => [],
            'speakers' => [],
        ]),
    ]);

    $this->service->diarize($this->audioFile, 'en');
})->throws(\RuntimeException::class);
