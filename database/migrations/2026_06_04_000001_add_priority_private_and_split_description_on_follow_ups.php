<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the follow-up short-text field into a title and an optional long-form
 * description, and adds priority and is_private columns.
 *
 * The existing required `description` column is renamed to `title`; the rename
 * carries the stored values, preserving every follow-up's visible label. A new
 * nullable `description` TEXT column provides the long-form body. The FULLTEXT
 * search index is dropped before the rename and recreated afterwards to cover
 * the new `title` and `description` columns (skipped on SQLite, which lacks
 * FULLTEXT support).
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
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (!$isSqlite) {
            DB::statement('ALTER TABLE follow_ups DROP INDEX IF EXISTS ft_follow_ups_search');
        }

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->renameColumn('description', 'title');
        });

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('title');
            $table->string('priority', 20)->default('normal')->after('description');
            $table->boolean('is_private')->default(false)->after('priority');
        });

        if (!$isSqlite) {
            DB::statement(
                'ALTER TABLE follow_ups ADD FULLTEXT INDEX ft_follow_ups_search (title, description, waiting_on)'
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (!$isSqlite) {
            DB::statement('ALTER TABLE follow_ups DROP INDEX IF EXISTS ft_follow_ups_search');
        }

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->dropColumn(['description', 'priority', 'is_private']);
        });

        Schema::table('follow_ups', function (Blueprint $table): void {
            $table->renameColumn('title', 'description');
        });

        if (!$isSqlite) {
            DB::statement(
                'ALTER TABLE follow_ups ADD FULLTEXT INDEX ft_follow_ups_search (description, waiting_on)'
            );
        }
    }
};
