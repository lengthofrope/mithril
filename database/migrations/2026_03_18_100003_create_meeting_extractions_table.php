<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the meeting_extractions table for AI-extracted items from transcriptions.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meeting_extractions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('content', 1000);
            $table->foreignId('assignee_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->string('priority')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status')->default('pending');
            $table->string('created_model_type')->nullable();
            $table->unsignedBigInteger('created_model_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_extractions');
    }
};
