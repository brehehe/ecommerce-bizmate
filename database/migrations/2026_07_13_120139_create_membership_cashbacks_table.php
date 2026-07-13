<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_cashbacks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignUuid('membership_level_id')->constrained('membership_levels');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'credited', 'expired', 'cancelled'])->default('pending');
            $table->string('description')->nullable();
            $table->date('credited_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_cashbacks');
    }
};
