<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix the weekly_reflections unique constraint to include user_id.
 *
 * The original constraint was on week_start alone, preventing different
 * users from having reflections for the same week.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('weekly_reflections', function (Blueprint $table): void {
            $table->dropUnique(['week_start']);
            $table->unique(['user_id', 'week_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_reflections', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'week_start']);
            $table->unique('week_start');
        });
    }
};
