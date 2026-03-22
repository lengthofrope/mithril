<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MeetingInsights\MeetingInsightExtractorInterface;
use App\Services\MeetingInsights\OpenAiInsightExtractor;
use App\Services\MeetingInsights\AnthropicInsightExtractor;
use App\Services\MeetingInsights\OpenRouterInsightExtractor;
use App\Services\Diarization\DiarizationServiceInterface;
use App\Services\Diarization\UnifiedSpeechDiarizationService;
use App\Services\Transcription\TranscriptionServiceInterface;
use App\Services\Transcription\UnifiedSpeechTranscriptionService;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\Task;
use App\Observers\ActivityObserver;
use App\Observers\TaskObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Primary application service provider.
 *
 * Registers event-to-listener mappings and bootstraps application services.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(MeetingInsightExtractorInterface::class, function (): MeetingInsightExtractorInterface {
            $apiKey = config('ai.api_key') ?? '';
            $model = config('ai.model') ?? 'gpt-4o-mini';

            $provider = config('ai.provider') ?? 'openai';

            return match ($provider) {
                'openai' => new OpenAiInsightExtractor(apiKey: $apiKey, model: $model),
                'openrouter' => new OpenRouterInsightExtractor(apiKey: $apiKey, model: $model),
                'anthropic' => new AnthropicInsightExtractor(apiKey: $apiKey, model: $model),
                default => throw new \InvalidArgumentException("Unsupported AI provider: {$provider}"),
            };
        });

        $this->app->bind(DiarizationServiceInterface::class, function (): DiarizationServiceInterface {
            return new UnifiedSpeechDiarizationService(
                baseUrl: config('meetings.diarization.base_url') ?? 'http://localhost:8090',
            );
        });

        $this->app->bind(TranscriptionServiceInterface::class, function (): TranscriptionServiceInterface {
            return new UnifiedSpeechTranscriptionService(
                baseUrl: config('meetings.transcription.base_url') ?? 'http://localhost:8090',
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        Task::observe(ActivityObserver::class);
        FollowUp::observe(ActivityObserver::class);
        Meeting::observe(ActivityObserver::class);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
