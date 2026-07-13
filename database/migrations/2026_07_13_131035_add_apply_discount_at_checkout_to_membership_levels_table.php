<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_levels', function (Blueprint $table) {
            $table->boolean('apply_discount_at_checkout')->default(false)->after('auto_downgrade');
        });
    }

    public function down(): void
    {
        Schema::table('membership_levels', function (Blueprint $table) {
            $table->dropColumn('apply_discount_at_checkout');
        });
    }
};
