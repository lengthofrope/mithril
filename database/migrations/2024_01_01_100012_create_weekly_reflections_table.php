<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the weekly_reflections table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weekly_reflections', function (Blueprint $table): void {
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->text('summary')->nullable();
            $table->text('reflection')->nullable();
            $table->timestamps();

            $table->unique('week_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_reflections');
    }
};
