<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        $query = Promotion::with([
            'items.product.productPrice',
            'items.product.productStock',
            'items.variant.options',
            'items.variant.productPrice',
            'items.variant.productStock',
        ])->latest();

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('type') && $request->get('type') !== 'all') {
            $query->where('type', $request->get('type'));
        }

        if ($request->has('status') && $request->get('status') !== 'all') {
            $query->where('is_active', $request->get('status') === 'active');
        }

        $promotions = $query->paginate(10)->withQueryString();

        // Calculate metrics
        $now = now();
        $totalPromotions = Promotion::count();

        $activePromotions = Promotion::where('is_active', true)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->count();

        $totalVouchers = Promotion::whereIn('type', ['voucher_belanja', 'voucher_gratis_ongkir'])->count();

        $activeFlashSales = Promotion::where('type', 'flash_sale')
            ->where('is_active', true)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->count();

        return Inertia::render('Admin/Promotions/Index', [
            'promotions' => $promotions,
            'filters' => $request->only(['search', 'type', 'status']),
            'metrics' => [
                'total_promotions' => $totalPromotions,
                'active_promotions' => $activePromotions,
                'total_vouchers' => $totalVouchers,
                'active_flash_sales' => $activeFlashSales,
            ],
        ]);
    }

    public function create()
    {
        $products = $this->getProductsForSelection();

        return Inertia::render('Admin/Promotions/Create', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:promo_produk,promo_toko,voucher_belanja,voucher_gratis_ongkir,flash_sale,bundling_gift,special_deals'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('promotions', 'code')],
            'discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:0'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.promo_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.promo_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            $promotionData = collect($validated)->except('items')->toArray();

            // Format datetime local input to database timestamp format
            $promotionData['start_time'] = date('Y-m-d H:i:s', strtotime($validated['start_time']));
            $promotionData['end_time'] = date('Y-m-d H:i:s', strtotime($validated['end_time']));

            $promotion = Promotion::create($promotionData);

            if (! empty($validated['items']) && in_array($validated['type'], ['promo_produk', 'promo_toko', 'flash_sale', 'special_deals', 'voucher_belanja'])) {
                foreach ($validated['items'] as $item) {
                    $promotion->items()->create([
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'discount_type' => $item['discount_type'] ?? null,
                        'discount_value' => $item['discount_value'] ?? null,
                        'promo_price' => $item['promo_price'] ?? null,
                        'promo_stock' => $item['promo_stock'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('admin.promotions.index')->with('success', 'Promosi berhasil ditambahkan.');
    }

    public function edit(Promotion $promotion)
    {
        $promotion->load([
            'items.product.productPrice',
            'items.product.productStock',
            'items.variant.options',
            'items.variant.productPrice',
            'items.variant.productStock',
        ]);
        $products = $this->getProductsForSelection();

        return Inertia::render('Admin/Promotions/Edit', [
            'promotion' => $promotion,
            'products' => $products,
        ]);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:promo_produk,promo_toko,voucher_belanja,voucher_gratis_ongkir,flash_sale,bundling_gift,special_deals'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('promotions', 'code')->ignore($promotion->id)],
            'discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'quota' => ['nullable', 'integer', 'min:0'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.promo_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.promo_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $promotion) {
            $promotionData = collect($validated)->except('items')->toArray();

            $promotionData['start_time'] = date('Y-m-d H:i:s', strtotime($validated['start_time']));
            $promotionData['end_time'] = date('Y-m-d H:i:s', strtotime($validated['end_time']));

            $promotion->update($promotionData);

            // Sync items (delete old, insert current ones)
            $promotion->items()->delete();

            if (! empty($validated['items']) && in_array($validated['type'], ['promo_produk', 'promo_toko', 'flash_sale', 'special_deals', 'voucher_belanja'])) {
                foreach ($validated['items'] as $item) {
                    $promotion->items()->create([
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'discount_type' => $item['discount_type'] ?? null,
                        'discount_value' => $item['discount_value'] ?? null,
                        'promo_price' => $item['promo_price'] ?? null,
                        'promo_stock' => $item['promo_stock'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('admin.promotions.index')->with('success', 'Promosi berhasil diperbarui.');
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();

        return redirect()->back()->with('success', 'Promosi berhasil dihapus.');
    }

    public function toggleActive(Promotion $promotion)
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return redirect()->back()->with('success', 'Status promosi berhasil diubah.');
    }

    private function getProductsForSelection()
    {
        return Product::with([
            'productPrice',
            'productStock',
            'category',
            'variants.options',
            'variants.productPrice',
            'variants.productStock',
        ])->get()->map(function ($product) {
            $stock = $product->productStock?->is_unlimited ? 9999 : intval($product->productStock?->stock ?? 0);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'image' => $product->image,
                'category_name' => $product->category?->name ?? 'Tanpa Kategori',
                'price' => floatval($product->productPrice?->price ?? 0),
                'stock' => $stock,
                'variants' => $product->variants->map(function ($v) use ($product) {
                    $optionNames = $v->options->pluck('name')->join(' - ');
                    $vStock = $v->productStock?->is_unlimited ? 9999 : intval($v->productStock?->stock ?? 0);

                    return [
                        'id' => $v->id,
                        'sku' => $v->sku,
                        'image' => $v->image ?? $product->image,
                        'name' => $optionNames ?: $v->sku,
                        'price' => floatval($v->productPrice?->price ?? $product->productPrice?->price ?? 0),
                        'stock' => $vStock,
                    ];
                }),
            ];
        });
    }
}
