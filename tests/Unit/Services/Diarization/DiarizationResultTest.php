<?php

declare(strict_types=1);

use App\Services\Diarization\DiarizedSegment;
use App\Services\Diarization\DiarizationResult;

describe('DiarizedSegment', function (): void {
    describe('fromArray', function (): void {
        it('creates a segment with the correct property values', function (): void {
            $segment = DiarizedSegment::fromArray([
                'speaker' => 'SPEAKER_00',
                'start' => 1.5,
                'end' => 4.2,
                'text' => 'Hello, how are you?',
            ]);

            expect($segment->speaker)->toBe('SPEAKER_00')
                ->and($segment->start)->toBe(1.5)
                ->and($segment->end)->toBe(4.2)
                ->and($segment->text)->toBe('Hello, how are you?');
        });

        it('casts integer timestamps to float', function (): void {
            $segment = DiarizedSegment::fromArray([
                'speaker' => 'SPEAKER_01',
                'start' => 0,
                'end' => 10,
                'text' => 'Test',
            ]);

            expect($segment->start)->toBe(0.0)
                ->and($segment->end)->toBe(10.0);
        });

        it('defaults speaker to UNKNOWN when the key is missing', function (): void {
            $segment = DiarizedSegment::fromArray([
                'start' => 0.0,
                'end' => 1.0,
                'text' => 'No speaker',
            ]);

            expect($segment->speaker)->toBe('UNKNOWN');
        });

        it('defaults start and end to 0.0 when the keys are missing', function (): void {
            $segment = DiarizedSegment::fromArray([
                'speaker' => 'SPEAKER_00',
                'text' => 'No timestamps',
            ]);

            expect($segment->start)->toBe(0.0)
                ->and($segment->end)->toBe(0.0);
        });

        it('defaults text to an empty string when the key is missing', function (): void {
            $segment = DiarizedSegment::fromArray([
                'speaker' => 'SPEAKER_00',
                'start' => 0.0,
                'end' => 1.0,
            ]);

            expect($segment->text)->toBe('');
        });

        it('defaults all properties when given an empty array', function (): void {
            $segment = DiarizedSegment::fromArray([]);

            expect($segment->speaker)->toBe('UNKNOWN')
                ->and($segment->start)->toBe(0.0)
                ->and($segment->end)->toBe(0.0)
                ->and($segment->text)->toBe('');
        });
    });

    describe('toArray', function (): void {
        it('returns an array with all four keys', function (): void {
            $segment = new DiarizedSegment(
                speaker: 'SPEAKER_00',
                start: 1.5,
                end: 4.2,
                text: 'Hello, how are you?',
            );

            expect($segment->toArray())->toBe([
                'speaker' => 'SPEAKER_00',
                'start' => 1.5,
                'end' => 4.2,
                'text' => 'Hello, how are you?',
            ]);
        });

        it('preserves float precision for timestamps', function (): void {
            $segment = new DiarizedSegment(
                speaker: 'SPEAKER_01',
                start: 12.345,
                end: 67.891,
                text: 'Precise timestamps',
            );

            $array = $segment->toArray();

            expect($array['start'])->toBe(12.345)
                ->and($array['end'])->toBe(67.891);
        });
    });
});

describe('DiarizationResult', function (): void {
    describe('fromResponse', function (): void {
        it('parses segments and speakers from a service response', function (): void {
            $result = DiarizationResult::fromResponse([
                'segments' => [
                    ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 2.0, 'text' => 'Hello.'],
                    ['speaker' => 'SPEAKER_01', 'start' => 2.5, 'end' => 5.0, 'text' => 'Hi there.'],
                ],
                'speakers' => ['SPEAKER_00', 'SPEAKER_01'],
            ]);

            expect($result->segments)->toHaveCount(2)
                ->and($result->speakers)->toBe(['SPEAKER_00', 'SPEAKER_01']);
        });

        it('creates DiarizedSegment instances for each segment in the response', function (): void {
            $result = DiarizationResult::fromResponse([
                'segments' => [
                    ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 3.0, 'text' => 'First.'],
                ],
                'speakers' => ['SPEAKER_00'],
            ]);

            expect($result->segments[0])->toBeInstanceOf(DiarizedSegment::class)
                ->and($result->segments[0]->speaker)->toBe('SPEAKER_00')
                ->and($result->segments[0]->text)->toBe('First.');
        });

        it('handles a response with no segments or speakers', function (): void {
            $result = DiarizationResult::fromResponse([]);

            expect($result->segments)->toBe([])
                ->and($result->speakers)->toBe([]);
        });

        it('handles a response with an empty segments array', function (): void {
            $result = DiarizationResult::fromResponse([
                'segments' => [],
                'speakers' => [],
            ]);

            expect($result->segments)->toBe([])
                ->and($result->speakers)->toBe([]);
        });
    });

    describe('toJson', function (): void {
        it('serializes segments and speakers to a JSON string', function (): void {
            $result = new DiarizationResult(
                segments: [
                    new DiarizedSegment('SPEAKER_00', 0.0, 2.0, 'Hello.'),
                    new DiarizedSegment('SPEAKER_01', 2.5, 5.0, 'Hi there.'),
                ],
                speakers: ['SPEAKER_00', 'SPEAKER_01'],
            );

            $decoded = json_decode($result->toJson(), true);

            expect($decoded['segments'])->toHaveCount(2)
                ->and($decoded['speakers'])->toBe(['SPEAKER_00', 'SPEAKER_01'])
                ->and($decoded['segments'][0]['speaker'])->toBe('SPEAKER_00')
                ->and($decoded['segments'][0]['text'])->toBe('Hello.')
                ->and((float) $decoded['segments'][0]['start'])->toBe(0.0)
                ->and((float) $decoded['segments'][0]['end'])->toBe(2.0);
        });

        it('produces valid JSON for an empty result', function (): void {
            $result = new DiarizationResult(segments: [], speakers: []);

            $decoded = json_decode($result->toJson(), true);

            expect($decoded)->toBe(['segments' => [], 'speakers' => []]);
        });
    });

    describe('fromJson', function (): void {
        it('deserializes a JSON string into a DiarizationResult', function (): void {
            $json = json_encode([
                'segments' => [
                    ['speaker' => 'SPEAKER_00', 'start' => 0.0, 'end' => 2.0, 'text' => 'Hello.'],
                ],
                'speakers' => ['SPEAKER_00'],
            ]);

            $result = DiarizationResult::fromJson($json);

            expect($result)->toBeInstanceOf(DiarizationResult::class)
                ->and($result->segments)->toHaveCount(1)
                ->and($result->speakers)->toBe(['SPEAKER_00'])
                ->and($result->segments[0]->speaker)->toBe('SPEAKER_00')
                ->and($result->segments[0]->text)->toBe('Hello.');
        });
    });

    describe('toJson and fromJson round-trip', function (): void {
        it('restores an identical result after serialization and deserialization', function (): void {
            $original = new DiarizationResult(
                segments: [
                    new DiarizedSegment('SPEAKER_00', 0.0, 2.0, 'Hello.'),
                    new DiarizedSegment('SPEAKER_01', 2.5, 5.0, 'Hi there.'),
                    new DiarizedSegment('SPEAKER_00', 6.0, 9.3, 'How are you?'),
                ],
                speakers: ['SPEAKER_00', 'SPEAKER_01'],
            );

            $restored = DiarizationResult::fromJson($original->toJson());

            expect($restored->speakers)->toBe($original->speakers)
                ->and($restored->segments)->toHaveCount(count($original->segments));

            foreach ($original->segments as $i => $seg) {
                expect($restored->segments[$i]->speaker)->toBe($seg->speaker)
                    ->and($restored->segments[$i]->start)->toBe($seg->start)
                    ->and($restored->segments[$i]->end)->toBe($seg->end)
                    ->and($restored->segments[$i]->text)->toBe($seg->text);
            }
        });
    });

    describe('toFormattedText', function (): void {
        it('produces speaker-labeled text blocks for each segment', function (): void {
            $result = new DiarizationResult(
                segments: [
                    new DiarizedSegment('SPEAKER_00', 0.0, 2.0, 'Hello, how are you?'),
                    new DiarizedSegment('SPEAKER_01', 2.5, 5.0, "I'm fine, thanks."),
                ],
                speakers: ['SPEAKER_00', 'SPEAKER_01'],
            );

            $text = $result->toFormattedText();

            expect($text)->toContain('[SPEAKER_00]')
                ->and($text)->toContain('Hello, how are you?')
                ->and($text)->toContain('[SPEAKER_01]')
                ->and($text)->toContain("I'm fine, thanks.");
        });

        it('places the speaker label on the line before the segment text', function (): void {
            $result = new DiarizationResult(
                segments: [
                    new DiarizedSegment('SPEAKER_00', 0.0, 2.0, 'First utterance.'),
                ],
                speakers: ['SPEAKER_00'],
            );

            $lines = explode("\n", $result->toFormattedText());

            expect($lines[0])->toBe('[SPEAKER_00]')
                ->and($lines[1])->toBe('First utterance.');
        });

        it('separates consecutive segments with a blank line', function (): void {
            $result = new DiarizationResult(
                segments: [
                    new DiarizedSegment('SPEAKER_00', 0.0, 2.0, 'First.'),
                    new DiarizedSegment('SPEAKER_01', 2.5, 4.0, 'Second.'),
                ],
                speakers: ['SPEAKER_00', 'SPEAKER_01'],
            );

            $lines = explode("\n", $result->toFormattedText());

            expect($lines[2])->toBe('');
        });

        it('does not end with a trailing newline', function (): void {
            $result = new DiarizationResult(
                segments: [
                    new DiarizedSegment('SPEAKER_00', 0.0, 2.0, 'Hello.'),
                ],
                speakers: ['SPEAKER_00'],
            );

            expect($result->toFormattedText())->not->toEndWith("\n");
        });

        it('returns an empty string when there are no segments', function (): void {
            $result = new DiarizationResult(segments: [], speakers: []);

            expect($result->toFormattedText())->toBe('');
        });
    });
});
