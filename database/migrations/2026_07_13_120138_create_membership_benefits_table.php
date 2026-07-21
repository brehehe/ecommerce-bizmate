<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('membership_level_id')->constrained('membership_levels')->cascadeOnDelete();

            $table->enum('type', [
                'discount_percentage',
                'discount_nominal',
                'free_shipping',
                'cashback_percentage',
                'cashback_nominal',
                'point_multiplier',
                'auto_voucher',
                'birthday_bonus',
                'flash_sale_access',
                'priority_cs',
                'exclusive_product',
                'early_access',
            ]);

            $table->string('label');
            $table->text('description')->nullable();
            $table->decimal('value', 15, 2)->default(0)->comment('Percentage, nominal, or multiplier value');
            $table->string('icon')->default('ti-star');
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);

            $table->timestamps();

            $table->index(['membership_level_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_benefits');
    }
};
