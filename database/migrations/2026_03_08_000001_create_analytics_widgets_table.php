<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the analytics_widgets table.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analytics_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('data_source', 50);
            $table->string('chart_type', 30);
            $table->string('title', 100)->nullable();
            $table->unsignedTinyInteger('column_span')->default(1);
            $table->boolean('show_on_analytics')->default(true);
            $table->boolean('show_on_dashboard')->default(false);
            $table->unsignedSmallInteger('sort_order_analytics')->default(0);
            $table->unsignedSmallInteger('sort_order_dashboard')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_widgets');
    }
};
