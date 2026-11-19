<script>
    import { Link } from '@inertiajs/svelte';

    let { product } = $props();
</script>

<!-- ─────────────────────────────────────────────────────
     BREADCRUMB
───────────────────────────────────────────────────── -->
<div class="bg-white border-b border-slate-100 py-2.5 hidden md:block">
    <nav
        class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-1 text-[11px] sm:text-xs text-slate-400 flex-wrap"
    >
        <Link href="/" prefetch class="hover:text-slate-700 transition"
            >Beranda</Link
        >
        <i class="ti ti-chevron-right text-[10px]"></i>
        {#if product.category}
            <Link
                href="/category/{product.category.slug || product.category.id}"
                prefetch
                class="hover:text-slate-700 transition"
                >{product.category.name}</Link
            >
            <i class="ti ti-chevron-right text-[10px]"></i>
        {/if}
        <span class="text-slate-700 font-medium line-clamp-1"
            >{product.name}</span
        >
    </nav>
</div>
