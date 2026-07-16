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
    const isMembershipEnabled = $derived((page.props as any).settings?.membership_enabled ?? true);
    const isLogisticEnabled = $derived((page.props as any).settings?.logistic_enabled ?? true);

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

            // Also listen to the general admin channel for real-time list/detail page updates
            const adminChannel = (window as any).Echo.private('admin')
                .listen('.transaction.updated', (event: any) => {
                    const pathname = window.location.pathname;
                    if (pathname.startsWith('/admin/transactions') || pathname === '/admin/dashboard') {
                        router.reload();
                    }
                })
                .listen('.refund.updated', (event: any) => {
                    const pathname = window.location.pathname;
                    if (pathname.startsWith('/admin/refunds') || pathname === '/admin/dashboard') {
                        router.reload();
                    }
                })
                .listen('.return.updated', (event: any) => {
                    const pathname = window.location.pathname;
                    if (pathname.startsWith('/admin/returns') || pathname === '/admin/dashboard') {
                        router.reload();
                    }
                });

            return () => {
                (window as any).Echo.leave(`user.${user.id}`);
                (window as any).Echo.leave('admin');
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
            ? 'text-base'
            : storeAppName.length <= 16
              ? 'text-sm'
              : 'text-xs',
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

    const adminChatUnreadCount = $derived(
        (page.props as any).adminChatUnreadCount || 0,
    );
</script>

<!-- ── Sidebar shell ─────────────────────────────────────────── -->
<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-100 bg-white transition-transform duration-300 lg:translate-x-0 lg:shadow-none"
    class:translate-x-0={isSidebarOpen}
    class:-translate-x-full={!isSidebarOpen}
>
    <!-- Brand -->
    <div
        class="flex h-14 shrink-0 items-center gap-3 border-b border-slate-100 px-5"
    >
        {#if storeIcon}
            <img
                src={storeIcon}
                alt={storeAppName}
                class="h-7 w-7 rounded-lg object-contain"
            />
        {:else}
            <div
                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-sm text-white"
                style="background: linear-gradient(135deg, {primaryColor}, {secondaryColor});"
            >
                <i class="ti ti-bolt"></i>
            </div>
        {/if}
        <div class="min-w-0 flex-1">
            <p
                class="truncate font-semibold leading-none text-slate-900 {storeNameFontClass}"
            >
                {storeAppName}
            </p>
            <p
                class="mt-0.5 text-[9px] font-semibold uppercase tracking-widest text-slate-400"
            >
                Admin Console
            </p>
        </div>
    </div>

    <!-- Nav body -->
    <nav
        bind:this={sidebarContainer}
        class="flex-grow overflow-y-auto px-3 py-3 custom-scrollbar"
    >
        <!-- Section: Main -->
        <p
            class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest text-slate-400"
        >
            Menu
        </p>

        {@render NavItem(
            '/admin/dashboard',
            'ti-layout-dashboard',
            'Dashboard',
        )}
        {@render NavItemBadge(
            '/admin/chats',
            'ti-message-dots',
            'Chat',
            adminChatUnreadCount,
        )}
        {@render NavItemBadge(
            '/admin/transactions',
            'ti-receipt',
            'Transaksi',
            totalNewTransactions,
        )}
        {@render NavItem('/admin/refunds', 'ti-cash-banknote-off', 'Refund')}
        {@render NavItem('/admin/returns', 'ti-arrow-back-up', 'Retur')}
        {@render NavItem(
            '/admin/stock-movements',
            'ti-transfer',
            'Stok Masuk/Keluar',
        )}

        <div class="my-3 h-px bg-slate-100"></div>

        <!-- Section: Katalog -->
        <p
            class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest text-slate-400"
        >
            Katalog
        </p>

        <!-- Katalog dropdown -->
        <div class="mb-0.5">
            <button
                type="button"
                onclick={() => (isCatalogOpen = !isCatalogOpen)}
                class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors
                    {isCatalogOpen ||
                isActive('/admin/categories') ||
                isActive('/admin/products') ||
                isActive('/admin/master-data/brands')
                    ? 'font-semibold text-slate-900'
                    : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
            >
                <i
                    class="ti ti-box w-4 text-center text-base
                    {isCatalogOpen ||
                    isActive('/admin/categories') ||
                    isActive('/admin/products') ||
                    isActive('/admin/master-data/brands')
                        ? ''
                        : 'text-slate-400'}"
                    style={isCatalogOpen ||
                    isActive('/admin/categories') ||
                    isActive('/admin/products') ||
                    isActive('/admin/master-data/brands')
                        ? `color: ${primaryColor}`
                        : ''}
                ></i>
                <span class="flex-1 text-left">Katalog Produk</span>
                <i
                    class="ti ti-chevron-right text-xs text-slate-400 transition-transform duration-200
                    {isCatalogOpen ? 'rotate-90' : ''}"
                ></i>
            </button>

            {#if isCatalogOpen}
                <div
                    class="ml-6 mt-0.5 space-y-0.5 border-l border-slate-100 pl-3"
                    transition:slide={{ duration: 150 }}
                >
                    {@render SubNavItem('/admin/categories', 'Kategori')}
                    {@render SubNavItem('/admin/master-data/brands', 'Brand')}
                    {@render SubNavItem('/admin/products', 'Produk')}
                </div>
            {/if}
        </div>

        {@render NavItem(
            '/admin/store/prices',
            'ti-building-store',
            'Toko & Stok',
        )}
        {@render NavItem('/admin/promotions', 'ti-discount', 'Promosi')}

        <div class="my-3 h-px bg-slate-100"></div>

        <!-- Section: Laporan -->
        <p
            class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest text-slate-400"
        >
            Laporan
        </p>

        <div class="mb-0.5">
            <button
                type="button"
                onclick={() => (isReportsOpen = !isReportsOpen)}
                class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors
                    {isReportsOpen || isActive('/admin/reports')
                    ? 'font-semibold text-slate-900'
                    : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
            >
                <i
                    class="ti ti-chart-bar w-4 text-center text-base
                    {isReportsOpen || isActive('/admin/reports')
                        ? ''
                        : 'text-slate-400'}"
                    style={isReportsOpen || isActive('/admin/reports')
                        ? `color: ${primaryColor}`
                        : ''}
                ></i>
                <span class="flex-1 text-left">Laporan</span>
                <i
                    class="ti ti-chevron-right text-xs text-slate-400 transition-transform duration-200
                    {isReportsOpen ? 'rotate-90' : ''}"
                ></i>
            </button>

            {#if isReportsOpen}
                <div
                    class="ml-6 mt-0.5 space-y-0.5 border-l border-slate-100 pl-3"
                    transition:slide={{ duration: 150 }}
                >
                    {@render SubNavItem('/admin/reports/sales', 'Penjualan')}
                    {@render SubNavItem('/admin/reports/products', 'Produk')}
                    {@render SubNavItem(
                        '/admin/reports/customers',
                        'Pelanggan',
                    )}
                    {@render SubNavItem('/admin/reports/couriers', 'Kurir')}
                    {@render SubNavItem(
                        '/admin/reports/profit-loss',
                        'Laba Rugi',
                    )}
                    {@render SubNavItem('/admin/reports/stocks', 'Stok')}
                    {@render SubNavItem('/admin/reports/reviews', 'Ulasan')}
                    {@render SubNavItem('/admin/reports/pareto', 'Pareto')}
                    {@render SubNavItem('/admin/reports/abandoned-carts', 'Keranjang Terbengkalai')}
                    {@render SubNavItem('/admin/reports/vouchers', 'Voucher & Diskon')}
                </div>
            {/if}
        </div>

        <div class="my-3 h-px bg-slate-100"></div>

        <!-- Section: Sistem -->
        <p
            class="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest text-slate-400"
        >
            Sistem
        </p>

        <!-- Master Data dropdown -->
        <div class="mb-0.5">
            <button
                type="button"
                onclick={() => (isMasterDataOpen = !isMasterDataOpen)}
                class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors
                    {isMasterDataOpen || isActive('/admin/master-data')
                    ? 'font-semibold text-slate-900'
                    : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
            >
                <i
                    class="ti ti-database w-4 text-center text-base
                    {isMasterDataOpen || isActive('/admin/master-data')
                        ? ''
                        : 'text-slate-400'}"
                    style={isMasterDataOpen || isActive('/admin/master-data')
                        ? `color: ${primaryColor}`
                        : ''}
                ></i>
                <span class="flex-1 text-left">Master Data</span>
                <i
                    class="ti ti-chevron-right text-xs text-slate-400 transition-transform duration-200
                    {isMasterDataOpen ? 'rotate-90' : ''}"
                ></i>
            </button>

            {#if isMasterDataOpen}
                <div
                    class="ml-6 mt-0.5 space-y-0.5 border-l border-slate-100 pl-3"
                    transition:slide={{ duration: 150 }}
                >
                    {@render SubNavItem('/admin/master-data/admins', 'Admin')}
                    {@render SubNavItem(
                        '/admin/master-data/roles',
                        'Roles & Akses',
                    )}
                    {@render SubNavItem(
                        '/admin/master-data/customers',
                        'Pelanggan',
                    )}
                    {@render SubNavItem('/admin/master-data/couriers', 'Kurir')}
                    {#if isLogisticEnabled}
                        {@render SubNavItem(
                            '/admin/master-data/logistic-api',
                            'Logistik API',
                        )}
                    {/if}
                    {@render SubNavItem(
                        '/admin/master-data/payment-methods',
                        'Metode Bayar',
                    )}
                    {@render SubNavItem(
                        '/admin/master-data/social-media',
                        'Media Sosial',
                    )}
                    {@render SubNavItem(
                        '/admin/master-data/stickers',
                        'Stiker Chat',
                    )}
                    {@render SubNavItem(
                        '/admin/master-data/loyalty-poin',
                        'Loyalty Poin',
                    )}
                    {@render SubNavItem(
                        '/admin/master-data/cost',
                        'Biaya',
                    )}
                </div>
            {/if}
        </div>

        <!-- CMS dropdown -->
        <div class="mb-0.5">
            <button
                type="button"
                onclick={() => (isCmsOpen = !isCmsOpen)}
                class="flex w-full items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors
                    {isCmsOpen || isActive('/admin/cms')
                    ? 'font-semibold text-slate-900'
                    : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
            >
                <i
                    class="ti ti-layout-cards w-4 text-center text-base
                    {isCmsOpen || isActive('/admin/cms')
                        ? ''
                        : 'text-slate-400'}"
                    style={isCmsOpen || isActive('/admin/cms')
                        ? `color: ${primaryColor}`
                        : ''}
                ></i>
                <span class="flex-1 text-left">Konten (CMS)</span>
                <i
                    class="ti ti-chevron-right text-xs text-slate-400 transition-transform duration-200
                    {isCmsOpen ? 'rotate-90' : ''}"
                ></i>
            </button>

            {#if isCmsOpen}
                <div
                    class="ml-6 mt-0.5 space-y-0.5 border-l border-slate-100 pl-3"
                    transition:slide={{ duration: 150 }}
                >
                    {@render SubNavItem(
                        '/admin/cms/banners',
                        'Banner & Slider',
                    )}
                </div>
            {/if}
        </div>

        {@render NavItem('/admin/settings', 'ti-settings-2', 'Pengaturan')}
        {@render NavItem(
            '/zozzuehmqewbobfo',
            'ti-adjustments-horizontal',
            'Konfigurasi App',
        )}

        <!-- Membership -->
        {#if isMembershipEnabled}
            {@render NavItem('/admin/membership/dashboard', 'ti-award', 'Membership')}
        {/if}

        <div class="my-3 h-px bg-slate-100"></div>

        <!-- Storefront link -->
        <a
            href="/"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-2.5 rounded-md px-2 py-2 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-800"
        >
            <i
                class="ti ti-external-link w-4 text-center text-base text-slate-400"
            ></i>
            <span class="flex-1">Lihat Toko</span>
            <i class="ti ti-arrow-up-right text-[10px] text-slate-300"></i>
        </a>
    </nav>

    <!-- User profile footer -->
    <div class="relative shrink-0 border-t border-slate-100 p-3">
        {#if isProfileDropdownOpen}
            <div
                transition:fade={{ duration: 120 }}
                class="absolute bottom-full left-3 right-3 mb-2 overflow-hidden rounded-xl border border-slate-100 bg-white py-1 shadow-lg shadow-slate-200/60"
            >
                <a
                    href="/admin/profile"
                    use:inertia
                    class="flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <i class="ti ti-user-circle text-base text-slate-400"></i>
                    Profil Saya
                </a>
                <div class="mx-4 h-px bg-slate-100"></div>
                <button
                    type="button"
                    onclick={handleLogout}
                    class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-medium text-rose-500 transition-colors hover:bg-rose-50"
                >
                    <i class="ti ti-logout text-base"></i>
                    Keluar
                </button>
            </div>
        {/if}

        <button
            type="button"
            onclick={() => (isProfileDropdownOpen = !isProfileDropdownOpen)}
            class="flex w-full items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-slate-50"
        >
            <!-- Avatar -->
            <div class="relative shrink-0">
                {#if user?.avatar}
                    <img
                        src="/storage/{user.avatar}"
                        alt={user.name}
                        class="h-8 w-8 rounded-full object-cover"
                    />
                {:else}
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-full text-[11px] font-bold uppercase text-white"
                        style="background: linear-gradient(135deg, {primaryColor}, {secondaryColor});"
                    >
                        {user ? user.name.substring(0, 2) : 'AU'}
                    </div>
                {/if}
                <!-- Online dot -->
                <span
                    class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-emerald-400"
                ></span>
            </div>

            <!-- Info -->
            <div class="min-w-0 flex-1 text-left">
                <p
                    class="truncate text-sm font-semibold leading-tight text-slate-800"
                >
                    {user ? user.name : 'Admin Utama'}
                </p>
                <p class="truncate text-[10px] text-slate-400">
                    {user ? user.email : fallbackEmail}
                </p>
            </div>

            <i class="ti ti-selector shrink-0 text-sm text-slate-300"></i>
        </button>
    </div>
</aside>

<!-- ── Snippets ────────────────────────────────────────────── -->

{#snippet NavItem(href: string, icon: string, label: string)}
    {@const active = isActive(href)}
    <a
        {href}
        use:inertia
        class="mb-0.5 flex items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors
            {active
            ? 'font-semibold text-slate-900'
            : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
    >
        <i
            class="ti {icon} w-4 text-center text-base {active
                ? ''
                : 'text-slate-400'}"
            style={active ? `color: ${primaryColor}` : ''}
        ></i>
        <span class="flex-1 truncate">{label}</span>
        {#if active}
            <span
                class="h-1.5 w-1.5 shrink-0 rounded-full"
                style="background-color: {primaryColor};"
            ></span>
        {/if}
    </a>
{/snippet}

{#snippet NavItemBadge(
    href: string,
    icon: string,
    label: string,
    count: number,
)}
    {@const active = isActive(href)}
    <a
        {href}
        use:inertia
        class="mb-0.5 flex items-center gap-2.5 rounded-md px-2 py-2 text-sm transition-colors
            {active
            ? 'font-semibold text-slate-900'
            : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
    >
        <i
            class="ti {icon} w-4 text-center text-base {active
                ? ''
                : 'text-slate-400'}"
            style={active ? `color: ${primaryColor}` : ''}
        ></i>
        <span class="flex-1 truncate">{label}</span>
        {#if count > 0}
            <span
                class="flex h-4.5 min-w-[18px] items-center justify-center rounded-full px-1 text-[10px] font-bold text-white"
                style="background-color: {secondaryColor};"
            >
                {count > 99 ? '99+' : count}
            </span>
        {:else if active}
            <span
                class="h-1.5 w-1.5 shrink-0 rounded-full"
                style="background-color: {primaryColor};"
            ></span>
        {/if}
    </a>
{/snippet}

{#snippet SubNavItem(href: string, label: string)}
    {@const active = isActive(href)}
    <a
        {href}
        use:inertia
        class="block rounded-md px-2 py-1.5 text-sm transition-colors
            {active
            ? 'font-semibold'
            : 'font-medium text-slate-500 hover:bg-slate-50 hover:text-slate-800'}"
        style={active ? `color: ${primaryColor};` : ''}
    >
        {label}
    </a>
{/snippet}

<style>
    :global(.custom-scrollbar::-webkit-scrollbar) {
        width: 3px;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-track) {
        background: transparent;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb) {
        background: #e2e8f0;
        border-radius: 99px;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb:hover) {
        background: #cbd5e1;
    }
</style>
