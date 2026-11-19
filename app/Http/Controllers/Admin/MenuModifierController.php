<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuModifier;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuModifierController extends Controller
{
    /**
     * Check if the authenticated user is allowed to manage this product's modifiers.
     */
    protected function authorizeProduct(Request $request, Product $product): void
    {
        $user = $request->user();
        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return;
        }

        if ($user->is_seller && $product->user_id === $user->id) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk mengubah kustomisasi menu ini.');
    }

    /**
     * Display the modifiers management page for a specific menu item.
     */
    public function index(Request $request, Product $product): Response
    {
        $this->authorizeProduct($request, $product);

        $product->load([
            'menuModifiers.items' => function ($q) {
                $q->orderBy('created_at');
            },
            'images',
            'productPrice',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Products/Modifiers', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => (float) ($product->productPrice?->price ?? 0),
                'image_url' => $product->images->first()?->url ?? $product->images->first()?->path ?? null,
                'modifiers' => $product->menuModifiers->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'product_id' => $m->product_id,
                        'group_name' => $m->group_name,
                        'is_required' => (bool) $m->is_required,
                        'is_multiple' => (bool) $m->is_multiple,
                        'max_selectable' => (int) ($m->max_selectable ?: 1),
                        'items' => $m->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'menu_modifier_id' => $item->menu_modifier_id,
                                'name' => $item->name,
                                'additional_price' => (float) $item->additional_price,
                                'is_default' => (bool) $item->is_default,
                                'is_available' => (bool) $item->is_available,
                            ];
                        }),
                    ];
                }),
            ],
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Store a new modifier group with items.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'group_name' => 'required|string|max:100',
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
            'max_selectable' => 'nullable|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:100',
            'items.*.additional_price' => 'required|numeric|min:0',
            'items.*.is_default' => 'nullable|boolean',
            'items.*.is_available' => 'nullable|boolean',
        ], [
            'group_name.required' => 'Nama grup kustomisasi (misal: Level Pedas / Pilihan Nasi) wajib diisi.',
            'items.required' => 'Minimal harus menambahkan 1 pilihan opsi.',
            'items.min' => 'Minimal harus menambahkan 1 pilihan opsi.',
            'items.*.name.required' => 'Nama opsi tidak boleh kosong.',
            'items.*.additional_price.required' => 'Harga tambahan tidak boleh kosong.',
        ]);

        $modifier = $product->menuModifiers()->create([
            'group_name' => $validated['group_name'],
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_multiple' => (bool) ($validated['is_multiple'] ?? false),
            'max_selectable' => ! empty($validated['is_multiple']) ? ($validated['max_selectable'] ?? 5) : 1,
        ]);

        foreach ($validated['items'] as $itemData) {
            $modifier->items()->create([
                'name' => $itemData['name'],
                'additional_price' => (float) ($itemData['additional_price'] ?? 0),
                'is_default' => (bool) ($itemData['is_default'] ?? false),
                'is_available' => (bool) ($itemData['is_available'] ?? true),
            ]);
        }

        return back()->with('success', 'Grup kustomisasi menu berhasil ditambahkan!');
    }

    /**
     * Update an existing modifier group and its items.
     */
    public function update(Request $request, Product $product, MenuModifier $modifier): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($modifier->product_id !== $product->id) {
            abort(404);
        }

        $validated = $request->validate([
            'group_name' => 'required|string|max:100',
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
            'max_selectable' => 'nullable|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:100',
            'items.*.additional_price' => 'required|numeric|min:0',
            'items.*.is_default' => 'nullable|boolean',
            'items.*.is_available' => 'nullable|boolean',
        ]);

        $modifier->update([
            'group_name' => $validated['group_name'],
            'is_required' => (bool) ($validated['is_required'] ?? false),
            'is_multiple' => (bool) ($validated['is_multiple'] ?? false),
            'max_selectable' => ! empty($validated['is_multiple']) ? ($validated['max_selectable'] ?? 5) : 1,
        ]);

        // Recreate items cleanly
        $modifier->items()->delete();

        foreach ($validated['items'] as $itemData) {
            $modifier->items()->create([
                'name' => $itemData['name'],
                'additional_price' => (float) ($itemData['additional_price'] ?? 0),
                'is_default' => (bool) ($itemData['is_default'] ?? false),
                'is_available' => (bool) ($itemData['is_available'] ?? true),
            ]);
        }

        return back()->with('success', 'Grup kustomisasi berhasil diperbarui!');
    }

    /**
     * Delete a modifier group.
     */
    public function destroy(Request $request, Product $product, MenuModifier $modifier): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        if ($modifier->product_id !== $product->id) {
            abort(404);
        }

        $modifier->items()->delete();
        $modifier->delete();

        return back()->with('success', 'Grup kustomisasi berhasil dihapus.');
    }
}
