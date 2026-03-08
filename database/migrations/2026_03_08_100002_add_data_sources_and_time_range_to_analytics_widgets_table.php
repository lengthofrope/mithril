<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds data_sources (JSON) and time_range columns to analytics_widgets.
 *
 * The existing data_source column is intentionally left in place while code
 * depending on it is migrated. These two columns are the forward-looking
 * replacements — data_sources as a multi-value JSON array and time_range as
 * an explicit window selector (e.g. '7d', '30d', '90d').
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analytics_widgets', function (Blueprint $table): void {
            $table->json('data_sources')->nullable()->after('data_source');
            $table->string('time_range', 10)->nullable()->after('data_sources');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_widgets', function (Blueprint $table): void {
            $table->dropColumn(['data_sources', 'time_range']);
        });
    }
};
