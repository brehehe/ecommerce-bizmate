<?php

namespace App\Http\Controllers;

use App\Actions\Cart\AddToCartAction;
use App\Actions\Cart\SelectCartVouchersAction;
use App\Actions\Cart\UpdateCartItemAction;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\BulkUpdateCartRequest;
use App\Http\Requests\Cart\SelectVouchersRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\CartItem;
use App\Services\Promotion\PromotionCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly PromotionCalculationService $promotionService,
        private readonly AddToCartAction $addToCartAction,
        private readonly UpdateCartItemAction $updateCartItemAction,
        private readonly SelectCartVouchersAction $selectCartVouchersAction
    ) {}

    /**
     * Show the cart page.
     */
    public function index(Request $request): Response
    {
        session()->forget('buy_now_item');

        $cartItems = CartItem::with([
            'product.productPrice',
            'product.productStock',
            'product.images',
            'product.category',
            'product.variants.productPrice',
            'product.variants.productStock',
            'product.variants.options',
            'product.variants.tierPrices',
            'productVariant.productPrice',
            'productVariant.productStock',
            'productVariant.options',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $activePromotions = $this->promotionService->getActivePromotions();
        $this->promotionService->populateBundlingProducts($activePromotions);

        // Calculate dynamic pricing and tier prices
        foreach ($cartItems as $item) {
            $product = $item->product;
            $variant = $item->productVariant;

            $this->promotionService->applyPromotionsToProduct($product, $activePromotions, $item->quantity);

            if ($variant) {
                $matchedVariant = $product->variants->firstWhere('id', $variant->id);
                if ($matchedVariant) {
                    $variant = $matchedVariant;
                    $item->setRelation('productVariant', $matchedVariant);
                }
            }

            $basePrice = $variant
                ? (float) ($variant->is_promo ? $variant->promo_price : ($variant->productPrice?->price ?? 0))
                : (float) ($product->is_promo ? $product->promo_price : ($product->productPrice?->price ?? 0));

            $stock = (int) ($variant ? ($variant->productStock?->stock ?? 0) : ($product->productStock?->stock ?? 0));
            $isUnlimited = (bool) ($variant ? ($variant->productStock?->is_unlimited ?? false) : ($product->productStock?->is_unlimited ?? false));
            $tiers = $variant ? $variant->tierPrices : $product->tierPrices;

            $appliedTier = null;
            $effectivePrice = $basePrice;

            if ($tiers && $tiers->isNotEmpty()) {
                $matchingTiers = $tiers->filter(fn ($t) => $item->quantity >= $t->min_qty)->sortByDesc('min_qty');
                if ($matchingTiers->isNotEmpty()) {
                    $appliedTier = $matchingTiers->first();
                    $tierPriceVal = (float) $appliedTier->price;
                    if ($tierPriceVal < $effectivePrice) {
                        $effectivePrice = $tierPriceVal;
                    }
                }
            }

            $item->setAttribute('computed_base_price', $basePrice);
            $item->setAttribute('computed_price', $effectivePrice);
            $item->setAttribute('unit_price', $effectivePrice);
            $item->setAttribute('subtotal', $effectivePrice * $item->quantity);
            $item->setAttribute('price', $effectivePrice);
            $item->setAttribute('computed_stock', $stock);
            $item->setAttribute('computed_is_unlimited', $isUnlimited);
            $item->setAttribute('applied_tier', $appliedTier);
        }

        $cartPromoPrices = [];
        foreach ($cartItems as $item) {
            $key = $item->product_variant_id ? "v_{$item->product_variant_id}" : "p_{$item->product_id}";
            $cartPromoPrices[$key] = [
                'price' => (float) ($item->computed_price ?? $item->unit_price ?? 0),
                'is_promo' => (bool) ($item->productVariant ? ($item->productVariant->is_promo ?? false) : ($item->product->is_promo ?? false)),
            ];
        }
        session(['cart_promo_prices' => $cartPromoPrices]);

        return Inertia::render('Storefront/Cart', [
            'cartItems' => $cartItems,
            'activePromotions' => $activePromotions,
            'selectedVouchers' => [
                'belanja_id' => null,
                'ongkir_id' => null,
            ],
        ]);
    }

    /**
     * Add a product or variant to the cart.
     */
    public function store(AddToCartRequest $request): RedirectResponse
    {
        $user = $request->user();
        $buyNow = $request->boolean('buy_now', false);

        $result = $this->addToCartAction->execute(
            $user,
            $request->input('product_id'),
            $request->input('product_variant_id'),
            (int) $request->input('quantity', 1),
            $buyNow
        );

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        if ($result['buy_now']) {
            return redirect()->route('checkout.index');
        }

        return redirect()->back()->with('success', 'Produk berhasil ditambahkan ke keranjang!');
    }

    /**
     * Update quantity or variant of a cart item.
     */
    public function update(UpdateCartRequest $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorize('update', $cartItem);

        $result = $this->updateCartItemAction->execute($cartItem, $request->validated());

        if ($result['merged']) {
            return redirect()->back()->with('success', 'Keranjang digabungkan karena produk sejenis sudah ada!');
        }

        return redirect()->back();
    }

    /**
     * Bulk update check status of cart items.
     */
    public function bulkUpdate(BulkUpdateCartRequest $request): RedirectResponse
    {
        $query = CartItem::where('user_id', $request->user()->id);

        if ($request->has('ids')) {
            $query->whereIn('id', $request->input('ids'));
        }

        $query->update([
            'is_checked' => (bool) $request->input('is_checked'),
        ]);

        return redirect()->back();
    }

    /**
     * Remove a cart item.
     */
    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorize('delete', $cartItem);

        $cartItem->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus dari keranjang!');
    }

    /**
     * Select multiple vouchers for cart.
     */
    public function selectVouchers(SelectVouchersRequest $request): RedirectResponse
    {
        $this->selectCartVouchersAction->execute(
            $request->input('voucher_belanja_id'),
            $request->input('voucher_ongkir_id'),
            $request->user()
        );

        return redirect()->back()->with('success', 'Voucher berhasil dipilih.');
    }
}
