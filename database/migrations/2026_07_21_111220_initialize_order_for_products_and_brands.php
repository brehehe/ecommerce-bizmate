<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $products = DB::table('products')->orderBy('created_at', 'desc')->pluck('id');
        $i = 1;
        foreach ($products as $id) {
            DB::table('products')->where('id', $id)->update(['order' => $i++]);
        }

        $brands = DB::table('brands')->orderBy('name', 'asc')->pluck('id');
        $j = 1;
        foreach ($brands as $id) {
            DB::table('brands')->where('id', $id)->update(['order' => $j++]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('products')->update(['order' => 0]);
        DB::table('brands')->update(['order' => 0]);
    }
};
