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
        // 1. Profil Resto / Dapur Mitra
        if (! Schema::hasTable('resto_profiles')) {
            Schema::create('resto_profiles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('resto_name');
                $table->string('slug')->unique();
                $table->string('culinary_category');
                $table->text('description')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('banner_path')->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->json('operating_hours')->nullable();
                $table->integer('avg_prep_time_minutes')->default(20);
                $table->boolean('allow_pickup')->default(true);
                $table->boolean('allow_delivery')->default(true);
                $table->decimal('max_delivery_radius_km', 5, 2)->default(7.00);
                $table->boolean('is_open_now')->default(true);
                $table->string('approval_status')->default('pending_approval');
                $table->timestamps();

                $table->index(['latitude', 'longitude']);
                $table->index('approval_status');
            });
        }

        // 2. Kustomisasi Menu (Grup Modifier: Level Pedas, Pilihan Nasi, Topping)
        if (! Schema::hasTable('menu_modifiers')) {
            Schema::create('menu_modifiers', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('group_name');
                $table->boolean('is_required')->default(false);
                $table->boolean('is_multiple')->default(false);
                $table->integer('max_selectable')->nullable();
                $table->timestamps();
            });
        }

        // 3. Opsi Item Modifier (Level 1, Level 2, Telur Ceplok, Keju, dll)
        if (! Schema::hasTable('menu_modifier_items')) {
            Schema::create('menu_modifier_items', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('menu_modifier_id')->constrained('menu_modifiers')->cascadeOnDelete();
                $table->string('name');
                $table->decimal('additional_price', 12, 2)->default(0.00);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_available')->default(true);
                $table->timestamps();
            });
        }

        // 4. Kolom Tambahan Transaksi Khusus FoodPick (Nullable agar 100% aman untuk e-commerce)
        Schema::table('transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('transactions', 'resto_id')) {
                $table->foreignUuid('resto_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('transactions', 'fulfillment_type')) {
                $table->string('fulfillment_type')->default('self_pickup')->nullable()->after('shipping_courier');
            }
            if (! Schema::hasColumn('transactions', 'pickup_type')) {
                $table->string('pickup_type')->default('takeaway')->nullable()->after('fulfillment_type');
            }
            if (! Schema::hasColumn('transactions', 'table_number')) {
                $table->string('table_number')->nullable()->after('pickup_type');
            }
            if (! Schema::hasColumn('transactions', 'scheduled_date')) {
                $table->date('scheduled_date')->nullable()->after('pickup_at');
            }
            if (! Schema::hasColumn('transactions', 'scheduled_time_slot')) {
                $table->string('scheduled_time_slot')->nullable()->after('scheduled_date');
            }
            if (! Schema::hasColumn('transactions', 'kitchen_status')) {
                $table->string('kitchen_status')->default('pesanan_masuk')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $columnsToDrop = [];
            foreach (['resto_id', 'fulfillment_type', 'pickup_type', 'table_number', 'scheduled_date', 'scheduled_time_slot', 'kitchen_status'] as $column) {
                if (Schema::hasColumn('transactions', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropForeign(['resto_id']);
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::dropIfExists('menu_modifier_items');
        Schema::dropIfExists('menu_modifiers');
        Schema::dropIfExists('resto_profiles');
    }
};
