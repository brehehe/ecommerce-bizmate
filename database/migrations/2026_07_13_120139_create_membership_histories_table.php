<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('from_level_id')->nullable()->constrained('membership_levels')->nullOnDelete();
            $table->foreignUuid('to_level_id')->constrained('membership_levels');
            $table->enum('action', ['upgraded', 'downgraded', 'assigned', 'renewed', 'expired']);
            $table->text('reason')->nullable();
            $table->decimal('total_purchase_at_time', 15, 2)->default(0);
            $table->integer('total_transactions_at_time')->default(0);
            $table->uuid('processed_by')->nullable()->comment('admin user id, null = automatic');
            $table->timestamps();

            $table->index('user_id');
            $table->index('to_level_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_histories');
    }
};
