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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('status', 50)->default('belum_bayar')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('status', [
                'belum_bayar',
                'menunggu',
                'diproses',
                'dikemas',
                'dikirim',
                'selesai',
                'batal',
            ])->default('belum_bayar')->change();
        });
    }
};
