<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the legacy bila and bila_prep_items tables after data migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('bila_prep_items');
        Schema::dropIfExists('bilas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration is not reversible. Use a database backup to restore.
    }
};
