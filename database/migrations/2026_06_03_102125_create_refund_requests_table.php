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
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('refund_number')->unique();
            $table->foreignUuid('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('menunggu_konfirmasi'); // menunggu_konfirmasi, disetujui, ditolak, selesai
            $table->string('refund_method'); // transfer, poin
            $table->text('reason'); // cancellation reason
            $table->text('notes_admin')->nullable(); // admin response notes
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->decimal('refund_amount', 12, 2);
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
