<?php

declare(strict_types=1);

use App\Services\Diarization\DiarizedSegment;
use App\Services\Diarization\DiarizationResult;
use App\Services\Diarization\PyAnnoteDiarizationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

describe('PyAnnoteDiarizationService', function (): void {
    it('returns a DiarizationResult with segments and speakers on success', function (): void {
        Http::fake([
            'http://localhost:8081/diarize' => Http::response([
                'segments' => [
                    ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 3.5, 'text' => 'Hello there.'],
                    ['speaker' => 'SPEAKER_01', 'start' => 3.5, 'end' => 7.0, 'text' => 'Good to meet you.'],
                ],
                'speakers' => ['SPEAKER_00', 'SPEAKER_01'],
            ], 200),
        ]);

        $service = new PyAnnoteDiarizationService(
            baseUrl: 'http://localhost:8081',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $result = $service->diarize($path, 'en');

        expect($result)->toBeInstanceOf(DiarizationResult::class)
            ->and($result->speakers)->toBe(['SPEAKER_00', 'SPEAKER_01'])
            ->and($result->segments)->toHaveCount(2)
            ->and($result->segments[0])->toBeInstanceOf(DiarizedSegment::class)
            ->and($result->segments[0]->speaker)->toBe('SPEAKER_00')
            ->and($result->segments[0]->start)->toBe(0.0)
            ->and($result->segments[0]->end)->toBe(3.5)
            ->and($result->segments[0]->text)->toBe('Hello there.')
            ->and($result->segments[1]->speaker)->toBe('SPEAKER_01')
            ->and($result->segments[1]->text)->toBe('Good to meet you.');

        @unlink($path);
    });

    it('throws when the audio file does not exist', function (): void {
        $service = new PyAnnoteDiarizationService(
            baseUrl: 'http://localhost:8081',
        );

        $service->diarize('/nonexistent/audio.wav', 'nl');
    })->throws(\RuntimeException::class, 'Audio file not found');

    it('throws when the server connection fails', function (): void {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $service = new PyAnnoteDiarizationService(
            baseUrl: 'http://localhost:8081',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        try {
            $service->diarize($path, 'nl');
        } finally {
            @unlink($path);
        }
    })->throws(\RuntimeException::class, 'Pyannote server connection failed');

    it('throws when the server returns a non-successful HTTP response', function (): void {
        Http::fake([
            'http://localhost:8081/diarize' => Http::response('Internal Server Error', 500),
        ]);

        $service = new PyAnnoteDiarizationService(
            baseUrl: 'http://localhost:8081',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        try {
            $service->diarize($path, 'nl');
        } finally {
            @unlink($path);
        }
    })->throws(\RuntimeException::class, 'Pyannote server error (500)');

    it('throws when the response contains empty segments', function (): void {
        Http::fake([
            'http://localhost:8081/diarize' => Http::response([
                'segments' => [],
                'speakers' => [],
            ], 200),
        ]);

        $service = new PyAnnoteDiarizationService(
            baseUrl: 'http://localhost:8081',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        try {
            $service->diarize($path, 'nl');
        } finally {
            @unlink($path);
        }
    })->throws(\RuntimeException::class, 'no diarization segments');

    it('sends a multipart request with the audio file and language', function (): void {
        Http::fake([
            'http://localhost:8081/diarize' => Http::response([
                'segments' => [
                    ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 2.0, 'text' => 'Test.'],
                ],
                'speakers' => ['SPEAKER_00'],
            ], 200),
        ]);

        $service = new PyAnnoteDiarizationService(
            baseUrl: 'http://localhost:8081',
        );

        $path = tempnam(sys_get_temp_dir(), 'audio');
        file_put_contents($path, 'fake audio content');

        $service->diarize($path, 'nl');

        Http::assertSent(function ($request) use ($path) {
            return str_contains($request->url(), '/diarize')
                && $request->hasFile('file')
                && str_contains($request->body(), 'language')
                && str_contains($request->body(), 'nl');
        });

        @unlink($path);
    });
});
