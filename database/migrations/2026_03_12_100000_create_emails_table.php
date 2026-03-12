<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the emails table for caching Microsoft Graph email metadata.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('microsoft_message_id', 255);
            $table->string('subject', 500);
            $table->string('sender_name', 255)->nullable();
            $table->string('sender_email', 255)->nullable();
            $table->timestamp('received_at');
            $table->text('body_preview')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->date('flag_due_date')->nullable();
            $table->json('categories')->nullable();
            $table->string('importance', 20)->default('normal');
            $table->boolean('has_attachments')->default(false);
            $table->string('web_link', 1000)->nullable();
            $table->json('sources');
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['user_id', 'microsoft_message_id']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
