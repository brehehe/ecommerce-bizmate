<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->integer('amount');
            $table->enum('type', ['earned', 'redeemed', 'expired', 'bonus', 'adjusted']);
            $table->string('description')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_points');
    }
};
