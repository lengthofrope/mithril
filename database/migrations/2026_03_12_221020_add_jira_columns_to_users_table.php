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
        Schema::table('users', function (Blueprint $table) {
            $table->string('jira_cloud_id')->nullable()->after('microsoft_token_expires_at');
            $table->string('jira_account_id')->nullable()->after('jira_cloud_id');
            $table->text('jira_access_token')->nullable()->after('jira_account_id');
            $table->text('jira_refresh_token')->nullable()->after('jira_access_token');
            $table->timestamp('jira_token_expires_at')->nullable()->after('jira_refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'jira_cloud_id',
                'jira_account_id',
                'jira_access_token',
                'jira_refresh_token',
                'jira_token_expires_at',
            ]);
        });
    }
};
