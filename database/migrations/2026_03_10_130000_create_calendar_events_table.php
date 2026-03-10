<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the calendar_events table for storing Microsoft Graph calendar data.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('microsoft_event_id', 255);
            $table->string('subject', 500);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->boolean('is_all_day')->default(false);
            $table->string('location', 500)->nullable();
            $table->string('status', 30);
            $table->boolean('is_online_meeting')->default(false);
            $table->string('online_meeting_url', 1000)->nullable();
            $table->string('organizer_name', 255)->nullable();
            $table->string('organizer_email', 255)->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->unique(['user_id', 'microsoft_event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
