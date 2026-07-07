<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores processed webhook events for idempotency.
     * Prevents the same webhook from being processed twice
     * when courier providers retry their callbacks.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();

            /** The provider that sent this webhook: 'biteship', 'komerce', 'midtrans', 'xendit', 'flip'. */
            $table->string('source', 50)->index();

            /**
             * A unique key that identifies this specific webhook event.
             * For shipping webhooks: "{source}:{order_id}:{status}"
             * For payment webhooks: "{source}:{reference_id}:{status}"
             */
            $table->string('idempotency_key')->unique();

            /** HTTP status code returned when we processed this event. */
            $table->unsignedSmallInteger('processed_status_code')->default(200);

            /** The full raw payload received from the provider (for debugging). */
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['source', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
