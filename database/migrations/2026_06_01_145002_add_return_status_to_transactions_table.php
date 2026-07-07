<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'return_status')) {
                $table->string('return_status', 50)->nullable()->after('cancel_reason');
            }
            if (! Schema::hasColumn('transactions', 'is_replacement_transaction')) {
                $table->boolean('is_replacement_transaction')->default(false)->after('return_status');
            }
            if (! Schema::hasColumn('transactions', 'original_transaction_id')) {
                $table->foreignUuid('original_transaction_id')->nullable()->constrained('transactions')->nullOnDelete()->after('is_replacement_transaction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['original_transaction_id']);
            $table->dropColumn(['return_status', 'is_replacement_transaction', 'original_transaction_id']);
        });
    }
};
