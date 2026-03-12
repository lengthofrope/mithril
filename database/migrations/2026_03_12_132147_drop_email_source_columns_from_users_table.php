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
            $table->dropColumn([
                'email_source_flagged',
                'email_source_categorized',
                'email_source_category_name',
                'email_source_unread',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_source_flagged')->default(true)->after('dashboard_upcoming_bilas');
            $table->boolean('email_source_categorized')->default(false)->after('email_source_flagged');
            $table->string('email_source_category_name', 100)->nullable()->default('Mithril')->after('email_source_categorized');
            $table->boolean('email_source_unread')->default(false)->after('email_source_category_name');
        });
    }
};
