<?php

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
        Schema::create('dashboard_daily_summaries', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->decimal('revenue', 15, 2)->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('paid_orders_count')->default(0);
            $table->unsignedInteger('refunds_count')->default(0);
            $table->decimal('refunds_amount', 15, 2)->default(0);
            $table->unsignedInteger('returns_count')->default(0);
            $table->decimal('returns_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_daily_summaries');
    }
};
