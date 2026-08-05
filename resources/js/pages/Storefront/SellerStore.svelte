<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';
    import { showToast } from '@/utils/toast';

    let {
        seller,
        products = undefined,
        filters = { q: '', sort: 'latest' },
        storeName = '',
    } = $props();

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');
    const auth = $derived(page.props.auth?.user);
    const cartButtonStyle = $derived(
        (page.props.settings as any)?.storefront_cart_button_style || 'button',
    );
    const isSellerEnabled = $derived(
        (page.props as any).app_config?.is_seller_enabled ??
            (page.props as any).settings?.is_seller_enabled ??
            false,
    );

    let searchQ = $state('');
    let selectedSort = $state('latest');

    $effect(() => {
        searchQ = filters?.q || '';
        selectedSort = filters?.sort || 'latest';
    });

    let selectedVariantProduct = $state<any>(null);
    let showVariantModal = $state(false);

    function formatPrice(price: any) {
        const n = Number(price);
        if (!n) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function getProductImage(product: any) {
        let path: string | null = null;
        if (product?.images?.length > 0) {
            path = product.images[0].url || product.images[0].path;
        } else if (product?.image) {
            path = product.image;
        }
        if (!path || typeof path !== 'string') { return null; }
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
            return path;
        }
        return '/' + path;
    }

    function applyFilters() {
        router.get(
            `/${seller.store_slug}`,
            { q: searchQ, sort: selectedSort },
            { preserveState: true, replace: true },
        );
    }

    function handleAddToCart(product: any, e: MouseEvent) {
        e.preventDefault();
        e.stopPropagation();

        if (!auth) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        if (product.variants && product.variants.length > 0) {
            selectedVariantProduct = product;
            showVariantModal = true;
            return;
        }

        router.post(
            '/cart',
            { product_id: product.id, product_variant_id: null, quantity: 1 },
            {
                preserveScroll: true,
                onError: () => showToast('Gagal menambahkan ke keranjang.', 'error'),
            },
        );
    }

    const sellerLogoUrl = $derived(
        seller?.store_logo ? `/storage/${seller.store_logo}` : null,
    );

    const productList = $derived(products?.data ?? []);
    const totalProducts = $derived(products?.total ?? 0);

    const sortOptions = [
        { id: 'latest', label: 'Terbaru' },
        { id: 'popular', label: 'Terlaris' },
        { id: 'price_asc', label: 'Harga Terendah' },
        { id: 'price_desc', label: 'Harga Tertinggi' },
    ];
</script>

<svelte:head>
    <title>{seller?.store_name || seller?.name} — {storeName || 'Toko'}</title>
    <meta name="description" content={seller?.store_description || `Belanja di toko ${seller?.store_name || seller?.name}`} />
</svelte:head>

<StorefrontLayout>
    <!-- ── Store Hero Banner ─────────────────────────────── -->
    <div
        class="relative overflow-hidden"
        style="background: linear-gradient(135deg, {primary}22 0%, {primary}08 100%);"
    >
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
            <div class="flex items-center gap-5 sm:gap-7">
                <!-- Store Logo -->
                <div
                    class="shrink-0 w-20 h-20 sm:w-28 sm:h-28 rounded-2xl sm:rounded-3xl border-4 border-white shadow-lg overflow-hidden flex items-center justify-center"
                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                >
                    {#if sellerLogoUrl}
                        <img
                            src={sellerLogoUrl}
                            alt={seller?.store_name}
                            class="w-full h-full object-cover"
                            onerror={(e) => {
                                (e.currentTarget as HTMLImageElement).style.display = 'none';
                            }}
                        />
                    {:else}
                        <span class="text-white text-3xl sm:text-4xl font-black uppercase">
                            {seller?.store_name?.substring(0, 1) || '?'}
                        </span>
                    {/if}
                </div>

                <!-- Store Info -->
                <div class="flex-1 min-w-0">
                    <h1 class="font-outfit font-black text-xl sm:text-3xl text-slate-900 leading-tight">
                        {seller?.store_name || seller?.name || 'Toko'}
                    </h1>
                    {#if seller?.store_slug}
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5 font-mono">
                            /{seller.store_slug}
                        </p>
                    {/if}
                    {#if seller?.store_description}
                        <p class="text-sm text-slate-600 mt-2 line-clamp-2 max-w-xl">
                            {seller.store_description}
                        </p>
                    {/if}
                    <div class="mt-3 flex items-center gap-3">
                        <span
                            class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-bold rounded-full text-white"
                            style="background-color: {primary};"
                        >
                            <i class="ti ti-building-store text-sm"></i>
                            Toko Resmi
                        </span>
                        <span class="text-xs text-slate-500">
                            {totalProducts} produk
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Search & Filter Bar ────────────────────────────── -->
    <div class="sticky top-0 z-30 bg-white border-b border-slate-100 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3">
            <div class="flex items-center gap-3">
                <!-- Search -->
                <form
                    onsubmit={(e) => { e.preventDefault(); applyFilters(); }}
                    class="flex-1 relative"
                >
                    <input
                        type="text"
                        bind:value={searchQ}
                        placeholder="Cari produk di toko ini..."
                        class="w-full pl-4 pr-10 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                    />
                    <button
                        type="submit"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    >
                        <i class="ti ti-search text-base"></i>
                    </button>
                </form>

                <!-- Sort -->
                <select
                    bind:value={selectedSort}
                    onchange={applyFilters}
                    class="shrink-0 pl-3 pr-8 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition appearance-none cursor-pointer"
                >
                    {#each sortOptions as opt}
                        <option value={opt.id}>{opt.label}</option>
                    {/each}
                </select>
            </div>
        </div>
    </div>

    <!-- ── Product Grid ───────────────────────────────────── -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

        {#if productList.length === 0}
            <div class="py-20 text-center">
                <div
                    class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                    style="background-color: {primary}15;"
                >
                    <i class="ti ti-package-off text-3xl" style="color: {primary};"></i>
                </div>
                <p class="font-outfit font-black text-xl text-slate-700 mb-1">
                    {filters.q ? 'Produk Tidak Ditemukan' : 'Belum Ada Produk'}
                </p>
                <p class="text-sm text-slate-400">
                    {#if filters.q}
                        Coba kata kunci lain atau hapus filter.
                    {:else}
                        Toko ini belum memiliki produk aktif.
                    {/if}
                </p>
                {#if filters.q}
                    <button
                        type="button"
                        onclick={() => { searchQ = ''; applyFilters(); }}
                        class="mt-4 px-5 py-2 text-sm font-bold rounded-xl text-white"
                        style="background-color: {primary};"
                    >
                        Hapus Filter
                    </button>
                {/if}
            </div>
        {:else}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                {#each productList as product (product.id)}
                    {@const img = getProductImage(product)}
                    {@const isPromo = product.is_promo}
                    {@const price = isPromo
                        ? product.promo_price
                        : (product.product_price?.price ?? 0)}
                    {@const originalPrice = isPromo ? product.original_price : 0}
                    {@const discountPct = isPromo ? product.discount_percentage : 0}

                    <div class="relative group bg-white border border-slate-100 hover:border-slate-200 hover:shadow-lg rounded-xl overflow-hidden transition flex flex-col h-full">
                        <a
                            href={`/products/${product.id}`}
                            class="flex flex-col flex-1 cursor-pointer"
                        >
                            <!-- Product Image -->
                            <div class="relative aspect-square overflow-hidden border-b border-slate-50">
                                {#if img}
                                    <img
                                        src={img}
                                        alt={product.name}
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                        onerror={(e) => {
                                            (e.currentTarget as HTMLImageElement).src = '/noimage/image.png';
                                        }}
                                    />
                                {:else}
                                    <img
                                        src="/noimage/image.png"
                                        alt="Produk tanpa gambar"
                                        class="w-full h-full object-cover"
                                    />
                                {/if}

                                <div class="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1 items-start pointer-events-none">
                                    {#if isSellerEnabled}
                                        <span
                                            class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-xs {product.condition === 'rent' ? 'bg-purple-600' : (product.condition === 'used' || product.condition === 'second' ? 'bg-amber-600' : 'bg-emerald-600')}"
                                        >
                                            {product.condition === 'rent' ? 'Rent' : (product.condition === 'used' || product.condition === 'second' ? 'Second' : 'New')}
                                        </span>
                                    {/if}
                                    {#if isPromo && discountPct > 0}
                                        <span
                                            class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                            style="background-color: {secondary};"
                                        >
                                            -{discountPct}%
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between">
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-slate-800 line-clamp-2 leading-snug mb-1.5">
                                        {product.name}
                                    </p>
                                    {#if product.avg_rating}
                                        <div class="flex items-center gap-1 mb-1">
                                            <i class="ti ti-star-filled text-[10px] text-amber-400"></i>
                                            <span class="text-[10px] text-slate-500">
                                                {Number(product.avg_rating).toFixed(1)}
                                                {#if product.review_count}
                                                    ({product.review_count})
                                                {/if}
                                            </span>
                                        </div>
                                    {/if}
                                </div>
                                <div class="mt-1">
                                    <p
                                        class="text-sm font-black"
                                        style="color: {primary};"
                                    >
                                        {formatPrice(price)}
                                    </p>
                                    {#if isPromo && originalPrice > 0}
                                        <p class="text-[10px] text-slate-400 line-through">
                                            {formatPrice(originalPrice)}
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        </a>

                        <!-- Add to Cart Button -->
                        {#if cartButtonStyle !== 'none'}
                            <div class="px-2.5 pb-2.5">
                                <button
                                    type="button"
                                    onclick={(e) => handleAddToCart(product, e)}
                                    class="w-full py-1.5 text-xs font-bold rounded-lg border transition"
                                    style="border-color: {primary}; color: {primary};"
                                    class:hover:text-white={true}
                                >
                                    + Keranjang
                                </button>
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>

            <!-- Pagination Component -->
            <Pagination
                data={products}
                itemLabel="Produk"
                class="mt-8 flex flex-col sm:flex-row gap-3.5 sm:items-center sm:justify-between py-4 w-full"
            />
        {/if}
    </div>
</StorefrontLayout>

{#if showVariantModal && selectedVariantProduct}
    <VariantSelectorModal
        product={selectedVariantProduct}
        onClose={() => { showVariantModal = false; selectedVariantProduct = null; }}
    />
{/if}
