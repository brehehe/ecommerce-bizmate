<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('membership_level_id')->constrained('membership_levels');

            // Accumulated stats (recalculated by MembershipService)
            $table->decimal('total_purchase', 15, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->integer('total_products')->default(0);
            $table->integer('total_points')->default(0);
            $table->decimal('total_cashback', 15, 2)->default(0);

            // Validity
            $table->date('joined_at');
            $table->date('expires_at')->nullable();

            $table->timestamps();

            $table->unique('user_id');
            $table->index('membership_level_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_memberships');
    }
};
