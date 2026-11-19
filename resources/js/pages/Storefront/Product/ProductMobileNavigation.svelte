<script>
    import { Link, router } from '@inertiajs/svelte';

    let {
        goBack,
        handleSearch,
        searchQuery = $bindable(),
        showCheckoutSettings,
        user,
        cartCount,
        secondary,
        canEditProduct,
        product,
        mobileMenuOpen = $bindable(),
        shareProduct,
        openWhatsapp,
        isFoodPick = false,
    } = $props();
</script>

<!-- MOBILE NAVBAR (Always visible, matching Image 3) -->
<div
    class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-100 shadow-xs py-2.5 px-3"
>
    <div class="flex items-center gap-3">
        <!-- Back Button -->
        <button
            onclick={goBack}
            class="w-8 h-8 flex items-center justify-center text-slate-700 hover:bg-slate-100 rounded-xl transition active:scale-95 shrink-0 cursor-pointer"
            aria-label="Kembali"
        >
            <i class="ti ti-arrow-left text-xl"></i>
        </button>

        <!-- Search Bar -->
        <div class="flex-grow">
            <form onsubmit={handleSearch} class="relative">
                <input
                    type="text"
                    bind:value={searchQuery}
                    placeholder="Cari produk..."
                    class="w-full pl-3.5 pr-9 py-1.5 text-xs bg-slate-100 rounded-xl border border-transparent focus:outline-none focus:bg-white focus:border-slate-300 transition"
                />
                <button
                    type="submit"
                    aria-label="Search"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition flex items-center justify-center p-1"
                >
                    <i class="ti ti-search text-xl"></i>
                </button>
            </form>
        </div>

        <!-- Right Icons: Share, Cart, Menu -->
        <div class="flex items-center gap-1.5 shrink-0">
            <!-- Share Button -->
            <!-- <button
                onclick={shareProduct}
                class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition"
                aria-label="Bagikan"
            >
                <i class="ti ti-share text-lg"></i>
            </button> -->

            <!-- Cart Button -->
            {#if showCheckoutSettings && !user?.is_seller}
                <button
                    onclick={() => {
                        if (user) {
                            router.visit('/cart');
                        } else {
                            window.dispatchEvent(
                                new CustomEvent('open-login-modal'),
                            );
                        }
                    }}
                    class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer active:scale-95 shrink-0"
                    aria-label="Keranjang"
                >
                    <div class="relative">
                        <i class="ti ti-shopping-cart text-xl"></i>
                        {#if cartCount > 0}
                            <span
                                class="absolute -top-1.5 -right-2 w-4 h-4 rounded-full text-[8px] font-black flex items-center justify-center text-white shadow-xs"
                                style="background-color: {secondary}; font-family: sans-serif;"
                            >
                                {cartCount}
                            </span>
                        {/if}
                    </div>
                </button>
            {/if}

            <!-- Edit Product Shortcut (Mobile) -->
            {#if canEditProduct}
                <Link
                    href="/admin/products/{product.id}/edit"
                    class="w-8 h-8 flex items-center justify-center text-amber-600 bg-amber-50 hover:bg-amber-100 rounded-xl transition cursor-pointer active:scale-95 shrink-0 border border-amber-200/80"
                    aria-label="Edit Produk"
                    title="Edit Produk"
                >
                    <i class="ti ti-pencil text-lg"></i>
                </Link>
            {/if}

            <!-- Menu Button -->
            <button
                onclick={() => (mobileMenuOpen = !mobileMenuOpen)}
                class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-xl transition cursor-pointer active:scale-95 shrink-0"
                aria-label="Menu"
            >
                <i class="ti ti-dots-vertical text-xl"></i>
            </button>
        </div>
    </div>
</div>

<!-- Mobile Dropdown Menu -->
{#if mobileMenuOpen}
    <div
        class="fixed inset-0 z-50 md:hidden"
        onclick={() => (mobileMenuOpen = false)}
        role="presentation"
    >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/10"></div>
        <!-- Menu Box -->
        <div
            class="absolute right-4 top-14 w-44 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 overflow-hidden z-10 animate-in zoom-in-95 duration-100"
            onclick={(e) => e.stopPropagation()}
            role="presentation"
        >
            {#if canEditProduct}
                <Link
                    href="/admin/products/{product.id}/edit"
                    class="flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-amber-700 bg-amber-50 hover:bg-amber-100 transition border-b border-slate-100"
                >
                    <i class="ti ti-pencil text-sm text-amber-600"></i>
                    Edit Produk
                </Link>
            {/if}
            <Link
                href="/"
                prefetch
                class="flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
            >
                <i class="ti ti-home text-sm text-slate-400"></i>
                Beranda
            </Link>
            <button
                onclick={() => {
                    mobileMenuOpen = false;
                    shareProduct();
                }}
                class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition text-left"
            >
                <i class="ti ti-share text-sm text-slate-400"></i>
                Bagikan Produk
            </button>
            {#if !isFoodPick}
                <button
                    onclick={() => {
                        mobileMenuOpen = false;
                        openWhatsapp();
                    }}
                    class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition text-left border-t border-slate-100"
                >
                    <i
                        class="ti ti-brand-whatsapp text-sm text-green-500 animate-pulse"
                    ></i>
                    Tanya Penjual
                </button>
            {/if}
        </div>
    </div>
{/if}
