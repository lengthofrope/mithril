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
            $table->longText('diarized_content')->nullable()->after('content');
            $table->string('diarization_status')->nullable()->after('status');
            $table->text('diarization_error')->nullable()->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_transcriptions', function (Blueprint $table): void {
            $table->dropColumn(['diarized_content', 'diarization_status', 'diarization_error']);
        });
    }
};
