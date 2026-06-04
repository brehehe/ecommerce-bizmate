<script lang="ts">
    import { onMount } from 'svelte';
    import { usePage, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import AdminSidebar from './AdminSidebar.svelte';
    import OfflineDetector from '@/components/OfflineDetector.svelte';

    let { children } = $props();

    const page = usePage();
    const shownFlashIds = new Set();

    $effect(() => {
        const flash = (page.props as any).flash;
        if (!flash || !flash.id || shownFlashIds.has(flash.id)) return;

        let showed = false;
        if (flash.success) {
            showToast(flash.success, 'success');
            showed = true;
        }
        if (flash.error) {
            showToast(flash.error, 'error');
            showed = true;
        }
        if (flash.warning) {
            showToast(flash.warning, 'error');
            showed = true;
        }

        if (showed) {
            shownFlashIds.add(flash.id);
        }
    });

    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');

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
                { preserveScroll: true },
            );
        }
        if (notif.url) {
            router.visit(notif.url);
        }
    }

    function markAllAsRead() {
        router.post('/notifications/read-all', {}, { preserveScroll: true });
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
        (!setupTourCompletedInDb || forceShowTour) && currentTourStep > 0,
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
            title: '8. Manajemen Toko',
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
            title: '15. Master Data Admin',
            description:
                'Mengatur akun dan hak akses tim operasional toko Anda. Anda dapat mendaftarkan akun Admin Toko, Admin Penjualan, atau Super Admin.',
            url: '/admin/master-data/admins',
        },
        {
            title: '16. Master Data Pelanggan',
            description:
                'Kelola seluruh basis data pelanggan terdaftar. Anda dapat menonaktifkan akun pembeli, melihat data kontak, email, dan tanggal mendaftar.',
            url: '/admin/master-data/customers',
        },
        {
            title: '17. Metode Pembayaran',
            description:
                'Konfigurasikan rekening bank toko Anda untuk transfer manual, atau hubungkan dengan payment gateway untuk verifikasi transaksi otomatis.',
            url: '/admin/master-data/payment-methods',
        },
        {
            title: '18. Jasa Kurir Pengiriman',
            description:
                'Aktifkan opsi ekspedisi pengiriman resmi (seperti JNE, POS, J&T, SiCepat) untuk perhitungan tarif ongkos kirim otomatis berdasarkan berat.',
            url: '/admin/master-data/couriers',
        },
        {
            title: '19. CMS / Banner Promosi',
            description:
                'Percantik tampilan halaman depan toko online Anda dengan mengunggah banner promosi, gambar slide utama, atau info promo terbaru.',
            url: '/admin/cms/banners',
        },
        {
            title: '20. Pengaturan Toko',
            description:
                'Pusat konfigurasi utama toko. Atur nama toko, logo resmi, favicon, email CS, nomor WhatsApp, alamat koordinat peta, hingga tarif pajak.',
            url: '/admin/settings',
        },
    ];

    function syncTourStepWithUrl() {
        const path = window.location.pathname;

        // Skip syncing if we are at the final step which is on the dashboard to avoid resetting to step 1
        if (currentTourStep === tourSteps.length && path === '/admin/dashboard') {
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
            { urls: ['/admin/master-data/admins'], step: 15 },
            { urls: ['/admin/master-data/customers'], step: 16 },
            { urls: ['/admin/master-data/payment-methods'], step: 17 },
            { urls: ['/admin/master-data/couriers'], step: 18 },
            { urls: ['/admin/cms/banners'], step: 19 },
            { urls: ['/admin/settings'], step: 20 },
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
            router.visit('/admin/master-data/admins');
        } else if (step === 16) {
            router.visit('/admin/master-data/customers');
        } else if (step === 17) {
            router.visit('/admin/master-data/payment-methods');
        } else if (step === 18) {
            router.visit('/admin/master-data/couriers');
        } else if (step === 19) {
            router.visit('/admin/cms/banners');
        } else if (step === 20) {
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
    class="min-h-screen flex selection:bg-brand-blueRoyal selection:text-white bg-brand-slateBg font-sans"
    style="--color-brand-blueRoyal: {page.props.theme?.primary_color ||
        '#0c4cb4'}; --color-brand-orange: {page.props.theme?.secondary_color ||
        '#fa7315'};"
>
    <!-- Overlay for mobile sidebar -->
    {#if isSidebarOpen}
        <div
            class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden backdrop-blur-sm"
            onclick={toggleSidebar}
            onkeydown={(e) => e.key === 'Escape' && toggleSidebar()}
            role="button"
            tabindex="0"
        ></div>
    {/if}

    <!-- ==================== SIDEBAR ==================== -->
    <AdminSidebar {isSidebarOpen} isTourActive={showTour} />

    <!-- ==================== MAIN LAYOUT ==================== -->
    <div class="flex-grow lg:pl-72 flex flex-col min-h-screen w-full">
        <!-- Topbar -->
        <header
            class="h-20 bg-white/80 backdrop-blur-xl border-b border-slate-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-8"
        >
            <div class="flex items-center gap-4 flex-grow">
                <button
                    type="button"
                    aria-label="Toggle sidebar"
                    onclick={toggleSidebar}
                    class="lg:hidden p-2 text-slate-500 hover:text-slate-800 rounded-lg hover:bg-slate-100 transition"
                >
                    <i class="ti ti-menu-2 text-xl"></i>
                </button>
                <div class="flex items-center gap-2 font-outfit hidden sm:flex">
                    <span
                        class="px-2.5 py-1 bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black tracking-widest uppercase rounded-lg"
                        >Admin</span
                    >
                    <span class="font-black text-slate-800 tracking-tight"
                        >{storeName} Console</span
                    >
                </div>
            </div>

            <div class="flex items-center gap-4 shrink-0">
                <!-- Notifications Bell with Dropdown -->
                <div class="relative">
                    <button
                        onclick={() => (isNotifOpen = !isNotifOpen)}
                        class="p-2.5 text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-full transition relative flex items-center justify-center"
                        aria-label="Notifikasi"
                    >
                        <i class="ti ti-bell text-xl"></i>
                        {#if totalUnreadCount > 0}
                            <span
                                class="absolute -top-1 -right-1 w-5 h-5 bg-brand-orange text-white text-[9px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-sm font-sans"
                            >
                                {totalUnreadCount}
                            </span>
                        {/if}
                    </button>

                    {#if isNotifOpen}
                        <!-- Backdrop to close dropdown on click outside -->
                        <div
                            class="fixed inset-0 z-40"
                            onclick={() => (isNotifOpen = false)}
                            role="presentation"
                        ></div>

                        <!-- Dropdown Panel -->
                        <div
                            class="absolute right-0 mt-2 w-96 bg-white border border-slate-200 rounded-2xl shadow-2xl z-50 overflow-hidden font-sans origin-top-right animate-in fade-in slide-in-from-top-2 duration-200"
                        >
                            <!-- Header -->
                            <div
                                class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
                            >
                                <h3
                                    class="text-sm font-black text-slate-800 tracking-tight flex items-center gap-2"
                                >
                                    <i
                                        class="ti ti-bell-ringing text-brand-orange text-base"
                                    ></i>
                                    Notifikasi Sistem
                                </h3>
                                {#if unreadAdminNotifCount > 0}
                                    <button
                                        onclick={markAllAsRead}
                                        class="text-xs font-bold text-slate-500 hover:text-slate-800 transition"
                                    >
                                        Tandai Semua Dibaca
                                    </button>
                                {/if}
                            </div>

                            <!-- Content -->
                            <div
                                class="max-h-[380px] overflow-y-auto divide-y divide-slate-100 custom-scrollbar"
                            >
                                <!-- Detailed Order/Payment Notifications -->
                                {#if notificationsList.length > 0}
                                    <div class="divide-y divide-slate-100">
                                        {#each notificationsList as notif}
                                            <button
                                                onclick={() =>
                                                    handleNotificationClick(
                                                        notif,
                                                    )}
                                                class="w-full text-left p-4 hover:bg-slate-50/70 transition flex gap-3 items-start {!notif.is_read
                                                    ? 'bg-slate-50/40'
                                                    : ''}"
                                            >
                                                <!-- Icon depending on notification type -->
                                                <div
                                                    class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 {notif.type ===
                                                    'new_order'
                                                        ? 'bg-emerald-50 text-emerald-600'
                                                        : notif.type ===
                                                            'payment_proof'
                                                          ? 'bg-blue-50 text-blue-600'
                                                          : notif.type ===
                                                              'out_of_stock'
                                                            ? 'bg-rose-50 text-rose-600'
                                                            : notif.type ===
                                                                'low_stock'
                                                              ? 'bg-amber-50 text-amber-600'
                                                              : 'bg-slate-50 text-slate-600'}"
                                                >
                                                    <i
                                                        class="ti {notif.type ===
                                                        'new_order'
                                                            ? 'ti-package'
                                                            : notif.type ===
                                                                'payment_proof'
                                                              ? 'ti-credit-card'
                                                              : notif.type ===
                                                                  'out_of_stock'
                                                                ? 'ti-alert-triangle'
                                                                : notif.type ===
                                                                    'low_stock'
                                                                  ? 'ti-alert-circle'
                                                                  : 'ti-bell'} text-lg"
                                                    ></i>
                                                </div>

                                                <!-- Content -->
                                                <div class="flex-grow min-w-0">
                                                    <div
                                                        class="flex items-center justify-between gap-2 mb-0.5"
                                                    >
                                                        <span
                                                            class="text-xs font-black text-slate-800 truncate"
                                                            >{notif.title}</span
                                                        >
                                                        <span
                                                            class="text-[10px] text-slate-400 shrink-0"
                                                            >{notif.created_at}</span
                                                        >
                                                    </div>
                                                    <p
                                                        class="text-xs text-slate-600 leading-normal line-clamp-2"
                                                    >
                                                        {notif.message}
                                                    </p>
                                                </div>

                                                <!-- Unread Dot Indicator -->
                                                {#if !notif.is_read}
                                                    <div
                                                        class="w-2.5 h-2.5 bg-brand-orange rounded-full shrink-0 mt-1.5"
                                                    ></div>
                                                {/if}
                                            </button>
                                        {/each}
                                    </div>
                                {/if}

                                <!-- Stock Out Section -->
                                {#if outOfStockCount > 0}
                                    <div class="p-4 bg-rose-50/30">
                                        <h4
                                            class="text-xs font-black text-rose-600 uppercase tracking-wider mb-2 flex items-center gap-1.5"
                                        >
                                            <i
                                                class="ti ti-alert-triangle text-rose-500"
                                            ></i>
                                            Stok Habis ({outOfStockCount})
                                        </h4>
                                        <div class="space-y-2">
                                            {#each adminNotifications.outOfStockItems as item}
                                                <div
                                                    class="text-xs flex items-start justify-between gap-3"
                                                >
                                                    <span
                                                        class="text-slate-700 font-bold leading-tight flex-grow"
                                                        >{item.name}</span
                                                    >
                                                    <span
                                                        class="px-2 py-0.5 bg-rose-100 text-rose-700 font-black rounded-md shrink-0"
                                                        >Habis</span
                                                    >
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                {/if}

                                <!-- Stock Low Section -->
                                {#if lowStockCount > 0}
                                    <div class="p-4 bg-amber-50/30">
                                        <h4
                                            class="text-xs font-black text-amber-600 uppercase tracking-wider mb-2 flex items-center gap-1.5"
                                        >
                                            <i
                                                class="ti ti-alert-circle text-amber-500"
                                            ></i>
                                            Stok Menipis ({lowStockCount})
                                        </h4>
                                        <div class="space-y-2">
                                            {#each adminNotifications.lowStockItems as item}
                                                <div
                                                    class="text-xs flex items-start justify-between gap-3"
                                                >
                                                    <span
                                                        class="text-slate-700 font-bold leading-tight flex-grow"
                                                        >{item.name}</span
                                                    >
                                                    <span
                                                        class="px-2 py-0.5 bg-amber-100 text-amber-700 font-black rounded-md shrink-0"
                                                        >Sisa {item.stock}</span
                                                    >
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                {/if}

                                {#if totalUnreadCount === 0}
                                    <div class="py-12 px-6 text-center">
                                        <div
                                            class="w-12 h-12 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-3"
                                        >
                                            <i
                                                class="ti ti-circle-check text-2xl"
                                            ></i>
                                        </div>
                                        <p
                                            class="text-xs font-bold text-slate-800"
                                        >
                                            Semua Berjalan Lancar
                                        </p>
                                        <p
                                            class="text-[10px] text-slate-400 mt-1"
                                        >
                                            Tidak ada notifikasi sistem baru
                                            saat ini.
                                        </p>
                                    </div>
                                {/if}
                            </div>

                            <!-- Footer -->
                            {#if totalStockAlerts > 0}
                                <div
                                    class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 text-center"
                                >
                                    <Link
                                        href="/admin/store/stocks"
                                        onclick={() => (isNotifOpen = false)}
                                        class="text-xs font-black text-brand-blueRoyal hover:underline"
                                    >
                                        Atur Semua Stok Barang <i
                                            class="ti ti-chevron-right text-xs"
                                        ></i>
                                    </Link>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
                <div class="h-6 w-px bg-slate-200 mx-2 hidden sm:block"></div>
                <button
                    onclick={restartTour}
                    class="hidden sm:flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 rounded-full text-xs font-bold transition shrink-0"
                >
                    <i class="ti ti-help text-sm text-brand-orange"></i>
                    Tur Panduan
                </button>
                <a
                    href="/"
                    class="hidden sm:flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 rounded-full text-xs font-bold transition"
                >
                    <span
                        class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"
                    ></span>
                    Live Storefront
                    <i class="ti ti-external-link text-sm"></i>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        {@render children()}
    </div>

    <!-- Guided Setup Tour Overlay/Card -->
    {#if showTour && currentTourStep > 0}
        <div
            class="fixed bottom-6 right-6 w-[380px] bg-white border border-slate-200 shadow-2xl rounded-2xl p-5 z-50 animate-in fade-in slide-in-from-bottom-5 duration-300 font-sans"
        >
            <div
                class="flex items-center justify-between mb-3 border-b border-slate-100 pb-2"
            >
                <span
                    class="text-[10px] font-black tracking-widest text-brand-orange uppercase"
                    >Panduan Toko Baru</span
                >
                <span class="text-xs font-bold text-slate-400"
                    >Langkah {currentTourStep} dari {tourSteps.length}</span
                >
            </div>

            <h4 class="text-sm font-black text-slate-800 tracking-tight mb-2">
                {tourSteps[currentTourStep - 1].title}
            </h4>

            <p class="text-xs text-slate-600 leading-relaxed mb-4">
                {tourSteps[currentTourStep - 1].description}
            </p>

            <!-- Progress Bar -->
            <div
                class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mb-4"
            >
                <div
                    class="h-full bg-brand-orange transition-all duration-300 rounded-full"
                    style="width: {(currentTourStep / tourSteps.length) * 100}%"
                ></div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <button
                    onclick={skipTour}
                    class="text-xs font-bold text-slate-400 hover:text-slate-600 transition"
                >
                    Lewati Panduan
                </button>

                <div class="flex items-center gap-2">
                    {#if currentTourStep > 1}
                        <button
                            onclick={handleTourPrev}
                            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition"
                        >
                            Kembali
                        </button>
                    {/if}

                    <button
                        onclick={handleTourNext}
                        class="px-4 py-1.5 text-white font-bold rounded-lg text-xs transition flex items-center gap-1 shrink-0"
                        style="background-color: var(--color-brand-orange);"
                    >
                        {currentTourStep === tourSteps.length ? 'Selesai' : 'Lanjutkan'}
                        <i class="ti ti-chevron-right text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <OfflineDetector />
</div>

<style>
    :global(.custom-scrollbar::-webkit-scrollbar) {
        width: 4px;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-track) {
        background: transparent;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb) {
        background: #e2e8f0;
        border-radius: 10px;
    }
    :global(.custom-scrollbar::-webkit-scrollbar-thumb:hover) {
        background: #cbd5e1;
    }
</style>
