<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename bila-specific scheduling columns to generic meeting columns on team_members.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table): void {
            $table->renameColumn('next_bila_date', 'next_meeting_date');
            $table->renameColumn('bila_interval_days', 'meeting_interval_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table): void {
            $table->renameColumn('next_meeting_date', 'next_bila_date');
            $table->renameColumn('meeting_interval_days', 'bila_interval_days');
        });
    }
};
