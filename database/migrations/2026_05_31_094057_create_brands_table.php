<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
            $table->json('specifications')->nullable()->after('description');
        });

        // Migrate existing unique brands from products table to brands table
        $existingBrands = DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->pluck('brand');

        foreach ($existingBrands as $brandName) {
            $brandId = (string) Str::uuid();
            DB::table('brands')->insert([
                'id' => $brandId,
                'name' => $brandName,
                'slug' => Str::slug($brandName).'-'.Str::random(3),
                'is_active' => true,
                'order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')
                ->where('brand', $brandName)
                ->update(['brand_id' => $brandId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn(['brand_id', 'specifications']);
        });

        Schema::dropIfExists('brands');
    }
};
