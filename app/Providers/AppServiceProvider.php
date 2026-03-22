<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MeetingInsights\MeetingInsightExtractorInterface;
use App\Services\MeetingInsights\OpenAiInsightExtractor;
use App\Services\MeetingInsights\AnthropicInsightExtractor;
use App\Services\MeetingInsights\OpenRouterInsightExtractor;
use App\Services\Diarization\DiarizationServiceInterface;
use App\Services\Diarization\PyAnnoteDiarizationService;
use App\Services\Diarization\UnifiedSpeechDiarizationService;
use App\Services\Transcription\TranscriptionServiceInterface;
use App\Services\Transcription\UnifiedSpeechTranscriptionService;
use App\Services\Transcription\WhisperCppTranscriptionService;
use App\Services\Transcription\WhisperTranscriptionService;
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
            return match (config('meetings.diarization.provider')) {
                'unified' => new UnifiedSpeechDiarizationService(
                    baseUrl: config('meetings.diarization.unified.base_url') ?? 'http://localhost:8090',
                ),
                default => new PyAnnoteDiarizationService(
                    baseUrl: config('meetings.diarization.pyannote.base_url') ?? 'http://localhost:8081',
                ),
            };
        });

        $this->app->bind(TranscriptionServiceInterface::class, function (): TranscriptionServiceInterface {
            return match (config('meetings.transcription.provider')) {
                'whisper' => new WhisperTranscriptionService(
                    apiKey: config('meetings.transcription.whisper.api_key') ?? '',
                    model: config('meetings.transcription.whisper.model') ?? 'whisper-1',
                ),
                'unified' => new UnifiedSpeechTranscriptionService(
                    baseUrl: config('meetings.transcription.unified.base_url') ?? 'http://localhost:8090',
                ),
                default => new WhisperCppTranscriptionService(
                    baseUrl: config('meetings.transcription.whisper_cpp.base_url') ?? 'http://localhost:8080',
                ),
            };
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
