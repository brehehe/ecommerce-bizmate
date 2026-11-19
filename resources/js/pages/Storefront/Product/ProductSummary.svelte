<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    let {
        product,
        canEditProduct,
        shareProduct,
        reviews,
        isSellerEnabled,
        formatDate,
    } = $props();
</script>

<!-- Header: name + rating/terjual -->
<div class="pb-4 flex flex-col">
    <div class="flex items-start justify-between gap-3">
        <h1
            class="text-lg sm:text-xl font-semibold text-slate-800 leading-tight flex items-center gap-2 flex-wrap"
        >
            <span>{product.name}</span>
        </h1>
        <div class="hidden md:flex items-center gap-2 shrink-0">
            {#if canEditProduct}
                <Link
                    href="/admin/products/{product.id}/edit"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200/80 rounded-xl transition cursor-pointer shadow-2xs active:scale-95"
                    title="Edit Produk di Admin / Seller Center"
                >
                    <i class="ti ti-edit text-sm text-amber-600"></i>
                    <span>Edit Produk</span>
                </Link>
            {/if}
            <button
                onclick={shareProduct}
                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer shadow-2xs active:scale-95"
                title="Bagikan Produk"
            >
                <i class="ti ti-share text-base text-slate-500"></i>
                <span>Bagikan</span>
            </button>
        </div>
    </div>
    <!-- Rating / terjual row -->
    <div
        class="flex items-center gap-3 text-xs text-slate-400 flex-wrap mt-1.5"
    >
        {#if reviews.length > 0}
            {@const avgRating =
                reviews.reduce((s: number, r: any) => s + Number(r.rating), 0) /
                reviews.length}
            <span
                class="flex items-center gap-1 border-r border-slate-200 pr-3"
            >
                <i class="ti ti-star-filled text-amber-400 text-xs"></i>
                <span class="font-bold text-slate-700"
                    >{avgRating.toFixed(1)}</span
                >
                <span class="text-slate-400">({reviews.length} ulasan)</span>
            </span>
        {/if}
        {#if !isSellerEnabled}
            <span class="border-r border-slate-200 pr-3"
                >{product.sold_count != null && product.sold_count > 0
                    ? (product.sold_count >= 1000
                          ? (product.sold_count / 1000)
                                .toFixed(1)
                                .replace('.0', '') + 'rb'
                          : product.sold_count) + ' Terjual'
                    : '0 Terjual'}</span
            >
            {#if product.sku}
                <span
                    >SKU: <b class="border-r text-slate-600">{product.sku}</b
                    ></span
                >
            {/if}
        {/if}
        {#if isSellerEnabled && product.created_at}
            <span
                >Tanggal Upload: <b class="text-slate-600"
                    >{formatDate(product.created_at)}</b
                ></span
            >
        {/if}
    </div>
</div>
