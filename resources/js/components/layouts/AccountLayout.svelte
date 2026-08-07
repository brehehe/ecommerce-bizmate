<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let { activeMenu = 'profile', children } = $props();

    const primary = $derived(page.props.theme?.primary_color || '#fa7315');
    const secondary = $derived(page.props.theme?.secondary_color || '#0c4cb4');
    const user = $derived(page.props.auth?.user);
    const isSellerEnabled = $derived(
        (page.props as any).app_config?.is_seller_enabled ??
            (page.props as any).settings?.is_seller_enabled ??
            false,
    );

    let localPreviewUrl = $state<string | null>(null);
    const previewUrl = $derived(
        localPreviewUrl || (user?.avatar ? `/storage/${user.avatar}` : null),
    );
</script>

<StorefrontLayout hideMobileFooter={true}>
    <div class="min-h-dvh bg-slate-50/60 pb-16 font-sans">
        <div class="max-w-6xl mx-auto px-4 py-6 md:py-8">
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
                <!-- ═══════════════════════════════════════════════════
                 LEFT SIDEBAR (Shopee / Tokopedia Style Unified Menu)
                ═══════════════════════════════════════════════════ -->
                <div class="col-span-1 space-y-4">
                    <div
                        class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden p-4 space-y-4"
                    >
                        <!-- User Profile Header -->
                        <div
                            class="flex items-center gap-3 pb-3 border-b border-slate-100"
                        >
                            <Link
                                href="/profile"
                                class="relative group shrink-0"
                            >
                                <div
                                    class="w-11 h-11 rounded-full ring-2 ring-slate-100 overflow-hidden flex items-center justify-center bg-slate-100 shadow-xs"
                                >
                                    {#if previewUrl}
                                        <img
                                            src={previewUrl}
                                            alt="Avatar"
                                            class="w-full h-full object-cover rounded-full"
                                        />
                                    {:else}
                                        <div
                                            class="w-full h-full text-white font-bold text-xs flex items-center justify-center uppercase"
                                            style="background: linear-gradient(135deg, {primary}, {secondary});"
                                        >
                                            {user
                                                ? user.name.substring(0, 2)
                                                : 'CS'}
                                        </div>
                                    {/if}
                                </div>
                            </Link>

                            <div class="min-w-0 flex-1">
                                <h2
                                    class="text-sm font-outfit font-black text-slate-800 truncate leading-snug"
                                >
                                    {user?.name || 'User'}
                                </h2>
                                <Link
                                    href="/profile"
                                    class="text-[11px] font-semibold text-slate-400 hover:text-slate-600 flex items-center gap-1 transition mt-0.5"
                                >
                                    <i class="ti ti-pencil text-xs"></i>
                                    <span>Ubah Profil</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Menu Navigation -->
                        <div class="space-y-1">
                            <!-- Akun Saya Group Header -->
                            <div
                                class="px-2 py-1.5 flex items-center gap-2 font-bold text-xs text-slate-800 uppercase tracking-wider"
                            >
                                <i
                                    class="ti ti-user text-base"
                                    style="color: {primary};"
                                ></i>
                                <span>Akun Saya</span>
                            </div>

                            <!-- Sub-items for Akun Saya -->
                            <div class="space-y-0.5 pl-3">
                                <Link
                                    href="/profile"
                                    class="w-full text-left py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-between
                                           {activeMenu === 'profile'
                                        ? 'text-white shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'}"
                                    style={activeMenu === 'profile'
                                        ? `background-color: ${primary};`
                                        : ''}
                                >
                                    <span>Profil</span>
                                    {#if activeMenu === 'profile'}
                                        <i class="ti ti-check text-xs font-bold"
                                        ></i>
                                    {/if}
                                </Link>

                                <Link
                                    href="/profile/bank-accounts"
                                    class="w-full text-left py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-between
                                           {activeMenu === 'bank-accounts'
                                        ? 'text-white shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'}"
                                    style={activeMenu === 'bank-accounts'
                                        ? `background-color: ${primary};`
                                        : ''}
                                >
                                    <span>Bank & Kartu</span>
                                    {#if activeMenu === 'bank-accounts'}
                                        <i class="ti ti-check text-xs font-bold"
                                        ></i>
                                    {/if}
                                </Link>

                                <Link
                                    href="/profile/addresses"
                                    class="w-full text-left py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-between
                                           {activeMenu === 'addresses'
                                        ? 'text-white shadow-xs font-bold'
                                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'}"
                                    style={activeMenu === 'addresses'
                                        ? `background-color: ${primary};`
                                        : ''}
                                >
                                    <span>Alamat</span>
                                    {#if activeMenu === 'addresses'}
                                        <i class="ti ti-check text-xs font-bold"
                                        ></i>
                                    {/if}
                                </Link>

                                <Link
                                    href="/profile?tab=password"
                                    class="w-full text-left py-2 px-3 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition block"
                                >
                                    Ubah Password
                                </Link>
                            </div>

                            <hr class="border-slate-100 my-2" />

                            <!-- Pesanan Saya -->
                            {#if !isSellerEnabled && !user?.is_seller}
                                <Link
                                    href="/transactions"
                                    class="px-3 py-2 rounded-xl flex items-center justify-between text-xs font-bold transition
                                           {activeMenu === 'transactions'
                                        ? 'text-white shadow-xs font-bold'
                                        : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50'}"
                                    style={activeMenu === 'transactions'
                                        ? `background-color: ${primary};`
                                        : ''}
                                >
                                    <div class="flex items-center gap-2.5">
                                        <i
                                            class="ti ti-clipboard-list text-base {activeMenu ===
                                            'transactions'
                                                ? 'text-white'
                                                : 'text-blue-600'}"
                                        ></i>
                                        <span>Pesanan Saya</span>
                                    </div>
                                    {#if activeMenu === 'transactions'}
                                        <i class="ti ti-check text-xs font-bold"
                                        ></i>
                                    {/if}
                                </Link>

                                <hr class="border-slate-100 my-2" />

                                <Link
                                    href="/refunds"
                                    class="px-3 py-2 rounded-xl flex items-center justify-between text-xs font-bold transition
                                           {activeMenu === 'refunds'
                                        ? 'text-white shadow-xs font-bold'
                                        : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50'}"
                                    style={activeMenu === 'refunds'
                                        ? `background-color: ${primary};`
                                        : ''}
                                >
                                    <div class="flex items-center gap-2.5">
                                        <i
                                            class="ti ti-receipt-refund text-base {activeMenu ===
                                            'refunds'
                                                ? 'text-white'
                                                : 'text-emerald-600'}"
                                        ></i>
                                        <span>Refund</span>
                                    </div>
                                    {#if activeMenu === 'refunds'}
                                        <i class="ti ti-check text-xs font-bold"
                                        ></i>
                                    {/if}
                                </Link>

                                <hr class="border-slate-100 my-2" />

                                <Link
                                    href="/returns"
                                    class="px-3 py-2 rounded-xl flex items-center justify-between text-xs font-bold transition
                                           {activeMenu === 'returns'
                                        ? 'text-white shadow-xs font-bold'
                                        : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50'}"
                                    style={activeMenu === 'returns'
                                        ? `background-color: ${primary};`
                                        : ''}
                                >
                                    <div class="flex items-center gap-2.5">
                                        <i
                                            class="ti ti-rotate-2 text-base {activeMenu ===
                                            'returns'
                                                ? 'text-white'
                                                : 'text-amber-600'}"
                                        ></i>
                                        <span>Retur Barang</span>
                                    </div>
                                    {#if activeMenu === 'returns'}
                                        <i class="ti ti-check text-xs font-bold"
                                        ></i>
                                    {/if}
                                </Link>

                                <hr class="border-slate-100 my-2" />
                            {/if}

                            <!-- Pesan Chat -->
                            <Link
                                href="/chats"
                                class="px-3 py-2 rounded-xl flex items-center gap-2.5 text-xs font-bold text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition"
                            >
                                <i
                                    class="ti ti-message-dots text-base text-emerald-600"
                                ></i>
                                <span>Pesan Chat</span>
                            </Link>

                            <!-- Toko Saya / Mulai Jual Barang Banner Menu -->
                            {#if isSellerEnabled}
                                <hr class="border-slate-100 my-2" />
                                {#if user?.is_seller}
                                    <a
                                        href="/admin/dashboard"
                                        class="px-3 py-2 rounded-xl flex items-center gap-2.5 text-xs font-bold text-amber-800 bg-amber-50/80 hover:bg-amber-100 border border-amber-200/60 transition"
                                    >
                                        <i
                                            class="ti ti-building-store text-base text-amber-600"
                                        ></i>
                                        <span class="truncate"
                                            >{user.store_name ||
                                                'Toko Saya'}</span
                                        >
                                    </a>
                                {:else}
                                    <Link
                                        href="/profile"
                                        class="w-full text-left px-3 py-2 rounded-xl flex items-center gap-2.5 text-xs font-bold text-orange-800 bg-orange-50/80 hover:bg-orange-100 border border-orange-200/60 transition"
                                    >
                                        <i
                                            class="ti ti-building-store text-base text-orange-600"
                                        ></i>
                                        <span>Mulai Jual Barang</span>
                                    </Link>
                                {/if}
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════════════
                 RIGHT MAIN CONTENT
                ═══════════════════════════════════════════════════ -->
                <div class="col-span-1 lg:col-span-3">
                    {@render children()}
                </div>
            </div>
        </div>
    </div>
</StorefrontLayout>
