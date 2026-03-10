<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a JSON attendees column to the calendar_events table,
 * storing an array of {email, name} objects synced from Microsoft Graph.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->json('attendees')->nullable()->after('organizer_email');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropColumn('attendees');
        });
    }
};
