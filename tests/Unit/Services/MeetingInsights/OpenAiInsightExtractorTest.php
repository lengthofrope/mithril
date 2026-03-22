<?php

declare(strict_types=1);

use App\Services\MeetingInsights\AbstractInsightExtractor;
use App\Services\MeetingInsights\ExtractionItem;
use App\Services\MeetingInsights\ExtractionResult;
use App\Services\MeetingInsights\OpenAiInsightExtractor;
use Illuminate\Support\Facades\Http;

describe('OpenAiInsightExtractor', function (): void {
    describe('class structure', function (): void {
        it('implements MeetingInsightExtractorInterface', function (): void {
            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');

            expect($extractor)->toBeInstanceOf(\App\Services\MeetingInsights\MeetingInsightExtractorInterface::class);
        });

        it('extends AbstractInsightExtractor', function (): void {
            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');

            expect($extractor)->toBeInstanceOf(AbstractInsightExtractor::class);
        });
    });

    describe('HTTP call', function (): void {
        it('sends request to the OpenAI chat completions endpoint', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://api.openai.com/v1/chat/completions';
            });
        });

        it('sends the Authorization Bearer header with the API key', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'my-secret-key', model: 'gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->hasHeader('Authorization', 'Bearer my-secret-key');
            });
        });

        it('sends the configured model in the request body', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request['model'] === 'gpt-4o';
            });
        });

        it('sends system and user messages in the request body', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                $messages = $request['messages'];

                return count($messages) === 2
                    && $messages[0]['role'] === 'system'
                    && $messages[1]['role'] === 'user';
            });
        });

        it('requests JSON response format', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request['response_format']['type'] === 'json_object';
            });
        });
    });

    describe('response parsing', function (): void {
        it('returns an ExtractionResult with summary and items', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'The team discussed Q1 targets.',
                        'items' => [
                            ['type' => 'task', 'content' => 'Prepare report'],
                            ['type' => 'follow_up', 'content' => 'Check progress next week'],
                        ],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');
            $result = $extractor->extract('Some transcription', [], 'Meeting', 'en');

            expect($result)->toBeInstanceOf(ExtractionResult::class)
                ->and($result->summary)->toBe('The team discussed Q1 targets.')
                ->and($result->items)->toHaveCount(2)
                ->and($result->items[0])->toBeInstanceOf(ExtractionItem::class)
                ->and($result->items[0]->type)->toBe('task')
                ->and($result->items[1]->type)->toBe('follow_up');
        });
    });

    describe('error handling', function (): void {
        it('throws RuntimeException on non-successful HTTP response', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'error' => ['message' => 'Rate limit exceeded'],
                ], 429),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');

            expect(fn () => $extractor->extract('text', [], 'Meeting', 'en'))
                ->toThrow(\RuntimeException::class, 'Rate limit exceeded');
        });

        it('throws RuntimeException when response contains invalid JSON content', function (): void {
            Http::fake([
                'api.openai.com/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => 'not valid json']]],
                ]),
            ]);

            $extractor = new OpenAiInsightExtractor(apiKey: 'test-key', model: 'gpt-4o-mini');

            expect(fn () => $extractor->extract('text', [], 'Meeting', 'en'))
                ->toThrow(\RuntimeException::class);
        });
    });
});
