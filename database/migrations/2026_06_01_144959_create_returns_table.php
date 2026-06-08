<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('return_number', 50)->unique();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 50)->default('menunggu_review');
            // refund = pengembalian dana, penggantian_barang = kirim barang baru
            $table->string('type', 30)->default('refund');
            $table->text('reason');
            $table->text('notes_admin')->nullable();
            // Resi pengiriman barang retur dari customer ke toko
            $table->string('return_tracking_number', 100)->nullable();
            $table->string('return_courier_name', 100)->nullable();
            // Resi pengiriman barang pengganti dari toko ke customer
            $table->string('replacement_tracking_number', 100)->nullable();
            $table->string('replacement_courier_name', 100)->nullable();
            $table->decimal('refund_amount', 14, 2)->default(0);
            // Link to new replacement transaction (if type = penggantian_barang)
            $table->foreignUuid('replacement_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transaction_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
