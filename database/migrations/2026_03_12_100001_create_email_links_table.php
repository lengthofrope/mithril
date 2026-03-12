<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the email_links polymorphic pivot table.
 *
 * Uses SET NULL on email deletion so that links survive when cached emails are pruned.
 * The denormalized email_subject field preserves provenance for display.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('email_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_id')
                ->nullable()
                ->constrained('emails')
                ->nullOnDelete();
            $table->string('email_subject', 500);
            $table->string('linkable_type', 255);
            $table->unsignedBigInteger('linkable_id');
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id']);
            $table->unique(['email_id', 'linkable_type', 'linkable_id']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_links');
    }
};
