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
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_recurring')->default(false)->after('is_private');
            $table->string('recurrence_interval', 20)->nullable()->after('is_recurring');
            $table->unsignedSmallInteger('recurrence_custom_days')->nullable()->after('recurrence_interval');
            $table->char('recurrence_series_id', 36)->nullable()->after('recurrence_custom_days');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_series_id')
                ->constrained('tasks')->nullOnDelete();

            $table->index('recurrence_series_id', 'idx_tasks_recurrence_series');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['recurrence_parent_id']);
            $table->dropIndex('idx_tasks_recurrence_series');
            $table->dropColumn([
                'is_recurring',
                'recurrence_interval',
                'recurrence_custom_days',
                'recurrence_series_id',
                'recurrence_parent_id',
            ]);
        });
    }
};
