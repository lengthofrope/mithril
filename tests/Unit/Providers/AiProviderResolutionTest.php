<?php

declare(strict_types=1);

use App\Services\MeetingInsights\AnthropicInsightExtractor;
use App\Services\MeetingInsights\MeetingInsightExtractorInterface;
use App\Services\MeetingInsights\OpenAiInsightExtractor;
use App\Services\MeetingInsights\OpenRouterInsightExtractor;

describe('AI provider resolution', function (): void {
    it('resolves OpenAiInsightExtractor when AI_PROVIDER is openai', function (): void {
        config()->set('ai.provider', 'openai');

        $extractor = app(MeetingInsightExtractorInterface::class);

        expect($extractor)->toBeInstanceOf(OpenAiInsightExtractor::class);
    });

    it('resolves OpenAiInsightExtractor when AI_PROVIDER is not set (default)', function (): void {
        config()->set('ai.provider', null);

        $extractor = app(MeetingInsightExtractorInterface::class);

        expect($extractor)->toBeInstanceOf(OpenAiInsightExtractor::class);
    });

    it('resolves OpenRouterInsightExtractor when AI_PROVIDER is openrouter', function (): void {
        config()->set('ai.provider', 'openrouter');

        $extractor = app(MeetingInsightExtractorInterface::class);

        expect($extractor)->toBeInstanceOf(OpenRouterInsightExtractor::class);
    });

    it('resolves AnthropicInsightExtractor when AI_PROVIDER is anthropic', function (): void {
        config()->set('ai.provider', 'anthropic');

        $extractor = app(MeetingInsightExtractorInterface::class);

        expect($extractor)->toBeInstanceOf(AnthropicInsightExtractor::class);
    });

    it('throws InvalidArgumentException for unknown AI_PROVIDER values', function (): void {
        config()->set('ai.provider', 'unknown-provider');

        expect(fn () => app(MeetingInsightExtractorInterface::class))
            ->toThrow(\InvalidArgumentException::class);
    });
});
