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
            $table->unsignedTinyInteger('dashboard_upcoming_tasks')->nullable()->after('prune_after_days');
            $table->unsignedTinyInteger('dashboard_upcoming_follow_ups')->nullable()->after('dashboard_upcoming_tasks');
            $table->unsignedTinyInteger('dashboard_upcoming_bilas')->nullable()->after('dashboard_upcoming_follow_ups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dashboard_upcoming_tasks',
                'dashboard_upcoming_follow_ups',
                'dashboard_upcoming_bilas',
            ]);
        });
    }
};
