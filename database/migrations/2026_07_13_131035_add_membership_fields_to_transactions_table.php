<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('membership_discount_amount', 15, 2)->default(0)->after('discount_amount');
            $table->foreignUuid('membership_level_id')->nullable()->after('membership_discount_amount')->constrained('membership_levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_level_id');
            $table->dropColumn('membership_discount_amount');
        });
    }
};
