<script>
    /* eslint-disable svelte/require-each-key */
    import { Link } from '@inertiajs/svelte';

    let {
        relatedProducts,
        primary,
        secondary,
        product,
        relImg,
        isSellerEnabled,
        cartButtonStyle,
        handleDirectAddToCart,
        fmt,
    } = $props();
</script>

<!-- ─────────────────────────────────────────────────────
     RELATED PRODUCTS
───────────────────────────────────────────────────── -->
{#if relatedProducts === undefined}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        <div class="flex items-center justify-between mb-4 animate-pulse">
            <div class="h-6 w-32 bg-slate-200 rounded-lg"></div>
            <div class="h-4 w-16 bg-slate-200 rounded-lg"></div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            {#each Array(4) as _}
                <div
                    class="bg-white rounded-xl border border-slate-100 overflow-hidden animate-pulse"
                >
                    <div class="aspect-square bg-slate-100"></div>
                    <div class="p-3 space-y-2">
                        <div class="h-3 bg-slate-100 rounded w-3/4"></div>
                        <div class="h-3 bg-slate-100 rounded w-1/2"></div>
                        <div class="h-4 bg-slate-100 rounded w-2/3 mt-2"></div>
                    </div>
                </div>
            {/each}
        </div>
    </div>
{:else if relatedProducts && relatedProducts.length > 0}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
        <div class="flex items-center justify-between mb-4">
            <h2
                class="font-outfit font-black text-base sm:text-lg text-slate-800 flex items-center gap-2"
            >
                <div
                    class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs"
                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                >
                    <i class="ti ti-sparkles"></i>
                </div>
                Produk Serupa
            </h2>
            {#if product.category}
                <Link
                    href="/category/{product.category.slug ||
                        product.category.id}"
                    prefetch
                    class="text-xs font-bold flex items-center gap-1 hover:underline"
                    style="color:{primary};"
                >
                    Lihat Semua <i class="ti ti-arrow-right text-xs"></i>
                </Link>
            {/if}
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            {#each relatedProducts as rp}
                {@const ri = relImg(rp)}
                {@const isPromo = rp.is_promo}
                {@const price = isPromo
                    ? rp.promo_price
                    : (rp.product_price?.price ?? 0)}
                {@const originalPrice = isPromo ? rp.original_price : 0}
                {@const discountPercentage = isPromo
                    ? rp.discount_percentage
                    : 0}
                <a
                    href="/products/{rp.id}"
                    class="group bg-white rounded-xl border border-slate-100 hover:border-slate-200 hover:shadow-md overflow-hidden transition cursor-pointer flex flex-col h-full"
                >
                    <div class="relative aspect-square overflow-hidden">
                        {#if ri}
                            <img
                                src={ri}
                                alt={rp.name}
                                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                onerror={(e) => {
                                    e.currentTarget.src = '/noimage/image.png';
                                }}
                            />
                        {:else}
                            <img
                                src="/noimage/image.png"
                                alt="Produk tanpa gambar"
                                class="w-full h-full object-cover"
                            />
                        {/if}
                        <div
                            class="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1 items-start pointer-events-none"
                        >
                            {#if isSellerEnabled}
                                <span
                                    class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-xs {rp.condition ===
                                    'used'
                                        ? 'bg-amber-600'
                                        : 'bg-emerald-600'}"
                                >
                                    {rp.condition === 'used' ? 'Bekas' : 'Baru'}
                                </span>
                            {/if}
                            {#if isPromo && discountPercentage > 0}
                                <span
                                    class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                    style="background-color: {secondary};"
                                >
                                    -{discountPercentage}%
                                </span>
                            {/if}
                        </div>
                        {#if cartButtonStyle === 'icon'}
                            <button
                                onclick={(e) => handleDirectAddToCart(rp, e)}
                                class="absolute w-8 h-8 rounded-full bg-white/95 hover:bg-white text-slate-800 flex items-center justify-center shadow-md border transition-all duration-200 active:scale-90 hover:scale-105 z-10"
                                style="top: 0.375rem; right: 0.375rem; border-color: {primary}; color: {primary};"
                                title="Tambah ke Keranjang"
                            >
                                <i class="ti ti-plus text-base font-black"></i>
                            </button>
                        {/if}
                    </div>
                    <div class="p-3 flex-1 flex flex-col">
                        <div>
                            <!-- <p
                                class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1"
                                style="color: {primary};"
                            >
                                {rp.category?.name || 'PRODUK'}
                            </p> -->
                            <div class="h-[2rem] overflow-hidden mb-0.5">
                                <p
                                    class="text-xs sm:text-sm font-black leading-tight line-clamp-2"
                                    style="color: #1e293b;"
                                >
                                    {rp.name}
                                </p>
                            </div>
                            <hr class="border-slate-100 my-1" />
                            <div class="mb-0.5">
                                {#if price > 0}
                                    <p
                                        class="text-base sm:text-lg font-black leading-tight tracking-tight"
                                        style="color: {secondary};"
                                    >
                                        {fmt(price)}
                                    </p>
                                    {#if isPromo && originalPrice > price}
                                        <p
                                            class="text-sm sm:text-base text-red-600 line-through font-bold mt-0.5"
                                        >
                                            {fmt(originalPrice)}
                                        </p>
                                    {/if}
                                {:else}
                                    <p
                                        class="text-xs text-slate-400 font-semibold"
                                    >
                                        Hubungi Kami
                                    </p>
                                {/if}
                            </div>
                        </div>
                        {#if cartButtonStyle === 'button'}
                            <div class="mt-auto pt-3">
                                <button
                                    type="button"
                                    onclick={(e) => {
                                        e.preventDefault();
                                        handleDirectAddToCart(rp, e);
                                    }}
                                    class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl font-bold text-[10px] sm:text-xs text-white uppercase tracking-wider transition duration-200 hover:brightness-95 active:scale-[0.98] border-0"
                                    style="background-color: {primary};"
                                >
                                    <i
                                        class="ti ti-shopping-cart text-xs sm:text-sm"
                                    ></i>
                                    + KERANJANG
                                </button>
                            </div>
                        {/if}
                    </div>
                </a>
            {/each}
        </div>
    </div>
{/if}
