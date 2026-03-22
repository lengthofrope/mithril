<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add meeting_id foreign key to tasks, follow_ups, and agreements tables.
 * Add preferred_output_language to users table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('meeting_id')->nullable()->after('recurrence_series_id')->constrained()->nullOnDelete();
        });

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->foreignId('meeting_id')->nullable()->after('task_id')->constrained()->nullOnDelete();
        });

        Schema::table('agreements', function (Blueprint $table): void {
            $table->foreignId('meeting_id')->nullable()->after('team_member_id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('preferred_output_language', 10)->default('nl')->after('prune_after_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('meeting_id');
        });

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('meeting_id');
        });

        Schema::table('agreements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('meeting_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('preferred_output_language');
        });
    }
};
