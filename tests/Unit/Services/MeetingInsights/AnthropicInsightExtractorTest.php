<?php

declare(strict_types=1);

use App\Services\MeetingInsights\AbstractInsightExtractor;
use App\Services\MeetingInsights\AnthropicInsightExtractor;
use App\Services\MeetingInsights\ExtractionItem;
use App\Services\MeetingInsights\ExtractionResult;
use Illuminate\Support\Facades\Http;

describe('AnthropicInsightExtractor', function (): void {
    describe('class structure', function (): void {
        it('implements MeetingInsightExtractorInterface', function (): void {
            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');

            expect($extractor)->toBeInstanceOf(\App\Services\MeetingInsights\MeetingInsightExtractorInterface::class);
        });

        it('extends AbstractInsightExtractor', function (): void {
            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');

            expect($extractor)->toBeInstanceOf(AbstractInsightExtractor::class);
        });
    });

    describe('HTTP call', function (): void {
        it('sends request to the Anthropic Messages endpoint', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->url() === 'https://api.anthropic.com/v1/messages';
            });
        });

        it('sends the x-api-key header with the API key', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'ant-secret-key', model: 'claude-haiku-4-5-20251001');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->hasHeader('x-api-key', 'ant-secret-key');
            });
        });

        it('sends the anthropic-version header', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request->hasHeader('anthropic-version');
            });
        });

        it('sends the system prompt as a top-level parameter', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return isset($request['system'])
                    && is_string($request['system'])
                    && str_contains($request['system'], 'meeting analysis assistant');
            });
        });

        it('sends only user messages in the messages array', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                $messages = $request['messages'];

                return count($messages) === 1
                    && $messages[0]['role'] === 'user';
            });
        });

        it('sends the configured model in the request body', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-sonnet-4-5-20250514');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return $request['model'] === 'claude-sonnet-4-5-20250514';
            });
        });

        it('sets max_tokens in the request body', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Test summary.',
                        'items' => [],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');
            $extractor->extract('Some transcription', [], 'Meeting', 'en');

            Http::assertSent(function ($request): bool {
                return isset($request['max_tokens']) && $request['max_tokens'] > 0;
            });
        });
    });

    describe('response parsing', function (): void {
        it('returns an ExtractionResult with summary and items', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => json_encode([
                        'summary' => 'Sprint planning went well.',
                        'items' => [
                            ['type' => 'task', 'content' => 'Deploy to staging'],
                            ['type' => 'agreement', 'content' => 'No deploys on Fridays'],
                        ],
                    ])]],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');
            $result = $extractor->extract('Some transcription', [], 'Meeting', 'en');

            expect($result)->toBeInstanceOf(ExtractionResult::class)
                ->and($result->summary)->toBe('Sprint planning went well.')
                ->and($result->items)->toHaveCount(2)
                ->and($result->items[0])->toBeInstanceOf(ExtractionItem::class)
                ->and($result->items[0]->type)->toBe('task')
                ->and($result->items[1]->type)->toBe('agreement');
        });
    });

    describe('error handling', function (): void {
        it('throws RuntimeException on non-successful HTTP response', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'error' => ['message' => 'Invalid API key'],
                ], 401),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'bad-key', model: 'claude-haiku-4-5-20251001');

            expect(fn () => $extractor->extract('text', [], 'Meeting', 'en'))
                ->toThrow(\RuntimeException::class, 'Invalid API key');
        });

        it('throws RuntimeException when response contains invalid JSON content', function (): void {
            Http::fake([
                'api.anthropic.com/v1/messages' => Http::response([
                    'content' => [['type' => 'text', 'text' => 'not json']],
                ]),
            ]);

            $extractor = new AnthropicInsightExtractor(apiKey: 'test-key', model: 'claude-haiku-4-5-20251001');

            expect(fn () => $extractor->extract('text', [], 'Meeting', 'en'))
                ->toThrow(\RuntimeException::class);
        });
    });
});
