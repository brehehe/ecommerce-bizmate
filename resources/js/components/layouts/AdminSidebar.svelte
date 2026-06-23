<script lang="ts">
    import { usePage, router, inertia } from '@inertiajs/svelte';
    import { fade, slide } from 'svelte/transition';

    let { isSidebarOpen = false, isTourActive = false } = $props();

    const page = usePage();

    let isProfileDropdownOpen = $state(false);
    let isCatalogOpen = $state(false);
    let isMasterDataOpen = $state(false);
    let isReportsOpen = $state(false);
    let isCmsOpen = $state(false);
    let sidebarContainer = $state<HTMLElement | null>(null);
    const user = $derived(page.props.auth?.user);

    $effect(() => {
        if (user && (window as any).Echo) {
            const channel = (window as any).Echo.private(
                `user.${user.id}`,
            ).listen('.notification.updated', (event: any) => {
                const data = event.data || {};
                if (data.adminChatUnreadCount !== undefined) {
                    (page.props as any).adminChatUnreadCount =
                        data.adminChatUnreadCount;
                }
                if (data.adminNotifications !== undefined) {
                    (page.props as any).adminNotifications =
                        data.adminNotifications;
                }
            });

            return () => {
                (window as any).Echo.leave(`user.${user.id}`);
            };
        }
    });

    const adminNotifications = $derived((page.props as any).adminNotifications);
    const totalNewTransactions = $derived(
        (adminNotifications?.transactionCounts?.belum_bayar || 0) +
            (adminNotifications?.transactionCounts?.menunggu || 0) +
            (adminNotifications?.transactionCounts?.diproses || 0),
    );

    const handleLogout = () => {
        router.post('/logout');
    };

    // Helper to check if a route is active
    const isActive = (path: string) => {
        return page.url.startsWith(path);
    };

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    const storeName = $derived(
        (page.props as any).settings?.store_name || 'Bizmate',
    );
    const storeAppName = $derived(
        (page.props as any).settings?.store_app_name || storeName,
    );
    const storeIcon = $derived(
        (page.props as any).settings?.store_icon || null,
    );
    const fallbackEmail = $derived(
        'superadmin@' +
            storeAppName.toLowerCase().replace(/[^a-z0-9]/g, '') +
            '.id',
    );
    const storeNameFontClass = $derived(
        storeAppName.length <= 10
            ? 'text-xl'
            : storeAppName.length <= 16
              ? 'text-base'
              : 'text-sm',
    );

    $effect(() => {
        if (isTourActive) {
            isCatalogOpen = true;
            isMasterDataOpen = true;
            isReportsOpen = true;
            isCmsOpen = true;
            return;
        }

        if (
            isActive('/admin/categories') ||
            isActive('/admin/products') ||
            isActive('/admin/master-data/brands')
        ) {
            isCatalogOpen = true;
        }
        if (isActive('/admin/master-data')) {
            isMasterDataOpen = true;
        }
        if (page.url.startsWith('/admin/reports')) {
            isReportsOpen = true;
        }
        if (page.url.startsWith('/admin/cms')) {
            isCmsOpen = true;
        }
    });

    $effect(() => {
        // Track page.url and dropdown open states reactively
        const url = page.url;
        const catalog = isCatalogOpen;
        const master = isMasterDataOpen;
        const reports = isReportsOpen;
        const cms = isCmsOpen;

        const timer = setTimeout(() => {
            if (sidebarContainer) {
                const currentPath = url.split('?')[0];
                const anchors = sidebarContainer.querySelectorAll('a[href]');
                let activeAnchor: HTMLAnchorElement | null = null;

                // 1. Try exact path match
                for (const a of anchors) {
                    const htmlAnchor = a as HTMLAnchorElement;
                    const path =
                        htmlAnchor.pathname ||
                        new URL(htmlAnchor.href, window.location.origin)
                            .pathname;
                    if (path === currentPath) {
                        activeAnchor = htmlAnchor;
                        break;
                    }
                }

                // 2. Try prefix match if no exact match (excluding general dashboard/admin paths)
                if (!activeAnchor) {
                    for (const a of anchors) {
                        const htmlAnchor = a as HTMLAnchorElement;
                        const path =
                            htmlAnchor.pathname ||
                            new URL(htmlAnchor.href, window.location.origin)
                                .pathname;
                        if (
                            path &&
                            path !== '/admin' &&
                            path !== '/admin/dashboard' &&
                            currentPath.startsWith(path)
                        ) {
                            activeAnchor = htmlAnchor;
                            break;
                        }
                    }
                }

                if (activeAnchor) {
                    activeAnchor.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                    });
                }
            }
        }, 150);

        return () => clearTimeout(timer);
    });
</script>

<aside
    class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 flex flex-col transform transition-transform duration-300 shadow-xl lg:shadow-none
  {isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}"
>
    <div
        class="h-20 flex flex-col justify-center px-6 border-b border-slate-100 shrink-0"
    >
        <div class="flex items-center gap-3">
            {#if storeIcon}
                <img
                    src={storeIcon}
                    alt="Store Icon"
                    class="w-10 h-10 object-contain"
                />
            {:else}
                <div
                    class="w-10 h-10 rounded-xl shadow-md flex items-center justify-center text-white text-xl"
                    style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
                >
                    <i class="ti ti-sofa"></i>
                </div>
            {/if}
            <div class="flex flex-col">
                <span
                    class="font-outfit font-black {storeNameFontClass} text-slate-800 tracking-tight leading-snug"
                    >{storeAppName}</span
                >
                <span
                    class="font-sans font-bold text-[10px] text-slate-400 tracking-widest mt-1"
                    >ADMIN CONSOLE</span
                >
            </div>
        </div>
    </div>

    <div
        bind:this={sidebarContainer}
        class="flex-grow overflow-y-auto py-6 px-4 space-y-1 custom-scrollbar"
    >
        <div
            class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-4 mb-3 mt-2"
        >
            Main Menu
        </div>

        <div class="relative">
            {#if isActive('/admin/dashboard')}
                <div
                    class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                    style="background-color: {secondaryColor};"
                ></div>
            {/if}
            <a
                href="/admin/dashboard"
                use:inertia
                class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                    '/admin/dashboard',
                )
                    ? 'bg-slate-50 font-bold'
                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                style={isActive('/admin/dashboard')
                    ? `color: ${primaryColor};`
                    : ''}
            >
                <i
                    class="ti ti-layout-dashboard text-xl group-hover:scale-110 transition"
                    style={isActive('/admin/dashboard')
                        ? `color: ${primaryColor};`
                        : ''}
                ></i>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="relative">
            {#if isActive('/admin/chats')}
                <div
                    class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                    style="background-color: {secondaryColor};"
                ></div>
            {/if}
            <a
                href="/admin/chats"
                use:inertia
                class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                    '/admin/chats',
                )
                    ? 'bg-slate-50 font-bold'
                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                style={isActive('/admin/chats')
                    ? `color: ${primaryColor};`
                    : ''}
            >
                <i
                    class="ti ti-message-dots text-xl group-hover:scale-110 transition"
                    style={isActive('/admin/chats')
                        ? `color: ${primaryColor};`
                        : ''}
                ></i>
                <span class="flex-1 flex items-center justify-between min-w-0">
                    <span class="truncate">Chat</span>
                    {#if (page.props as any).adminChatUnreadCount > 0}
                        <span
                            class="px-2 py-0.5 text-[9px] font-black text-white rounded-full leading-none shrink-0"
                            style="background-color: {secondaryColor};"
                        >
                            {(page.props as any).adminChatUnreadCount}
                        </span>
                    {/if}
                </span>
            </a>
        </div>
        <div
            class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-4 mb-3 mt-8"
        >
            Store Management
        </div>
        <div class="space-y-1 relative">
            <div class="relative">
                {#if isActive('/admin/transactions')}
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                        style="background-color: {secondaryColor};"
                    ></div>
                {/if}
                <a
                    href="/admin/transactions"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/transactions',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/transactions')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <i
                        class="ti ti-shopping-cart text-xl group-hover:scale-110 transition"
                        style={isActive('/admin/transactions')
                            ? `color: ${primaryColor};`
                            : ''}
                    ></i>
                    <span class="flex-grow flex items-center justify-between">
                        <span>Transaksi</span>
                        {#if totalNewTransactions > 0}
                            <span
                                class="px-2 py-0.5 text-[9px] font-black text-white rounded-full leading-none shrink-0 font-sans"
                                style="background-color: {secondaryColor};"
                            >
                                {totalNewTransactions}
                            </span>
                        {/if}
                    </span>
                </a>
            </div>
            <!-- Retur Pesanan -->
            <div class="relative">
                {#if isActive('/admin/returns')}
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                        style="background-color: {secondaryColor};"
                    ></div>
                {/if}
                <a
                    href="/admin/returns"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/returns',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/returns')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <i
                        class="ti ti-arrow-back-up text-xl group-hover:scale-110 transition"
                        style={isActive('/admin/returns')
                            ? `color: ${primaryColor};`
                            : ''}
                    ></i>
                    <span class="flex-grow flex items-center justify-between">
                        <span>Retur Pesanan</span>
                        {#if (adminNotifications?.returnCounts?.menunggu_review || 0) > 0}
                            <span
                                class="px-2 py-0.5 text-[9px] font-black text-white rounded-full leading-none shrink-0 font-sans"
                                style="background-color: {secondaryColor};"
                            >
                                {adminNotifications.returnCounts
                                    .menunggu_review}
                            </span>
                        {/if}
                    </span>
                </a>
            </div>
            <!-- Refund Pesanan -->
            <div class="relative">
                {#if isActive('/admin/refunds')}
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                        style="background-color: {secondaryColor};"
                    ></div>
                {/if}
                <a
                    href="/admin/refunds"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/refunds',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/refunds')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <i
                        class="ti ti-receipt-refund text-xl group-hover:scale-110 transition"
                        style={isActive('/admin/refunds')
                            ? `color: ${primaryColor};`
                            : ''}
                    ></i>
                    <span class="flex-grow flex items-center justify-between">
                        <span>Refund Pesanan</span>
                        {#if (adminNotifications?.refundCounts?.menunggu_konfirmasi || 0) > 0}
                            <span
                                class="px-2 py-0.5 text-[9px] font-black text-white rounded-full leading-none shrink-0 font-sans"
                                style="background-color: {secondaryColor};"
                            >
                                {adminNotifications.refundCounts
                                    .menunggu_konfirmasi}
                            </span>
                        {/if}
                    </span>
                </a>
            </div>
            <div class="relative">
                {#if isActive('/admin/stock-movements')}
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                        style="background-color: {secondaryColor};"
                    ></div>
                {/if}
                <a
                    href="/admin/stock-movements"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/stock-movements',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/stock-movements')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <i
                        class="ti ti-arrow-bar-to-down text-xl group-hover:scale-110 transition"
                        style={isActive('/admin/stock-movements')
                            ? `color: ${primaryColor};`
                            : ''}
                    ></i>
                    <span>Stok Keluar</span>
                </a>
            </div>
            <div class="flex flex-col space-y-1">
                <button
                    onclick={() => (isCatalogOpen = !isCatalogOpen)}
                    class="flex items-center justify-between px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/categories',
                    ) || isActive('/admin/products')
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/categories') ||
                    isActive('/admin/products')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <div class="flex items-center gap-3">
                        <i
                            class="ti ti-box text-xl group-hover:scale-110 transition"
                            style={isActive('/admin/categories') ||
                            isActive('/admin/products')
                                ? `color: ${primaryColor};`
                                : ''}
                        ></i>
                        <span>Produk</span>
                    </div>
                    <i
                        class="ti {isCatalogOpen
                            ? 'ti-chevron-up'
                            : 'ti-chevron-down'} text-sm transition-transform"
                        style={isActive('/admin/categories') ||
                        isActive('/admin/products')
                            ? `color: ${primaryColor};`
                            : 'color: #94a3b8;'}
                    ></i>
                </button>

                {#if isCatalogOpen}
                    <div
                        class="pl-7 pr-2 space-y-1"
                        transition:slide={{ duration: 200 }}
                    >
                        <div class="relative">
                            {#if isActive('/admin/categories')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/categories"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/categories',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/categories')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-tags text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/categories')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Kategori</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/master-data/brands')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/master-data/brands"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/master-data/brands',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/master-data/brands')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-tags text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/master-data/brands')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Brand / Merek</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/products')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/products"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/products',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/products')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-list text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/products')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Produk</span>
                            </a>
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Bulk Store Management -->
            <div class="relative">
                {#if page.url.startsWith('/admin/store')}
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                        style="background-color: {secondaryColor};"
                    ></div>
                {/if}
                <a
                    href="/admin/store/prices"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {page.url.startsWith(
                        '/admin/store',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={page.url.startsWith('/admin/store')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <i
                        class="ti ti-building-store text-xl group-hover:scale-110 transition"
                        style={page.url.startsWith('/admin/store')
                            ? `color: ${primaryColor};`
                            : ''}
                    ></i>
                    <span>Manajemen Produk</span>
                </a>
            </div>
            <div class="relative">
                {#if isActive('/admin/promotions')}
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                        style="background-color: {secondaryColor};"
                    ></div>
                {/if}
                <a
                    href="/admin/promotions"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/promotions',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/promotions')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <i
                        class="ti ti-ticket text-xl group-hover:scale-110 transition"
                        style={isActive('/admin/promotions')
                            ? `color: ${primaryColor};`
                            : ''}
                    ></i>
                    <span>Promosi</span>
                </a>
            </div>

            <!-- Laporan Dropdown -->
            <div class="flex flex-col space-y-1">
                <button
                    onclick={() => (isReportsOpen = !isReportsOpen)}
                    class="flex items-center justify-between px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/reports',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/reports')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <div class="flex items-center gap-3">
                        <i
                            class="ti ti-chart-bar text-xl group-hover:scale-110 transition"
                            style={isActive('/admin/reports')
                                ? `color: ${primaryColor};`
                                : ''}
                        ></i>
                        <span>Laporan</span>
                    </div>
                    <i
                        class="ti {isReportsOpen
                            ? 'ti-chevron-up'
                            : 'ti-chevron-down'} text-sm transition-transform"
                        style={isActive('/admin/reports')
                            ? `color: ${primaryColor};`
                            : 'color: #94a3b8;'}
                    ></i>
                </button>

                {#if isReportsOpen}
                    <div
                        class="pl-7 pr-2 space-y-1"
                        transition:slide={{ duration: 200 }}
                    >
                        <div class="relative">
                            {#if isActive('/admin/reports/sales')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/sales"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/sales',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/sales')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-cash text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/reports/sales')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Penjualan</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/reports/products')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/products"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/products',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/products')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-package text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/reports/products')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Penjualan Produk</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/reports/profit-loss')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/profit-loss"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/profit-loss',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/profit-loss')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-calculator text-lg group-hover:scale-110 transition"
                                    style={isActive(
                                        '/admin/reports/profit-loss',
                                    )
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Laba Rugi</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/reports/customers')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/customers"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/customers',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/customers')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-users text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/reports/customers')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Pelanggan</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/reports/stocks')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/stocks"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/stocks',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/stocks')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-archive text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/reports/stocks')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Stok & Inventaris</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/reports/pareto')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/pareto"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/pareto',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/pareto')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-chart-bar text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/reports/pareto')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Analisis Pareto</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/reports/couriers')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/couriers"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/couriers',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/couriers')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-truck text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/reports/couriers')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Kurir & Logistik</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/reports/reviews')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/reports/reviews"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/reports/reviews',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/reports/reviews')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-star text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/reports/reviews')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Ulasan Produk</span>
                            </a>
                        </div>
                    </div>
                {/if}
            </div>
        </div>

        <div
            class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-4 mb-3 mt-8"
        >
            System & Config
        </div>
        <div class="space-y-1 relative">
            <div class="flex flex-col space-y-1">
                <button
                    onclick={() => (isMasterDataOpen = !isMasterDataOpen)}
                    class="flex items-center justify-between px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/master-data',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/master-data')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <div class="flex items-center gap-3">
                        <i
                            class="ti ti-database text-xl group-hover:scale-110 transition"
                            style={isActive('/admin/master-data')
                                ? `color: ${primaryColor};`
                                : ''}
                        ></i>
                        <span>Master Data</span>
                    </div>
                    <i
                        class="ti {isMasterDataOpen
                            ? 'ti-chevron-up'
                            : 'ti-chevron-down'} text-sm transition-transform"
                        style={isActive('/admin/master-data')
                            ? `color: ${primaryColor};`
                            : 'color: #94a3b8;'}
                    ></i>
                </button>

                {#if isMasterDataOpen}
                    <div
                        class="pl-7 pr-2 space-y-1"
                        transition:slide={{ duration: 200 }}
                    >
                        <div class="relative">
                            {#if isActive('/admin/master-data/admins')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/master-data/admins"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/master-data/admins',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/master-data/admins')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-user-shield text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/master-data/admins')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Admin</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/master-data/customers')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/master-data/customers"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/master-data/customers',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/master-data/customers')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-users text-lg group-hover:scale-110 transition"
                                    style={isActive(
                                        '/admin/master-data/customers',
                                    )
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Pelanggan</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/master-data/payment-methods')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/master-data/payment-methods"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/master-data/payment-methods',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive(
                                    '/admin/master-data/payment-methods',
                                )
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-credit-card text-lg group-hover:scale-110 transition"
                                    style={isActive(
                                        '/admin/master-data/payment-methods',
                                    )
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Metode Pembayaran</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/master-data/couriers')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/master-data/couriers"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/master-data/couriers',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/master-data/couriers')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-truck text-lg group-hover:scale-110 transition"
                                    style={isActive(
                                        '/admin/master-data/couriers',
                                    )
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Kurir</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/master-data/social-media')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/master-data/social-media"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/master-data/social-media',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive(
                                    '/admin/master-data/social-media',
                                )
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-share text-lg group-hover:scale-110 transition"
                                    style={isActive(
                                        '/admin/master-data/social-media',
                                    )
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Media Sosial</span>
                            </a>
                        </div>

                        <div class="relative">
                            {#if isActive('/admin/master-data/stickers')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/master-data/stickers"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/master-data/stickers',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/master-data/stickers')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-sticker text-lg group-hover:scale-110 transition"
                                    style={isActive(
                                        '/admin/master-data/stickers',
                                    )
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Stiker Chat</span>
                            </a>
                        </div>
                    </div>
                {/if}
            </div>
            <!-- Konten (CMS) Dropdown -->
            <div class="flex flex-col space-y-1">
                <button
                    onclick={() => (isCmsOpen = !isCmsOpen)}
                    class="flex items-center justify-between px-4 py-3 mx-2 rounded-xl transition duration-200 group {page.url.startsWith(
                        '/admin/cms',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={page.url.startsWith('/admin/cms')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <div class="flex items-center gap-3">
                        <i
                            class="ti ti-layout-cards text-xl group-hover:scale-110 transition"
                            style={page.url.startsWith('/admin/cms')
                                ? `color: ${primaryColor};`
                                : ''}
                        ></i>
                        <span>Konten (CMS)</span>
                    </div>
                    <i
                        class="ti {isCmsOpen
                            ? 'ti-chevron-up'
                            : 'ti-chevron-down'} text-sm transition-transform"
                        style={page.url.startsWith('/admin/cms')
                            ? `color: ${primaryColor};`
                            : 'color: #94a3b8;'}
                    ></i>
                </button>

                {#if isCmsOpen}
                    <div
                        class="pl-7 pr-2 space-y-1"
                        transition:slide={{ duration: 200 }}
                    >
                        <div class="relative">
                            {#if isActive('/admin/cms/banners')}
                                <div
                                    class="absolute left-[-2.75rem] top-0 bottom-0 w-1 rounded-r-md"
                                    style="background-color: {secondaryColor};"
                                ></div>
                            {/if}
                            <a
                                href="/admin/cms/banners"
                                use:inertia
                                class="flex items-center gap-3 px-4 py-2 rounded-xl transition duration-200 group {isActive(
                                    '/admin/cms/banners',
                                )
                                    ? 'bg-slate-50 font-bold'
                                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                                style={isActive('/admin/cms/banners')
                                    ? `color: ${primaryColor};`
                                    : ''}
                            >
                                <i
                                    class="ti ti-photo text-lg group-hover:scale-110 transition"
                                    style={isActive('/admin/cms/banners')
                                        ? `color: ${primaryColor};`
                                        : ''}
                                ></i>
                                <span>Edit Banner</span>
                            </a>
                        </div>
                    </div>
                {/if}
            </div>
            <div class="relative">
                {#if isActive('/admin/settings')}
                    <div
                        class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                        style="background-color: {secondaryColor};"
                    ></div>
                {/if}
                <a
                    href="/admin/settings"
                    use:inertia
                    class="flex items-center justify-between px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                        '/admin/settings',
                    )
                        ? 'bg-slate-50 font-bold'
                        : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                    style={isActive('/admin/settings')
                        ? `color: ${primaryColor};`
                        : ''}
                >
                    <div class="flex items-center gap-3">
                        <i
                            class="ti ti-settings text-xl group-hover:scale-110 transition"
                            style={isActive('/admin/settings')
                                ? `color: ${primaryColor};`
                                : ''}
                        ></i>
                        <span>Pengaturan</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <div class="relative p-4 border-t border-slate-200 shrink-0 bg-white">
        <!-- Dropdown Menu -->
        {#if isProfileDropdownOpen}
            <div
                transition:fade={{ duration: 150 }}
                class="absolute bottom-full left-4 right-4 mb-2 bg-white border border-slate-200 rounded-xl shadow-lg shadow-slate-200/50 py-2 z-50"
            >
                <a
                    href="/admin/profile"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition"
                >
                    <i class="ti ti-user-edit text-lg"></i>
                    Edit Profile
                </a>
                <div class="h-px bg-slate-100 my-1"></div>
                <button
                    onclick={handleLogout}
                    class="w-full flex items-center gap-3 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50 transition text-left"
                >
                    <i class="ti ti-logout text-lg"></i>
                    Logout
                </button>
            </div>
        {/if}

        <button
            onclick={() => (isProfileDropdownOpen = !isProfileDropdownOpen)}
            class="w-full flex items-center gap-3 px-2 py-2 cursor-pointer hover:bg-slate-50 rounded-xl transition text-left"
        >
            <div
                class="w-10 h-10 rounded-full border-2 p-0.5 flex items-center justify-center shrink-0 overflow-hidden"
                style="border-color: {secondaryColor};"
            >
                {#if user?.avatar}
                    <img
                        src="/storage/{user.avatar}"
                        alt={user.name}
                        class="w-full h-full rounded-full object-cover"
                    />
                {:else}
                    <div
                        class="w-full h-full text-white font-bold text-[11px] rounded-full flex items-center justify-center uppercase"
                        style="background-color: {primaryColor};"
                    >
                        {user ? user.name.substring(0, 2) : 'AU'}
                    </div>
                {/if}
            </div>
            <div class="flex-grow min-w-0">
                <p
                    class="text-sm font-bold text-slate-800 truncate leading-tight"
                >
                    {user ? user.name : 'Admin Utama'}
                </p>
                <p class="text-[10px] text-slate-500 truncate mt-0.5">
                    {user ? user.email : fallbackEmail}
                </p>
            </div>
            <i
                class="ti {isProfileDropdownOpen
                    ? 'ti-chevron-down'
                    : 'ti-selector'} text-slate-400 shrink-0 transition-transform"
            ></i>
        </button>
    </div>
</aside>
