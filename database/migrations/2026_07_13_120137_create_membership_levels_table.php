<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_levels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->default('ti-award');
            $table->string('badge_color')->default('#6366f1');
            $table->text('description')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);

            // Upgrade requirements
            $table->decimal('min_total_purchase', 15, 2)->default(0);
            $table->integer('min_total_transactions')->default(0);
            $table->integer('min_total_products')->default(0);
            $table->enum('period_type', ['lifetime', '12_months', '6_months', '3_months'])->default('lifetime');

            // Automation
            $table->boolean('auto_upgrade')->default(true);
            $table->boolean('auto_downgrade')->default(false);
            $table->integer('validity_months')->nullable()->comment('null = no expiry');

            $table->timestamps();
            $table->softDeletes();

            $table->index('order');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_levels');
    }
};
