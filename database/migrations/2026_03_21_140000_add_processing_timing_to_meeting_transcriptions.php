<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meeting_transcriptions', function (Blueprint $table): void {
            $table->timestamp('processing_started_at')->nullable()->after('diarization_error');
            $table->unsignedInteger('processing_duration_seconds')->nullable()->after('processing_started_at');
            $table->unsignedInteger('audio_duration_seconds')->nullable()->after('processing_duration_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_transcriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'processing_started_at',
                'processing_duration_seconds',
                'audio_duration_seconds',
            ]);
        });
    }
};
