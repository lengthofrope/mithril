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
            $table->timestamp('diarization_started_at')->nullable()->after('diarization_error');
            $table->unsignedInteger('diarization_duration_seconds')->nullable()->after('diarization_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_transcriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'diarization_started_at',
                'diarization_duration_seconds',
            ]);
        });
    }
};
