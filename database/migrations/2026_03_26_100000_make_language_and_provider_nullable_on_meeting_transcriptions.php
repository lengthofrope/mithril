<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make language and provider nullable on meeting_transcriptions to support
 * stub records created by the start-local endpoint before language/provider
 * are known (they are filled in when the result is stored).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meeting_transcriptions', function (Blueprint $table): void {
            $table->string('language', 10)->nullable()->change();
            $table->string('provider')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_transcriptions', function (Blueprint $table): void {
            $table->string('language', 10)->nullable(false)->change();
            $table->string('provider')->nullable(false)->change();
        });
    }
};
