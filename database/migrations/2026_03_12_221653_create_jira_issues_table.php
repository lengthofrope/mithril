<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jira_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jira_issue_id');
            $table->string('issue_key', 50);
            $table->string('summary', 500);
            $table->text('description_preview')->nullable();
            $table->string('project_key', 50);
            $table->string('project_name');
            $table->string('issue_type', 100);
            $table->string('status_name', 100);
            $table->string('status_category', 50);
            $table->string('priority_name', 50)->nullable();
            $table->string('assignee_name')->nullable();
            $table->string('assignee_email')->nullable();
            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();
            $table->json('labels')->nullable();
            $table->string('web_url', 2048);
            $table->json('sources');
            $table->timestamp('updated_in_jira_at')->useCurrent();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'jira_issue_id']);
            $table->index(['user_id', 'status_category']);
            $table->index(['user_id', 'is_dismissed', 'synced_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jira_issues');
    }
};
