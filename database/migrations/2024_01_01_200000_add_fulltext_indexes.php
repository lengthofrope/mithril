<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds FULLTEXT indexes for search-heavy columns across core entities.
 *
 * These indexes improve search performance on tasks, notes, follow-ups,
 * and team members when using MATCH ... AGAINST queries.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            'ALTER TABLE tasks ADD FULLTEXT INDEX ft_tasks_search (title, description)'
        );

        DB::statement(
            'ALTER TABLE notes ADD FULLTEXT INDEX ft_notes_search (title, content)'
        );

        DB::statement(
            'ALTER TABLE follow_ups ADD FULLTEXT INDEX ft_follow_ups_search (description, waiting_on)'
        );

        DB::statement(
            'ALTER TABLE team_members ADD FULLTEXT INDEX ft_team_members_search (name, role)'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE tasks DROP INDEX ft_tasks_search');
        DB::statement('ALTER TABLE notes DROP INDEX ft_notes_search');
        DB::statement('ALTER TABLE follow_ups DROP INDEX ft_follow_ups_search');
        DB::statement('ALTER TABLE team_members DROP INDEX ft_team_members_search');
    }
};
