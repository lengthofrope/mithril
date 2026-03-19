<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MeetingInsights\MeetingInsightExtractorInterface;
use App\Services\MeetingInsights\OpenAiInsightExtractor;
use App\Services\Transcription\TranscriptionServiceInterface;
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
            return new OpenAiInsightExtractor(
                apiKey: config('meetings.extraction.openai.api_key') ?? config('meetings.transcription.whisper.api_key') ?? '',
                model: config('meetings.extraction.openai.model') ?? 'gpt-4o-mini',
            );
        });

        $this->app->bind(TranscriptionServiceInterface::class, function (): TranscriptionServiceInterface {
            return match (config('meetings.transcription.provider')) {
                'whisper' => new WhisperTranscriptionService(
                    apiKey: config('meetings.transcription.whisper.api_key') ?? '',
                    model: config('meetings.transcription.whisper.model') ?? 'whisper-1',
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
