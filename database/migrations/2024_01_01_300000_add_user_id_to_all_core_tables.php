<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds user_id foreign key to all core tables for multi-tenant user scoping.
 *
 * Every record across these 12 tables is owned by a single user. The foreign
 * key cascades deletes so that removing a user removes all their data.
 */
return new class extends Migration
{
    /**
     * The tables that require a user_id column.
     *
     * @var list<string>
     */
    private array $tables = [
        'teams',
        'team_members',
        'tasks',
        'task_groups',
        'task_categories',
        'follow_ups',
        'bilas',
        'bila_prep_items',
        'agreements',
        'notes',
        'note_tags',
        'weekly_reflections',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('user_id')->after('id')->constrained()->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropConstrainedForeignId('user_id');
            });
        }
    }
};
