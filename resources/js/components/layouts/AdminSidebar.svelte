<script lang="ts">
    import { page, router, inertia } from '@inertiajs/svelte';
    import { fade, slide } from 'svelte/transition';

    let { isSidebarOpen = false } = $props();

    let isProfileDropdownOpen = $state(false);
    let isCatalogOpen = $state(false);
    let isMasterDataOpen = $state(false);
    const user = $derived(page.props.auth?.user);

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

    $effect(() => {
        if (isActive('/admin/categories') || isActive('/admin/products')) {
            isCatalogOpen = true;
        }
        if (isActive('/admin/master-data')) {
            isMasterDataOpen = true;
        }
    });
</script>

<aside
    class="fixed inset-y-0 left-0 z-50 w-64 bg-white border-r border-slate-200 flex flex-col transform transition-transform duration-300 shadow-xl lg:shadow-none
  {isSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'}"
>
    <div
        class="h-20 flex flex-col justify-center px-6 border-b border-slate-100 shrink-0"
    >
        <div class="flex items-center gap-3">
            <div
                class="w-10 h-10 rounded-xl shadow-md flex items-center justify-center text-white text-xl"
                style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
            >
                <i class="ti ti-sofa"></i>
            </div>
            <div class="flex flex-col">
                <span
                    class="font-outfit font-black text-xl text-slate-800 tracking-tight leading-none"
                    >bizmate</span
                >
                <span
                    class="font-sans font-bold text-[10px] text-slate-400 tracking-widest mt-1"
                    >ADMIN CONSOLE</span
                >
            </div>
        </div>
    </div>

    <div class="flex-grow overflow-y-auto py-6 px-4 space-y-1 custom-scrollbar">
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
            {#if isActive('/admin/finance')}
                <div
                    class="absolute left-0 top-0 bottom-0 w-1 rounded-r-md"
                    style="background-color: {secondaryColor};"
                ></div>
            {/if}
            <a
                href="#"
                class="flex items-center gap-3 px-4 py-3 mx-2 rounded-xl transition duration-200 group {isActive(
                    '/admin/finance',
                )
                    ? 'bg-slate-50 font-bold'
                    : 'text-slate-600 hover:bg-slate-50 font-semibold'}"
                style={isActive('/admin/finance')
                    ? `color: ${primaryColor};`
                    : ''}
            >
                <i
                    class="ti ti-chart-pie text-xl group-hover:scale-110 transition"
                    style={isActive('/admin/finance')
                        ? `color: ${primaryColor};`
                        : ''}
                ></i>
                <span>Keuangan</span>
            </a>
        </div>

        <div
            class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-4 mb-3 mt-8"
        >
            Store Management
        </div>
        <div class="space-y-1 relative">
            <a
                href="#"
                class="flex items-center justify-between px-4 py-3 mx-2 rounded-xl text-slate-600 hover:text-brand-blueRoyal hover:bg-slate-50 font-semibold transition duration-200 group"
            >
                <div class="flex items-center gap-3">
                    <i
                        class="ti ti-shopping-cart text-xl group-hover:scale-110 transition"
                    ></i>
                    <span>Pesanan</span>
                </div>
                <i class="ti ti-chevron-down text-sm text-slate-400"></i>
            </a>
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
                    <span>Manajemen Toko</span>
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
                    </div>
                {/if}
            </div>
            <a
                href="#"
                class="flex items-center justify-between px-4 py-3 mx-2 rounded-xl text-slate-600 hover:text-brand-blueRoyal hover:bg-slate-50 font-semibold transition duration-200 group"
            >
                <div class="flex items-center gap-3">
                    <i
                        class="ti ti-layout-cards text-xl group-hover:scale-110 transition"
                    ></i>
                    <span>Konten (CMS)</span>
                </div>
                <i class="ti ti-chevron-down text-sm text-slate-400"></i>
            </a>
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
                    href="/admin/settings"
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
                class="w-10 h-10 rounded-full border-2 p-0.5 flex items-center justify-center shrink-0"
                style="border-color: {secondaryColor};"
            >
                <div
                    class="w-full h-full text-white font-bold text-[11px] rounded-full flex items-center justify-center uppercase"
                    style="background-color: {primaryColor};"
                >
                    {user ? user.name.substring(0, 2) : 'AU'}
                </div>
            </div>
            <div class="flex-grow min-w-0">
                <p
                    class="text-sm font-bold text-slate-800 truncate leading-tight"
                >
                    {user ? user.name : 'Admin Utama'}
                </p>
                <p class="text-[10px] text-slate-500 truncate mt-0.5">
                    {user ? user.email : 'superadmin@bizmate.id'}
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
