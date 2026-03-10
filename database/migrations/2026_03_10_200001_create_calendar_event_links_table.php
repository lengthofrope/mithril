<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the calendar_event_links polymorphic pivot table.
 *
 * Connects one calendar event to many resources of different types (Bila, Task, FollowUp, Note).
 * A unique constraint prevents the same resource from being linked to the same event twice.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (Schema::hasTable('calendar_event_links')) {
            return;
        }

        Schema::create('calendar_event_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_event_id')
                ->constrained('calendar_events')
                ->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id']);
            $table->unique(['calendar_event_id', 'linkable_type', 'linkable_id']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_links');
    }
};
