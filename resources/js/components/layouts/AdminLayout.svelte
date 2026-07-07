<script lang="ts">
    import { untrack } from 'svelte';
    import { usePage, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import AdminSidebar from './AdminSidebar.svelte';
    import OfflineDetector from '@/components/OfflineDetector.svelte';

    let { children } = $props();

    const page = usePage();

    // Module-level Set so it persists across SPA navigations and component re-mounts.
    // This prevents the same flash id from showing twice even if Inertia
    // triggers a prop update more than once per navigation.
    const shownFlashIds = new Set<string>();

    $effect(() => {
        const flash = (page.props as any).flash;

        // Read the id reactively to track it, then check/mutate inside untrack
        // so the Set mutation never triggers a re-run of this effect.
        const flashId: string | undefined = flash?.id;

        untrack(() => {
            if (!flash || !flashId || shownFlashIds.has(flashId)) return;

            shownFlashIds.add(flashId);

            if (flash.success) showToast(flash.success, 'success');
            if (flash.error) showToast(flash.error, 'error');
            if (flash.warning) showToast(flash.warning, 'error');
        });
    });

    const storeName = $derived(
        (page.props as any).settings?.store_name || 'Bizmate',
    );
    const storeAppName = $derived(
        (page.props as any).settings?.store_app_name || storeName,
    );

    let isSidebarOpen = $state(false);
    let isNotifOpen = $state(false);

    function toggleSidebar() {
        isSidebarOpen = !isSidebarOpen;
    }

    const adminNotifications = $derived((page.props as any).adminNotifications);
    const lowStockCount = $derived(adminNotifications?.lowStockCount || 0);
    const outOfStockCount = $derived(adminNotifications?.outOfStockCount || 0);
    const totalStockAlerts = $derived(lowStockCount + outOfStockCount);
    const notificationsList = $derived(adminNotifications?.notifications || []);
    const unreadAdminNotifCount = $derived(
        notificationsList.filter((n: any) => !n.is_read).length,
    );
    const totalUnreadCount = $derived(totalStockAlerts + unreadAdminNotifCount);

    import { router } from '@inertiajs/svelte';

    function handleNotificationClick(notif: any) {
        isNotifOpen = false;
        if (!notif.is_read) {
            router.post(
                `/notifications/${notif.id}/read`,
                {},
                {
                    preserveScroll: true,
                    onFinish: () => {
                        if (notif.url) {
                            router.visit(notif.url);
                        }
                    },
                },
            );
        } else if (notif.url) {
            router.visit(notif.url);
        }
    }

    function markAllAsRead() {
        router.post(
            '/notifications/read-all',
            { type: 'admin' },
            { preserveScroll: true },
        );
    }

    // Guided Setup Tour
    let currentTourStep = $state(0);
    let forceShowTour = $state(
        typeof window !== 'undefined'
            ? localStorage.getItem('force_show_tour') === 'true'
            : false,
    );

    const setupTourCompletedInDb = $derived(
        (page.props as any).settings?.setup_tour_completed || false,
    );
    const showTour = $derived(
        (!setupTourCompletedInDb || forceShowTour) &&
            currentTourStep > 0 &&
            typeof window !== 'undefined' &&
            window.location.pathname === tourSteps[currentTourStep - 1]?.url,
    );

    const tourSteps = [
        {
            title: '1. Dashboard Utama',
            description:
                'Konsol utama untuk memantau performa toko Anda. Di sini Anda bisa melihat ringkasan omset harian, jumlah pesanan baru, statistik stok kritis, serta aktivitas penjualan real-time.',
            url: '/admin/dashboard',
        },
        {
            title: '2. Chat Customer',
            description:
                'Hubungi dan berinteraksi langsung dengan pelanggan Anda secara real-time. Membantu menjawab pertanyaan produk, konsultasi, dan menyelesaikan komplain dengan cepat.',
            url: '/admin/chats',
        },
        {
            title: '3. Kelola Pesanan & Transaksi',
            description:
                'Daftar lengkap transaksi pesanan masuk. Anda dapat mengonfirmasi bukti pembayaran bank, mengemas pesanan, mencetak label kirim, hingga menginput nomor resi.',
            url: '/admin/transactions',
        },
        {
            title: '4. Pencatatan Stok Keluar',
            description:
                'Mencatat setiap pengurangan stok produk secara manual (seperti barang rusak, kadaluarsa, kehilangan, atau pemakaian internal) di luar pesanan reguler.',
            url: '/admin/stock-movements',
        },
        {
            title: '5. Kategori Produk',
            description:
                'Mengelompokkan produk Anda ke dalam kategori terstruktur (seperti Sofa, Meja, Lemari) agar memudahkan customer menavigasi katalog toko Anda.',
            url: '/admin/categories',
        },
        {
            title: '6. Brand / Merek',
            description:
                'Mengelola daftar brand/merek produk Anda. Customer dapat memfilter produk berdasarkan brand favorit mereka di halaman storefront.',
            url: '/admin/master-data/brands',
        },
        {
            title: '7. Katalog Produk',
            description:
                'Daftar produk lengkap Anda. Di sini Anda dapat menambah produk baru, mengunggah foto, menentukan deskripsi, harga, berat, dan status tayang di toko.',
            url: '/admin/products',
        },
        {
            title: '8. Manajemen Produk',
            description:
                'Kelola harga jual, harga promo coret, stok barang, dan persediaan pengaman minimum secara massal untuk efisiensi operasional harian.',
            url: '/admin/store/prices',
        },
        {
            title: '9. Promosi & Voucher',
            description:
                'Buat promo menarik seperti Flash Sale berbatas waktu, voucher potongan harga, diskon produk, atau bonus gratis ongkir untuk menarik minat pembeli.',
            url: '/admin/promotions',
        },
        {
            title: '10. Laporan Penjualan',
            description:
                'Grafik dan analisis omset penjualan toko. Anda dapat melihat performa pendapatan harian, bulanan, tahunan, serta filter total pesanan selesai.',
            url: '/admin/reports/sales',
        },
        {
            title: '11. Laporan Produk Terlaris',
            description:
                'Menganalisis produk terlaris (top selling) dan barang dengan volume penjualan tertinggi untuk menentukan prioritas restock barang.',
            url: '/admin/reports/products',
        },
        {
            title: '12. Laporan Laba Rugi',
            description:
                'Ringkasan performa keuangan bersih Anda. Menghitung total omset kotor dikurangi harga pokok penjualan (HPP) untuk mendapatkan laba bersih riil.',
            url: '/admin/reports/profit-loss',
        },
        {
            title: '13. Laporan Aktivitas Pelanggan',
            description:
                'Daftar data pelanggan yang paling loyal dan aktif berbelanja, beserta total akumulasi transaksi belanja mereka di toko Anda.',
            url: '/admin/reports/customers',
        },
        {
            title: '14. Laporan Stok & Inventaris',
            description:
                'Pantau persediaan produk secara menyeluruh. Sistem otomatis mendeteksi barang dengan stok menipis atau habis agar Anda dapat segera order ulang.',
            url: '/admin/reports/stocks',
        },
        {
            title: '15. Laporan Kurir & Logistik',
            description:
                'Analisis performa kurir pengiriman (RajaOngkir, Kurir Toko, Ambil di Toko) serta log pengantaran pesanan real-time.',
            url: '/admin/reports/couriers',
        },
        {
            title: '16. Master Data Admin',
            description:
                'Mengatur akun dan hak akses tim operasional toko Anda. Anda dapat mendaftarkan akun Admin Toko, Admin Penjualan, atau Super Admin.',
            url: '/admin/master-data/admins',
        },
        {
            title: '17. Master Data Pelanggan',
            description:
                'Kelola seluruh basis data pelanggan terdaftar. Anda dapat menonaktifkan akun pembeli, melihat data kontak, email, dan tanggal mendaftar.',
            url: '/admin/master-data/customers',
        },
        {
            title: '18. Metode Pembayaran',
            description:
                'Konfigurasikan rekening bank toko Anda untuk transfer manual, atau hubungkan dengan payment gateway untuk verifikasi transaksi otomatis.',
            url: '/admin/master-data/payment-methods',
        },
        {
            title: '19. Jasa Kurir Pengiriman',
            description:
                'Aktifkan opsi ekspedisi pengiriman resmi (seperti JNE, POS, J&T, SiCepat) untuk perhitungan tarif ongkos kirim otomatis berdasarkan berat.',
            url: '/admin/master-data/couriers',
        },
        {
            title: '20. CMS / Banner Promosi',
            description:
                'Percantik tampilan halaman depan toko online Anda dengan mengunggah banner promosi, gambar slide utama, atau info promo terbaru.',
            url: '/admin/cms/banners',
        },
        {
            title: '21. Pengaturan Toko',
            description:
                'Pusat konfigurasi utama toko. Atur nama toko, logo resmi, favicon, email CS, nomor WhatsApp, alamat koordinat peta, hingga tarif pajak.',
            url: '/admin/settings',
        },
    ];

    function syncTourStepWithUrl() {
        const path = window.location.pathname;

        // Skip syncing if we are at the final step which is on the dashboard to avoid resetting to step 1
        if (
            currentTourStep === tourSteps.length &&
            path === '/admin/dashboard'
        ) {
            return;
        }

        const mappings = [
            { urls: ['/admin/dashboard'], step: 1 },
            { urls: ['/admin/chats'], step: 2 },
            { urls: ['/admin/transactions'], step: 3 },
            { urls: ['/admin/stock-movements'], step: 4 },
            { urls: ['/admin/categories'], step: 5 },
            { urls: ['/admin/master-data/brands'], step: 6 },
            { urls: ['/admin/products'], step: 7 },
            { urls: ['/admin/store'], step: 8 },
            { urls: ['/admin/promotions'], step: 9 },
            { urls: ['/admin/reports/sales'], step: 10 },
            { urls: ['/admin/reports/products'], step: 11 },
            { urls: ['/admin/reports/profit-loss'], step: 12 },
            { urls: ['/admin/reports/customers'], step: 13 },
            { urls: ['/admin/reports/stocks'], step: 14 },
            { urls: ['/admin/reports/couriers'], step: 15 },
            { urls: ['/admin/master-data/admins'], step: 16 },
            { urls: ['/admin/master-data/customers'], step: 17 },
            { urls: ['/admin/master-data/payment-methods'], step: 18 },
            { urls: ['/admin/master-data/couriers', '/admin/master-data/logistic-api'], step: 19 },
            { urls: ['/admin/cms/banners'], step: 20 },
            { urls: ['/admin/settings'], step: 21 },
        ];

        const found = mappings.find((item) =>
            item.urls.some((url) => path.startsWith(url)),
        );

        if (found) {
            currentTourStep = found.step;
        }
    }

    $effect(() => {
        // Auto start the tour if not completed in database OR if manually forced
        if (currentTourStep === 0) {
            if (!setupTourCompletedInDb && !forceShowTour) {
                currentTourStep = 1;
            } else if (forceShowTour) {
                currentTourStep = 1;
            }
        }
    });

    $effect(() => {
        // Track the current URL dynamically in Inertia SPA to sync steps
        const path = page.url;
        if (currentTourStep > 0) {
            syncTourStepWithUrl();
        }
    });

    function handleTourNext() {
        if (currentTourStep < tourSteps.length) {
            currentTourStep++;
            navigateToTourStep(currentTourStep);
        } else {
            // Last step finished: save to database and redirect to dashboard
            if (typeof window !== 'undefined') {
                localStorage.removeItem('force_show_tour');
            }
            forceShowTour = false;
            currentTourStep = 0;
            router.post('/admin/settings/tour-complete');
        }
    }

    function handleTourPrev() {
        if (currentTourStep > 1) {
            currentTourStep--;
            navigateToTourStep(currentTourStep);
        }
    }

    function navigateToTourStep(step: number) {
        if (step === 1) {
            router.visit('/admin/dashboard');
        } else if (step === 2) {
            router.visit('/admin/chats');
        } else if (step === 3) {
            router.visit('/admin/transactions');
        } else if (step === 4) {
            router.visit('/admin/stock-movements');
        } else if (step === 5) {
            router.visit('/admin/categories');
        } else if (step === 6) {
            router.visit('/admin/master-data/brands');
        } else if (step === 7) {
            router.visit('/admin/products');
        } else if (step === 8) {
            router.visit('/admin/store/prices');
        } else if (step === 9) {
            router.visit('/admin/promotions');
        } else if (step === 10) {
            router.visit('/admin/reports/sales');
        } else if (step === 11) {
            router.visit('/admin/reports/products');
        } else if (step === 12) {
            router.visit('/admin/reports/profit-loss');
        } else if (step === 13) {
            router.visit('/admin/reports/customers');
        } else if (step === 14) {
            router.visit('/admin/reports/stocks');
        } else if (step === 15) {
            router.visit('/admin/reports/couriers');
        } else if (step === 16) {
            router.visit('/admin/master-data/admins');
        } else if (step === 17) {
            router.visit('/admin/master-data/customers');
        } else if (step === 18) {
            router.visit('/admin/master-data/payment-methods');
        } else if (step === 19) {
            router.visit('/admin/master-data/couriers');
        } else if (step === 20) {
            router.visit('/admin/cms/banners');
        } else if (step === 21) {
            router.visit('/admin/settings');
        }
    }

    function skipTour() {
        if (typeof window !== 'undefined') {
            localStorage.removeItem('force_show_tour');
        }
        forceShowTour = false;
        currentTourStep = 0;
        router.post('/admin/settings/tour-complete');
    }

    function restartTour() {
        if (typeof window !== 'undefined') {
            localStorage.setItem('force_show_tour', 'true');
        }
        forceShowTour = true;
        currentTourStep = 1;
        router.visit('/admin/dashboard');
    }
</script>

<div
    class="min-h-screen flex bg-slate-50 font-sans"
    style="--color-brand-blueRoyal: {page.props.theme?.primary_color || '#0c4cb4'}; --color-brand-orange: {page.props.theme?.secondary_color || '#fa7315'};"
>
    <!-- Mobile overlay -->
    {#if isSidebarOpen}
        <div
            class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm lg:hidden"
            onclick={toggleSidebar}
            onkeydown={(e) => e.key === 'Escape' && toggleSidebar()}
            role="button"
            tabindex="0"
        ></div>
    {/if}

    <AdminSidebar {isSidebarOpen} isTourActive={showTour} />

    <!-- Main content -->
    <div class="flex min-h-screen w-full flex-col lg:pl-64">

        <!-- Topbar -->
        <header class="sticky top-0 z-30 flex h-14 items-center gap-4 border-b border-slate-100 bg-white/95 px-4 backdrop-blur-xl sm:px-6">

            <!-- Left: hamburger + title -->
            <div class="flex flex-1 items-center gap-3 min-w-0">
                <button
                    type="button"
                    onclick={toggleSidebar}
                    aria-label="Toggle sidebar"
                    class="lg:hidden flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                >
                    <i class="ti ti-menu-2 text-lg"></i>
                </button>

                <div class="hidden items-center gap-2 sm:flex">
                    <span class="text-xs font-semibold text-slate-400">{storeAppName}</span>
                    <span class="text-slate-200">/</span>
                    <span class="text-sm font-semibold text-slate-800">Admin</span>
                </div>
            </div>

            <!-- Right: actions -->
            <div class="flex items-center gap-1 shrink-0">

                <!-- Storefront link -->
                <a
                    href="/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hidden sm:flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    Toko
                    <i class="ti ti-external-link text-[11px] text-slate-400"></i>
                </a>

                <!-- Tour button -->
                <button
                    onclick={restartTour}
                    class="hidden sm:flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Panduan setup"
                    title="Panduan Setup"
                >
                    <i class="ti ti-help text-base"></i>
                </button>

                <!-- Notification bell -->
                <div class="relative">
                    <button
                        onclick={() => (isNotifOpen = !isNotifOpen)}
                        class="relative flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Notifikasi"
                    >
                        <i class="ti ti-bell text-base"></i>
                        {#if totalUnreadCount > 0}
                            <span
                                class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full border-2 border-white text-[9px] font-bold text-white"
                                style="background-color: var(--color-brand-orange);"
                            >
                                {totalUnreadCount > 9 ? '9+' : totalUnreadCount}
                            </span>
                        {/if}
                    </button>

                    {#if isNotifOpen}
                        <div
                            class="fixed inset-0 z-40"
                            onclick={() => (isNotifOpen = false)}
                            role="presentation"
                        ></div>

                        <div class="absolute right-0 top-10 z-50 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
                            <!-- Header -->
                            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-slate-800">Notifikasi</h3>
                                    {#if totalUnreadCount > 0}
                                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">
                                            {totalUnreadCount}
                                        </span>
                                    {/if}
                                </div>
                                {#if unreadAdminNotifCount > 0}
                                    <button
                                        onclick={markAllAsRead}
                                        class="text-xs font-medium text-slate-500 transition-colors hover:text-slate-800"
                                    >
                                        Tandai dibaca
                                    </button>
                                {/if}
                            </div>

                            <div class="max-h-[380px] overflow-y-auto custom-scrollbar">
                                <!-- Notifications list -->
                                {#if notificationsList.length > 0}
                                    {#each notificationsList as notif}
                                        <button
                                            onclick={() => handleNotificationClick(notif)}
                                            class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 {!notif.is_read ? 'bg-blue-50/30' : ''}"
                                        >
                                            <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {
                                                notif.type === 'new_order' ? 'bg-emerald-50 text-emerald-600' :
                                                notif.type === 'payment_proof' ? 'bg-blue-50 text-blue-600' :
                                                notif.type === 'out_of_stock' ? 'bg-rose-50 text-rose-600' :
                                                notif.type === 'low_stock' ? 'bg-amber-50 text-amber-600' :
                                                'bg-slate-100 text-slate-500'
                                            }">
                                                <i class="ti text-sm {
                                                    notif.type === 'new_order' ? 'ti-package' :
                                                    notif.type === 'payment_proof' ? 'ti-credit-card' :
                                                    notif.type === 'out_of_stock' ? 'ti-alert-triangle' :
                                                    notif.type === 'low_stock' ? 'ti-alert-circle' :
                                                    'ti-bell'
                                                }"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-semibold text-slate-800 truncate">{notif.title}</p>
                                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{notif.message}</p>
                                                <p class="mt-1 text-[10px] text-slate-400">{notif.created_at}</p>
                                            </div>
                                            {#if !notif.is_read}
                                                <div class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background-color: var(--color-brand-orange);"></div>
                                            {/if}
                                        </button>
                                    {/each}
                                {/if}

                                <!-- Out of stock -->
                                {#if outOfStockCount > 0}
                                    <div class="border-t border-slate-100 px-4 py-3">
                                        <p class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-rose-500">
                                            <i class="ti ti-alert-triangle"></i>
                                            Stok Habis ({outOfStockCount})
                                        </p>
                                        <div class="space-y-1.5">
                                            {#each adminNotifications.outOfStockItems as item}
                                                <button
                                                    onclick={() => { isNotifOpen = false; router.visit(`/admin/store/stocks?search=${encodeURIComponent(item.name.split(' (')[0])}`); }}
                                                    class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-rose-50"
                                                >
                                                    <span class="truncate font-medium text-slate-700">{item.name}</span>
                                                    <span class="shrink-0 rounded-md bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-600">Habis</span>
                                                </button>
                                            {/each}
                                        </div>
                                    </div>
                                {/if}

                                <!-- Low stock -->
                                {#if lowStockCount > 0}
                                    <div class="border-t border-slate-100 px-4 py-3">
                                        <p class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider text-amber-500">
                                            <i class="ti ti-alert-circle"></i>
                                            Stok Menipis ({lowStockCount})
                                        </p>
                                        <div class="space-y-1.5">
                                            {#each adminNotifications.lowStockItems as item}
                                                <button
                                                    onclick={() => { isNotifOpen = false; router.visit(`/admin/store/stocks?search=${encodeURIComponent(item.name.split(' (')[0])}`); }}
                                                    class="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors hover:bg-amber-50"
                                                >
                                                    <span class="truncate font-medium text-slate-700">{item.name}</span>
                                                    <span class="shrink-0 rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-600">Sisa {item.stock}</span>
                                                </button>
                                            {/each}
                                        </div>
                                    </div>
                                {/if}

                                {#if notificationsList.length === 0 && totalStockAlerts === 0}
                                    <div class="flex flex-col items-center justify-center py-10 text-center">
                                        <i class="ti ti-bell-off text-2xl text-slate-300 mb-2"></i>
                                        <p class="text-xs text-slate-400">Tidak ada notifikasi</p>
                                    </div>
                                {/if}
                            </div>

                            {#if totalStockAlerts > 0}
                                <div class="border-t border-slate-100 px-4 py-2.5 text-center">
                                    <Link
                                        href="/admin/store/stocks"
                                        onclick={() => (isNotifOpen = false)}
                                        class="text-xs font-medium text-slate-500 transition-colors hover:text-slate-800"
                                    >
                                        Lihat semua stok
                                    </Link>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>

            </div>
        </header>

        <!-- Page content -->
        {@render children()}

    </div>

    <!-- Guided Setup Tour -->
    {#if showTour && currentTourStep > 0}
        <div class="fixed bottom-5 right-5 z-50 w-[340px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl shadow-slate-300/40">
            <!-- Tour header -->
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-4 py-3">
                <div class="flex items-center gap-2">
                    <span class="rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-widest text-white" style="background-color: var(--color-brand-orange);">Setup</span>
                    <span class="text-xs font-medium text-slate-500">Langkah {currentTourStep} / {tourSteps.length}</span>
                </div>
                <button onclick={skipTour} class="text-xs text-slate-400 transition-colors hover:text-slate-600">Lewati</button>
            </div>

            <!-- Progress -->
            <div class="h-0.5 bg-slate-100">
                <div
                    class="h-full transition-all duration-300"
                    style="width: {(currentTourStep / tourSteps.length) * 100}%; background-color: var(--color-brand-orange);"
                ></div>
            </div>

            <!-- Content -->
            <div class="p-4">
                <h4 class="mb-1.5 text-sm font-semibold text-slate-800">{tourSteps[currentTourStep - 1].title}</h4>
                <p class="text-xs leading-relaxed text-slate-500">{tourSteps[currentTourStep - 1].description}</p>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-4 py-3">
                {#if currentTourStep > 1}
                    <button
                        onclick={handleTourPrev}
                        class="rounded-md px-3 py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                    >
                        Kembali
                    </button>
                {/if}
                <button
                    onclick={handleTourNext}
                    class="flex items-center gap-1 rounded-md px-4 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                    style="background-color: var(--color-brand-orange);"
                >
                    {currentTourStep === tourSteps.length ? 'Selesai' : 'Lanjutkan'}
                    <i class="ti ti-chevron-right text-[11px]"></i>
                </button>
            </div>
        </div>
    {/if}

    <OfflineDetector />
</div>

<style>
    :global(.custom-scrollbar::-webkit-scrollbar) { width: 3px; }
    :global(.custom-scrollbar::-webkit-scrollbar-track) { background: transparent; }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb) { background: #e2e8f0; border-radius: 99px; }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb:hover) { background: #cbd5e1; }
</style>
