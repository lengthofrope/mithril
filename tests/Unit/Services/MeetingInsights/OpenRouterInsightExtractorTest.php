<?php

declare(strict_types=1);

use App\Services\MeetingInsights\AbstractInsightExtractor;
use App\Services\MeetingInsights\ExtractionItem;
use App\Services\MeetingInsights\ExtractionResult;
use App\Services\MeetingInsights\OpenRouterInsightExtractor;
use Illuminate\Support\Facades\Http;

describe('OpenRouterInsightExtractor', function (): void {
    describe('class structure', function (): void {
        it('implements MeetingInsightExtractorInterface', function (): void {
            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'openai/gpt-4o-mini');

            expect($extractor)->toBeInstanceOf(\App\Services\MeetingInsights\MeetingInsightExtractorInterface::class);
        });

        it('extends AbstractInsightExtractor', function (): void {
            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'openai/gpt-4o-mini');

            expect($extractor)->toBeInstanceOf(AbstractInsightExtractor::class);
        });
    });

    describe('HTTP call', function (): void {
        it('sends request to the OpenRouter chat completions endpoint', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'openai/gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://openrouter.ai/api/v1/chat/completions';
            });
        });

        it('sends the Authorization Bearer header with the API key', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'or-secret-key', model: 'openai/gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->hasHeader('Authorization', 'Bearer or-secret-key');
            });
        });

        it('sends HTTP-Referer and X-Title headers', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'openai/gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->hasHeader('HTTP-Referer')
                    && $request->hasHeader('X-Title');
            });
        });

        it('sends the configured model in the request body', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'anthropic/claude-3-haiku');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request['model'] === 'anthropic/claude-3-haiku';
            });
        });

        it('sends system and user messages in the request body', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'openai/gpt-4o-mini');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                $messages = $request['messages'];

                return count($messages) === 2
                    && $messages[0]['role'] === 'system'
                    && $messages[1]['role'] === 'user';
            });
        });
    });

    describe('response parsing', function (): void {
        it('returns an ExtractionResult with summary and items', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => json_encode([
                        'summary' => 'The team discussed roadmap.',
                        'items' => [
                            ['type' => 'decision', 'content' => 'Ship by Q2'],
                        ],
                    ])]]],
                ]),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'openai/gpt-4o-mini');
            $result = $extractor->extract('Some transcription', [], 'Meeting', 'en');

            expect($result)->toBeInstanceOf(ExtractionResult::class)
                ->and($result->summary)->toBe('The team discussed roadmap.')
                ->and($result->items)->toHaveCount(1)
                ->and($result->items[0])->toBeInstanceOf(ExtractionItem::class)
                ->and($result->items[0]->type)->toBe('decision');
        });
    });

    describe('error handling', function (): void {
        it('throws RuntimeException on non-successful HTTP response', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'error' => ['message' => 'Invalid API key'],
                ], 401),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'bad-key', model: 'openai/gpt-4o-mini');

            expect(fn () => $extractor->extract('text', [], 'Meeting', 'en'))
                ->toThrow(\RuntimeException::class, 'Invalid API key');
        });

        it('throws RuntimeException when response contains invalid JSON content', function (): void {
            Http::fake([
                'openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [['message' => ['content' => 'not json']]],
                ]),
            ]);

            $extractor = new OpenRouterInsightExtractor(apiKey: 'test-key', model: 'openai/gpt-4o-mini');

            expect(fn () => $extractor->extract('text', [], 'Meeting', 'en'))
                ->toThrow(\RuntimeException::class);
        });
    });
});
