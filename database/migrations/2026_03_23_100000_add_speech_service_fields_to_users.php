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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('speech_service_mode')->nullable()->after('sidebar_collapsed');
            $table->string('speech_service_url')->nullable()->after('speech_service_mode');
            $table->text('speech_service_token')->nullable()->after('speech_service_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['speech_service_mode', 'speech_service_url', 'speech_service_token']);
        });
    }
};
