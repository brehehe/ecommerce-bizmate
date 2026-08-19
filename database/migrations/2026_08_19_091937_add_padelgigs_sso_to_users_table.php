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
        Schema::table('users', function (Blueprint $table) {
            $table->string('padelgigs_user_id', 100)->nullable()->unique()->after('id');
            $table->text('padelgigs_access_token')->nullable()->after('password');
            $table->text('padelgigs_refresh_token')->nullable()->after('padelgigs_access_token');
            $table->timestamp('padelgigs_token_expires_at')->nullable()->after('padelgigs_refresh_token');
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'padelgigs_user_id',
                'padelgigs_access_token',
                'padelgigs_refresh_token',
                'padelgigs_token_expires_at',
            ]);
            $table->string('password')->nullable(false)->change();
        });
    }
};
