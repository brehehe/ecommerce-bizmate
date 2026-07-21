<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('membership_level_id')->constrained('membership_levels');
            $table->string('code')->unique();
            $table->string('label');
            $table->enum('discount_type', ['percentage', 'nominal'])->default('percentage');
            $table->decimal('discount_value', 15, 2);
            $table->decimal('min_purchase', 15, 2)->default(0);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->enum('trigger', ['auto_assign', 'birthday', 'level_upgrade', 'manual'])->default('auto_assign');
            $table->enum('status', ['active', 'used', 'expired'])->default('active');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->timestamp('used_at')->nullable();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_vouchers');
    }
};
