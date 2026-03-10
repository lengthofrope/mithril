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
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('microsoft_email', 255)->nullable()->after('status');
            $table->string('status_source', 20)->default('manual')->after('microsoft_email');
            $table->timestamp('status_synced_at')->nullable()->after('status_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn([
                'microsoft_email',
                'status_source',
                'status_synced_at',
            ]);
        });
    }
};
