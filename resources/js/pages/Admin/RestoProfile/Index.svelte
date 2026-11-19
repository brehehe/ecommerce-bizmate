<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, usePage, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        restoProfile,
        culinaryCategories = [],
        storeName = '',
        availableRestos = [],
    } = $props();

    const page = usePage();
    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    const defaultHours: Record<string, { open: string; close: string; is_closed: boolean }> = {
        monday: { open: '09:00', close: '21:00', is_closed: false },
        tuesday: { open: '09:00', close: '21:00', is_closed: false },
        wednesday: { open: '09:00', close: '21:00', is_closed: false },
        thursday: { open: '09:00', close: '21:00', is_closed: false },
        friday: { open: '09:00', close: '21:00', is_closed: false },
        saturday: { open: '09:00', close: '22:00', is_closed: false },
        sunday: { open: '09:00', close: '22:00', is_closed: false },
    };

    function resolveOperatingHours(raw: any): Record<string, { open: string; close: string; is_closed: boolean }> {
        const result: Record<string, { open: string; close: string; is_closed: boolean }> = {};
        for (const [key, val] of Object.entries(defaultHours)) {
            result[key] = { ...val };
        }

        const dayMap: Record<string, string> = {
            senin: 'monday',
            selasa: 'tuesday',
            rabu: 'wednesday',
            kamis: 'thursday',
            jumat: 'friday',
            sabtu: 'saturday',
            minggu: 'sunday',
        };

        if (raw && typeof raw === 'object' && !Array.isArray(raw)) {
            for (const [key, val] of Object.entries(raw)) {
                const lowerKey = key.toLowerCase();
                const standardKey = dayMap[lowerKey] || lowerKey;
                if (result[standardKey]) {
                    if (typeof val === 'string' && val.includes('-')) {
                        const parts = val.split('-').map((s) => s.trim());
                        result[standardKey] = {
                            open: parts[0] || '09:00',
                            close: parts[1] || '21:00',
                            is_closed: false,
                        };
                    } else if (val && typeof val === 'object') {
                        result[standardKey] = {
                            open: (val as any).open || '09:00',
                            close: (val as any).close || '21:00',
                            is_closed: Boolean((val as any).is_closed),
                        };
                    }
                }
            }
        }
        return result;
    }

    const form = useForm(() => ({
        resto_id: restoProfile?.id || '',
        resto_name: restoProfile?.resto_name || '',
        culinary_category: restoProfile?.culinary_category || culinaryCategories[0] || 'Aneka Kuliner',
        description: restoProfile?.description || '',
        address: restoProfile?.address || '',
        latitude: restoProfile?.latitude || -7.983908,
        longitude: restoProfile?.longitude || 112.621391,
        avg_prep_time_minutes: restoProfile?.avg_prep_time_minutes || 20,
        max_delivery_radius_km: restoProfile?.max_delivery_radius_km || 10,
        delivery_fee_type: restoProfile?.delivery_fee_type || 'flat',
        delivery_fee_type: (restoProfile?.delivery_fee_type === 'fixed' ? 'flat' : restoProfile?.delivery_fee_type) || 'flat',
        delivery_fee_base: restoProfile?.delivery_fee_base ?? 10000,
        delivery_free_min_order: restoProfile?.delivery_free_min_order ?? 0,
        allow_pickup: restoProfile?.allow_pickup ?? true,
        allow_delivery: restoProfile?.allow_delivery ?? true,
        is_open_now: restoProfile?.is_open_now ?? true,
        operating_hours: resolveOperatingHours(restoProfile?.operating_hours),
        payment_methods: {
            cod: {
                is_active: restoProfile?.payment_methods?.cod?.is_active ?? true,
                label: restoProfile?.payment_methods?.cod?.label || 'Bayar di Tempat (COD / Kasir Resto)',
                description: restoProfile?.payment_methods?.cod?.description || 'Bayar langsung dengan uang pas saat makanan diambil di resto atau diantar kurir.',
            },
            bank_transfer: {
                is_active: restoProfile?.payment_methods?.bank_transfer?.is_active ?? true,
                label: restoProfile?.payment_methods?.bank_transfer?.label || 'Transfer Bank Resto',
                bank_name: restoProfile?.payment_methods?.bank_transfer?.bank_name || 'BCA',
                account_number: restoProfile?.payment_methods?.bank_transfer?.account_number || '',
                account_name: restoProfile?.payment_methods?.bank_transfer?.account_name || (restoProfile?.resto_name || ''),
                description: restoProfile?.payment_methods?.bank_transfer?.description || 'Transfer ke rekening resmi resto dan lampirkan bukti pembayaran.',
            },
            qris: {
                is_active: restoProfile?.payment_methods?.qris?.is_active ?? true,
                label: restoProfile?.payment_methods?.qris?.label || 'QRIS Resto (Semua E-Wallet / Bank)',
                merchant_name: restoProfile?.payment_methods?.qris?.merchant_name || (restoProfile?.resto_name || ''),
                description: restoProfile?.payment_methods?.qris?.description || 'Scan kode QRIS resto menggunakan GoPay, OVO, ShopeePay, DANA, atau Mobile Banking.',
                image_path: restoProfile?.payment_methods?.qris?.image_path || restoProfile?.qris_image_path || '',
            },
            waris: {
                is_active: restoProfile?.payment_methods?.waris?.is_active ?? false,
                label: restoProfile?.payment_methods?.waris?.label || 'WARIS QRIS Dinamis Resto',
                api_url: restoProfile?.payment_methods?.waris?.api_url || 'https://analisis.giradia.id/api/v1',
                api_key: restoProfile?.payment_methods?.waris?.api_key || '',
                webhook_secret: restoProfile?.payment_methods?.waris?.webhook_secret || '',
                qris_code: restoProfile?.payment_methods?.waris?.qris_code || 'random',
                admin_fee: restoProfile?.payment_methods?.waris?.admin_fee || 0,
                description: restoProfile?.payment_methods?.waris?.description || 'Generate Dynamic QRIS real-time dengan verifikasi instan melalui Webhook portal WARIS.',
            },
        },
        logo: null as File | null,
        banner: null as File | null,
        qris_image: null as File | null,
    }));

    let logoPreview = $state<string | null>(null);
    let bannerPreview = $state<string | null>(null);
    let qrisPreview = $state<string | null>(null);
    let isDetectingGps = $state(false);
    let isToggling = $state(false);

    $effect(() => {
        logoPreview = restoProfile?.logo_path || null;
        bannerPreview = restoProfile?.banner_path || null;
        qrisPreview = restoProfile?.qris_image_path || restoProfile?.payment_methods?.qris?.image_path || null;
    });

    const dayLabels: Record<string, string> = {
        monday: 'Senin',
        tuesday: 'Selasa',
        wednesday: 'Rabu',
        thursday: 'Kamis',
        friday: 'Jumat',
        saturday: 'Sabtu',
        sunday: 'Minggu',
    };

    const commonBanks = [
        'BCA',
        'Mandiri',
        'BRI',
        'BNI',
        'BSI (Bank Syariah Indonesia)',
        'CIMB Niaga',
        'Permata',
        'Bank Jatim',
        'BTPN / Jenius',
        'Bank Lainnya',
    ];

    function handleLogoChange(e: Event) {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (file) {
            form.logo = file;
            logoPreview = URL.createObjectURL(file);
        }
    }

    function handleBannerChange(e: Event) {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (file) {
            form.banner = file;
            bannerPreview = URL.createObjectURL(file);
        }
    }

    function handleQrisChange(e: Event) {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (file) {
            form.qris_image = file;
            qrisPreview = URL.createObjectURL(file);
        }
    }

    function detectCurrentLocation() {
        if (!navigator.geolocation) {
            showToast('Geolocation tidak didukung browser ini.', 'error');
            return;
        }

        isDetectingGps = true;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                form.latitude = Number(pos.coords.latitude.toFixed(6));
                form.longitude = Number(pos.coords.longitude.toFixed(6));
                isDetectingGps = false;
                showToast('Koordinat GPS berhasil dideteksi!', 'success');
            },
            () => {
                isDetectingGps = false;
                showToast('Gagal mendeteksi lokasi GPS.', 'error');
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    }

    function toggleEmergencyOpen() {
        isToggling = true;
        router.post('/admin/resto-profile/toggle-open', {}, {
            preserveScroll: true,
            onFinish: () => {
                isToggling = false;
            },
            onSuccess: () => {
                form.is_open_now = !form.is_open_now;
            },
        });
    }

    function handleSubmit(e: Event) {
        e.preventDefault();
        // Use post with _method: 'put' so multipart/form-data (with file uploads like qris_image) is parsed by PHP
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post('/admin/resto-profile', {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Profil dapur resto & metode pembayaran berhasil disimpan!', 'success');
            },
            onError: (errs) => {
                const firstErr = Object.values(errs)[0];
                showToast(firstErr || 'Terjadi kesalahan saat menyimpan profil.', 'error');
            },
        });
    }
</script>

<svelte:head>
    <title>Profil Dapur Resto — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- ── Top Header & Emergency Switch ────────────────────────── -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-1">
                    <Link href="/admin/dashboard" class="hover:text-slate-800 transition-colors">Dashboard</Link>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Profil Dapur Resto</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900">
                        Profil Dapur & Mitra Resto
                    </h1>
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold {restoProfile.approval_status === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-amber-50 text-amber-700 border border-amber-100'}">
                        {restoProfile.approval_status === 'approved' ? 'Terverifikasi' : 'Menunggu Approval'}
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">
                    Atur jam operasional, status buka/tutup dapur, koordinat GPS, tarif pengantaran, dan metode pembayaran resto
                </p>
            </div>

            <!-- Emergency Open / Closed Toggle -->
            <div class="flex items-center gap-3 bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-2xs">
                <div class="text-right">
                    <div class="text-xs font-semibold {restoProfile.is_open_now ? 'text-emerald-700' : 'text-rose-700'} flex items-center gap-1.5 justify-end">
                        <span class="w-2 h-2 rounded-full {restoProfile.is_open_now ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'}"></span>
                        <span>{restoProfile.is_open_now ? 'DAPUR BUKA' : 'DAPUR TUTUP'}</span>
                    </div>
                    <p class="text-[11px] text-slate-400">
                        {restoProfile.is_open_now ? 'Menerima pesanan' : 'Pesanan dijeda'}
                    </p>
                </div>
                <button
                    onclick={toggleEmergencyOpen}
                    disabled={isToggling}
                    class="h-8 px-3 text-xs font-semibold rounded-lg transition-opacity hover:opacity-90 shadow-xs cursor-pointer flex items-center gap-1.5 {restoProfile.is_open_now ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'}"
                    type="button"
                >
                    <i class="ti {restoProfile.is_open_now ? 'ti-player-pause' : 'ti-player-play'}"></i>
                    <span>{restoProfile.is_open_now ? 'Tutup Sementara' : 'Buka Sekarang'}</span>
                </button>
            </div>
        </div>

        <!-- ── Super Admin Multi-Resto Selector ──────────────────────── -->
        {#if availableRestos && availableRestos.length > 1}
            <div class="bg-blue-50/70 border border-blue-200/80 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-xs text-blue-900">
                    <i class="ti ti-building-store text-base text-blue-600"></i>
                    <div>
                        <span class="font-bold">Mode Administrator:</span>
                        <span class="text-blue-700 block sm:inline sm:ml-1">Pilih profil resto yang ingin Anda kelola pengaturannya:</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <label for="admin_resto_select" class="text-xs font-semibold text-blue-900 sr-only">Pilih Resto</label>
                    <select
                        id="admin_resto_select"
                        value={restoProfile.id}
                        onchange={(e) => {
                            const selectedId = (e.target as HTMLSelectElement).value;
                            router.get('/admin/resto-profile', { resto_id: selectedId });
                        }}
                        class="h-8.5 px-3 rounded-lg border border-blue-300 bg-white text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer shadow-xs"
                    >
                        {#each availableRestos as r}
                            <option value={r.id}>
                                {r.resto_name} {r.approval_status === 'approved' ? '✓' : '(Pending)'}
                            </option>
                        {/each}
                    </select>
                </div>
            </div>
        {/if}

        <!-- ── Main Form Grid ──────────────────────────────────────── -->
        <form onsubmit={handleSubmit} class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- ── Column 1 & 2: Form Controls ────────────────────── -->
                <div class="lg:col-span-2 space-y-5">
                    <!-- Section 1: Informasi Resto -->
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-4">
                        <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                            <i class="ti ti-building-store text-slate-600"></i>
                            <span>1. Identitas Dapur & Kategori Kuliner</span>
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="resto_name" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Nama Resto / Dapur <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="resto_name"
                                    type="text"
                                    bind:value={form.resto_name}
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none transition-colors"
                                    required
                                />
                            </div>

                            <div>
                                <label for="culinary_category" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Kategori Kuliner Utama <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="culinary_category"
                                    bind:value={form.culinary_category}
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                                >
                                    {#each culinaryCategories as cat}
                                        <option value={cat}>{cat}</option>
                                    {/each}
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-xs font-semibold text-slate-700 mb-1">
                                Deskripsi Cita Rasa / Bio Singkat
                            </label>
                            <textarea
                                id="description"
                                bind:value={form.description}
                                rows="2"
                                placeholder="Jelaskan menu andalan resto Anda (misal: Bebek goreng rempah gurih sambal korek)..."
                                class="w-full rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none transition-colors"
                            ></textarea>
                        </div>

                        <!-- Logo and Banner -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <div>
                                <span class="block text-xs font-semibold text-slate-700 mb-1">
                                    Logo Resto (1:1)
                                </span>
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                                        {#if logoPreview}
                                            <img src={logoPreview} alt="Logo" class="w-full h-full object-cover" />
                                        {:else}
                                            <i class="ti ti-photo text-slate-400 text-lg"></i>
                                        {/if}
                                    </div>
                                    <label class="h-9 px-3 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors">
                                        <i class="ti ti-upload"></i>
                                        <span>Ganti Logo</span>
                                        <input type="file" accept="image/*" class="hidden" onchange={handleLogoChange} />
                                    </label>
                                </div>
                            </div>

                            <div>
                                <span class="block text-xs font-semibold text-slate-700 mb-1">
                                    Banner Resto (Landscape)
                                </span>
                                <div class="flex items-center gap-3">
                                    <div class="w-20 h-12 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center shrink-0">
                                        {#if bannerPreview}
                                            <img src={bannerPreview} alt="Banner" class="w-full h-full object-cover" />
                                        {:else}
                                            <i class="ti ti-photo text-slate-400 text-lg"></i>
                                        {/if}
                                    </div>
                                    <label class="h-9 px-3 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors">
                                        <i class="ti ti-upload"></i>
                                        <span>Ganti Banner</span>
                                        <input type="file" accept="image/*" class="hidden" onchange={handleBannerChange} />
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Alamat & Koordinat GPS -->
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                                <i class="ti ti-map-pin text-slate-600"></i>
                                <span>2. Alamat & Titik Koordinat GPS</span>
                            </h2>
                            <button
                                type="button"
                                onclick={detectCurrentLocation}
                                disabled={isDetectingGps}
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 hover:text-slate-900 cursor-pointer disabled:opacity-50"
                            >
                                <i class="ti {isDetectingGps ? 'ti-loader-2 animate-spin' : 'ti-current-location'} text-sm text-blue-600"></i>
                                <span>{isDetectingGps ? 'Mendeteksi...' : 'Deteksi GPS Saya'}</span>
                            </button>
                        </div>

                        <div>
                            <label for="address" class="block text-xs font-semibold text-slate-700 mb-1">
                                Alamat Lengkap Resto <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                id="address"
                                bind:value={form.address}
                                rows="2"
                                placeholder="Nama jalan, nomor ruko/rumah, kelurahan, kecamatan, kota..."
                                class="w-full rounded-lg border border-slate-200 bg-white p-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none transition-colors"
                                required
                            ></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="latitude" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Latitude GPS
                                </label>
                                <input
                                    id="latitude"
                                    type="number"
                                    step="0.000001"
                                    bind:value={form.latitude}
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors font-mono"
                                />
                            </div>

                            <div>
                                <label for="longitude" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Longitude GPS
                                </label>
                                <input
                                    id="longitude"
                                    type="number"
                                    step="0.000001"
                                    bind:value={form.longitude}
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors font-mono"
                                />
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-500 flex items-center justify-between pt-1">
                            <span>*Koordinat GPS digunakan untuk kalkulasi jarak radius kurir resto ke rumah pembeli.</span>
                            {#if form.latitude && form.longitude}
                                <a
                                    href="https://www.google.com/maps/search/?api=1&query={form.latitude},{form.longitude}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-blue-600 hover:underline font-medium inline-flex items-center gap-1"
                                >
                                    <span>Buka di Google Maps</span>
                                    <i class="ti ti-external-link"></i>
                                </a>
                            {/if}
                        </div>
                    </div>

                    <!-- Section 3: Waktu Masak & Layanan Pengantaran -->
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-4">
                        <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                            <i class="ti ti-moped text-slate-600"></i>
                            <span>3. Waktu Memasak & Pengantaran Kurir Resto</span>
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="avg_prep_time_minutes" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Estimasi Waktu Masak (Menit) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="avg_prep_time_minutes"
                                        type="number"
                                        min="5"
                                        max="180"
                                        bind:value={form.avg_prep_time_minutes}
                                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 pr-16 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors"
                                        required
                                    />
                                    <span class="absolute right-3 top-2 text-xs text-slate-400">
                                        Menit
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-400 mt-1 block">Waktu dapur untuk menyiapkan dan membungkus makanan.</span>
                            </div>

                            <div>
                                <label for="max_delivery_radius_km" class="block text-xs font-semibold text-slate-700 mb-1">
                                    Radius Maksimal Kurir Resto (KM)
                                </label>
                                <div class="relative">
                                    <input
                                        id="max_delivery_radius_km"
                                        type="number"
                                        step="0.5"
                                        min="1"
                                        max="50"
                                        bind:value={form.max_delivery_radius_km}
                                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 pr-12 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors"
                                    />
                                    <span class="absolute right-3 top-2 text-xs text-slate-400">
                                        KM
                                    </span>
                                </div>
                                <span class="text-[11px] text-slate-400 mt-1 block">Jarak tempuh terjauh kurir resto internal.</span>
                            </div>
                        </div>

                        <!-- Skema Tarif Pengantaran -->
                        <div class="pt-3 border-t border-slate-100 space-y-3">
                            <span class="block text-xs font-semibold text-slate-800">
                                Skema Ongkos Kirim Kurir Resto
                            </span>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label for="delivery_fee_type" class="block text-[11px] font-medium text-slate-600 mb-1">
                                        Tipe Perhitungan Tarif
                                    </label>
                                    <select
                                        id="delivery_fee_type"
                                        bind:value={form.delivery_fee_type}
                                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                                    >
                                        <option value="flat">Tarif Flat (Sama Rata)</option>
                                        <option value="per_km">Tarif Berdasarkan Jarak (Per KM)</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="delivery_fee_base" class="block text-[11px] font-medium text-slate-600 mb-1">
                                        {form.delivery_fee_type === 'per_km' ? 'Biaya Ongkir Per KM (Rp)' : 'Tarif Ongkir Flat (Rp)'}
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-xs text-slate-400">Rp</span>
                                        <input
                                            id="delivery_fee_base"
                                            type="number"
                                            min="0"
                                            step="500"
                                            bind:value={form.delivery_fee_base}
                                            class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-xs text-slate-700 focus:border-slate-400 focus:outline-none transition-colors"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label for="delivery_free_min_order" class="block text-[11px] font-medium text-slate-600 mb-1">
                                        Gratis Ongkir Min. Belanja (Rp)
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-xs text-slate-400">Rp</span>
                                        <input
                                            id="delivery_free_min_order"
                                            type="number"
                                            min="0"
                                            step="1000"
                                            placeholder="0 = tidak ada gratis ongkir"
                                            bind:value={form.delivery_free_min_order}
                                            class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-xs text-slate-700 focus:border-slate-400 focus:outline-none transition-colors"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Checkboxes for Delivery and Pickup -->
                        <div class="pt-2 grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-slate-100">
                            <label class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">
                                <input
                                    type="checkbox"
                                    bind:checked={form.allow_pickup}
                                    class="mt-0.5 rounded text-slate-900 focus:ring-0"
                                />
                                <div>
                                    <div class="text-xs font-semibold text-slate-800">Izinkan Ambil di Resto</div>
                                    <div class="text-[11px] text-slate-500">Bungkus (Takeaway) atau Makan di Tempat (Dine-In)</div>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer">
                                <input
                                    type="checkbox"
                                    bind:checked={form.allow_delivery}
                                    class="mt-0.5 rounded text-slate-900 focus:ring-0"
                                />
                                <div>
                                    <div class="text-xs font-semibold text-slate-800">Izinkan Kurir Resto</div>
                                    <div class="text-[11px] text-slate-500">Diantar armada kurir internal resto</div>
                                </div>
                            </label>

                            <!-- Quick link to Courier Management -->
                            <div class="col-span-1 sm:col-span-2 p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between text-xs flex-wrap gap-2">
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i class="ti ti-moped text-base text-slate-500"></i>
                                    <div>
                                        <span class="font-bold">Portal & Armada Kurir Resto:</span>
                                        <span class="text-slate-500 block text-[11px]">Kurir resto login di <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200 font-mono">/kurir/login</code> untuk mengantar makanan.</span>
                                    </div>
                                </div>
                                <Link
                                    href="/admin/courier-dispatch"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition hover:opacity-90 shrink-0 shadow-2xs"
                                    style="background-color: {secondary};"
                                >
                                    <i class="ti ti-users-group"></i>
                                    <span>Kelola Armada Kurir</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Jam Operasional Harian -->
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-4">
                        <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3">
                            <i class="ti ti-clock text-slate-600"></i>
                            <span>4. Jadwal Jam Operasional Harian</span>
                        </h2>

                        <div class="space-y-2">
                            {#each Object.entries(dayLabels) as [dayKey, dayLabel]}
                                {#if form.operating_hours && form.operating_hours[dayKey]}
                                    <div class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 bg-slate-50/50 text-xs">
                                        <div class="w-24 font-semibold text-slate-800">
                                            {dayLabel}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input
                                                type="time"
                                                bind:value={form.operating_hours[dayKey].open}
                                                disabled={form.operating_hours[dayKey]?.is_closed}
                                                class="h-8 px-2 rounded-md border border-slate-200 bg-white text-xs disabled:opacity-40 focus:outline-none focus:border-slate-400"
                                            />
                                            <span class="text-slate-400">s/d</span>
                                            <input
                                                type="time"
                                                bind:value={form.operating_hours[dayKey].close}
                                                disabled={form.operating_hours[dayKey]?.is_closed}
                                                class="h-8 px-2 rounded-md border border-slate-200 bg-white text-xs disabled:opacity-40 focus:outline-none focus:border-slate-400"
                                            />
                                        </div>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                bind:checked={form.operating_hours[dayKey].is_closed}
                                                class="rounded text-rose-600 focus:ring-0"
                                            />
                                            <span class="text-[11px] font-semibold {form.operating_hours[dayKey]?.is_closed ? 'text-rose-600' : 'text-slate-400'}">
                                                Libur
                                            </span>
                                        </label>
                                    </div>
                                {/if}
                            {/each}
                        </div>
                    </div>

                    <!-- Section 5: Metode Pembayaran Resto (NEW) -->
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-5">
                        <div class="border-b border-slate-100 pb-3">
                            <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                                <i class="ti ti-wallet text-slate-600"></i>
                                <span>5. Metode Pembayaran Resto</span>
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Atur metode pembayaran yang diterima resto Anda saat pelanggan checkout (Tunai di kasir/COD, Transfer Bank Rekening Resto, dan QRIS)
                            </p>
                        </div>

                        <!-- 1. COD / Tunai -->
                        <div class="p-4 rounded-xl border {form.payment_methods.cod.is_active ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200 bg-slate-50/50'} space-y-3 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg {form.payment_methods.cod.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'} flex items-center justify-center text-base">
                                        <i class="ti ti-cash"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Bayar di Tempat (COD / Kasir Resto)</div>
                                        <div class="text-[11px] text-slate-500">Menerima pembayaran tunai saat pesanan diambil atau diantar</div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        bind:checked={form.payment_methods.cod.is_active}
                                        class="sr-only peer"
                                    />
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                </label>
                            </div>

                            {#if form.payment_methods.cod.is_active}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-emerald-100/60">
                                    <div>
                                        <label for="cod_label" class="block text-[11px] font-medium text-slate-700 mb-1">
                                            Nama Label di Checkout
                                        </label>
                                        <input
                                            id="cod_label"
                                            type="text"
                                            bind:value={form.payment_methods.cod.label}
                                            class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                        />
                                    </div>
                                    <div>
                                        <label for="cod_desc" class="block text-[11px] font-medium text-slate-700 mb-1">
                                            Catatan / Instruksi Pembeli
                                        </label>
                                        <input
                                            id="cod_desc"
                                            type="text"
                                            bind:value={form.payment_methods.cod.description}
                                            placeholder="Siapkan uang pas saat menerima makanan..."
                                            class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <!-- 2. Transfer Bank Resto -->
                        <div class="p-4 rounded-xl border {form.payment_methods.bank_transfer.is_active ? 'border-blue-200 bg-blue-50/20' : 'border-slate-200 bg-slate-50/50'} space-y-3 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg {form.payment_methods.bank_transfer.is_active ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500'} flex items-center justify-center text-base">
                                        <i class="ti ti-building-bank"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Transfer Bank Langsung ke Resto</div>
                                        <div class="text-[11px] text-slate-500">Pelanggan mentransfer ke nomor rekening bank resmi milik resto Anda</div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        bind:checked={form.payment_methods.bank_transfer.is_active}
                                        class="sr-only peer"
                                    />
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            {#if form.payment_methods.bank_transfer.is_active}
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-blue-100/60">
                                    <div>
                                        <label for="bank_name" class="block text-[11px] font-medium text-slate-700 mb-1">
                                            Nama Bank <span class="text-rose-500">*</span>
                                        </label>
                                        <select
                                            id="bank_name"
                                            bind:value={form.payment_methods.bank_transfer.bank_name}
                                            class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2 text-xs text-slate-700 focus:border-slate-400 focus:outline-none cursor-pointer"
                                        >
                                            {#each commonBanks as b}
                                                <option value={b}>{b}</option>
                                            {/each}
                                        </select>
                                    </div>

                                    <div>
                                        <label for="bank_acc_no" class="block text-[11px] font-medium text-slate-700 mb-1">
                                            Nomor Rekening <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            id="bank_acc_no"
                                            type="text"
                                            placeholder="Contoh: 8295018291"
                                            bind:value={form.payment_methods.bank_transfer.account_number}
                                            class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 font-mono focus:border-slate-400 focus:outline-none"
                                        />
                                    </div>

                                    <div>
                                        <label for="bank_acc_name" class="block text-[11px] font-medium text-slate-700 mb-1">
                                            Atas Nama (Pemilik Rekening) <span class="text-rose-500">*</span>
                                        </label>
                                        <input
                                            id="bank_acc_name"
                                            type="text"
                                            placeholder="Nama pemilik rekening"
                                            bind:value={form.payment_methods.bank_transfer.account_name}
                                            class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                        />
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label for="bank_desc" class="block text-[11px] font-medium text-slate-700 mb-1">
                                            Instruksi Transfer
                                        </label>
                                        <input
                                            id="bank_desc"
                                            type="text"
                                            bind:value={form.payment_methods.bank_transfer.description}
                                            placeholder="Transfer tepat nominal dan lampirkan bukti pembayaran..."
                                            class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                        />
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <!-- 3. QRIS Resto -->
                        <div class="p-4 rounded-xl border {form.payment_methods.qris.is_active ? 'border-rose-200 bg-rose-50/20' : 'border-slate-200 bg-slate-50/50'} space-y-3 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg {form.payment_methods.qris.is_active ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-500'} flex items-center justify-center text-base">
                                        <i class="ti ti-qrcode"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">QRIS Resto (Semua E-Wallet / Mobile Banking)</div>
                                        <div class="text-[11px] text-slate-500">Pelanggan scan barcode QRIS resto menggunakan GoPay, OVO, ShopeePay, DANA, BCA, dll</div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        bind:checked={form.payment_methods.qris.is_active}
                                        class="sr-only peer"
                                    />
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-600"></div>
                                </label>
                            </div>

                            {#if form.payment_methods.qris.is_active}
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-rose-100/60">
                                    <div class="sm:col-span-2 space-y-2">
                                        <div>
                                            <label for="qris_merchant_name" class="block text-[11px] font-medium text-slate-700 mb-1">
                                                Nama Merchant QRIS (NMID / Nama Dapur)
                                            </label>
                                            <input
                                                id="qris_merchant_name"
                                                type="text"
                                                placeholder="Contoh: Resto Test Surabaya"
                                                bind:value={form.payment_methods.qris.merchant_name}
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label for="qris_desc" class="block text-[11px] font-medium text-slate-700 mb-1">
                                                Instruksi Scan Pembeli
                                            </label>
                                            <input
                                                id="qris_desc"
                                                type="text"
                                                bind:value={form.payment_methods.qris.description}
                                                placeholder="Scan QRIS menggunakan BCA, GoPay, OVO, ShopeePay, atau DANA..."
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <span class="block text-[11px] font-medium text-slate-700 mb-1">
                                            Foto / Gambar Kode QRIS
                                        </span>
                                        <div class="flex items-center gap-3">
                                            <div class="w-16 h-16 rounded-lg bg-white border border-slate-200 overflow-hidden flex items-center justify-center shrink-0 shadow-2xs">
                                                {#if qrisPreview}
                                                    <img src={qrisPreview} alt="QRIS" class="w-full h-full object-contain p-1" />
                                                {:else}
                                                    <i class="ti ti-qrcode text-slate-300 text-2xl"></i>
                                                {/if}
                                            </div>
                                            <label class="h-8 px-2.5 rounded-lg border border-slate-200 bg-white text-xs font-medium text-slate-700 hover:bg-slate-50 flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors">
                                                <i class="ti ti-upload"></i>
                                                <span>Upload QRIS</span>
                                                <input type="file" accept="image/*" class="hidden" onchange={handleQrisChange} />
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <!-- 4. WARIS QRIS Dinamis Resto -->
                        <div class="p-4 rounded-xl border {form.payment_methods.waris.is_active ? 'border-rose-200 bg-rose-50/20' : 'border-slate-200 bg-slate-50/50'} space-y-3 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg {form.payment_methods.waris.is_active ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-500'} flex items-center justify-center text-base">
                                        <i class="ti ti-bolt"></i>
                                    </div>
                                    <div>
                                        <div class="text-xs font-bold text-slate-900">Payment Gateway: WARIS QRIS Dinamis</div>
                                        <div class="text-[11px] text-slate-500">Generate Dynamic QRIS real-time dengan verifikasi instan melalui Webhook portal WARIS</div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        bind:checked={form.payment_methods.waris.is_active}
                                        class="sr-only peer"
                                    />
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-600"></div>
                                </label>
                            </div>

                            {#if form.payment_methods.waris.is_active}
                                <div class="space-y-3 pt-2 border-t border-rose-100/60 text-xs">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label for="waris_api_url" class="block text-[11px] font-medium text-slate-700 mb-1">
                                                API Base URL <span class="text-rose-500">*</span>
                                            </label>
                                            <input
                                                id="waris_api_url"
                                                type="text"
                                                placeholder="https://analisis.giradia.id/api/v1"
                                                bind:value={form.payment_methods.waris.api_url}
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label for="waris_api_key" class="block text-[11px] font-medium text-slate-700 mb-1">
                                                WARIS API Key (X-API-KEY) <span class="text-rose-500">*</span>
                                            </label>
                                            <input
                                                id="waris_api_key"
                                                type="password"
                                                placeholder="wrs_live_..."
                                                bind:value={form.payment_methods.waris.api_key}
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 font-mono focus:border-slate-400 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label for="waris_webhook_secret" class="block text-[11px] font-medium text-slate-700 mb-1">
                                                Webhook Secret (HMAC-SHA256)
                                            </label>
                                            <input
                                                id="waris_webhook_secret"
                                                type="password"
                                                placeholder="Secret untuk signature"
                                                bind:value={form.payment_methods.waris.webhook_secret}
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 font-mono focus:border-slate-400 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label for="waris_qris_code" class="block text-[11px] font-medium text-slate-700 mb-1">
                                                QRIS Code Target
                                            </label>
                                            <input
                                                id="waris_qris_code"
                                                type="text"
                                                placeholder="random atau kode spesifik"
                                                bind:value={form.payment_methods.waris.qris_code}
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                            />
                                        </div>

                                        <div>
                                            <label for="waris_admin_fee" class="block text-[11px] font-medium text-slate-700 mb-1">
                                                Biaya Admin (Rupiah)
                                            </label>
                                            <input
                                                id="waris_admin_fee"
                                                type="number"
                                                min="0"
                                                placeholder="0"
                                                bind:value={form.payment_methods.waris.admin_fee}
                                                class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 focus:border-slate-400 focus:outline-none"
                                            />
                                        </div>
                                    </div>

                                    <div class="bg-rose-50/80 border border-rose-200 rounded-xl p-3 text-[11.5px] text-rose-800 space-y-1">
                                        <p class="font-bold flex items-center gap-1.5 text-rose-900">
                                            <i class="ti ti-link"></i> URL Webhook Callback untuk Dashboard WARIS Resto:
                                        </p>
                                        <p class="font-mono bg-white p-2 rounded-lg border border-rose-200 select-all text-[11px] break-all text-slate-800">
                                            /api/webhooks/waris
                                        </p>
                                        <p class="text-[10.5px] text-rose-600">
                                            Masukkan URL callback ini ke menu Webhook portal WARIS untuk notifikasi pembayaran real-time resto Anda.
                                        </p>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <!-- ── Column 3: Live Preview & Action ────────────────── -->
                <div class="space-y-5">
                    <!-- Live Preview Card -->
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            Pratinjau Kartu Beranda
                        </h3>

                        <div class="rounded-xl border border-slate-200 overflow-hidden shadow-2xs bg-white">
                            <div class="relative h-28 bg-slate-100 overflow-hidden">
                                {#if bannerPreview}
                                    <img src={bannerPreview} alt="Banner" class="w-full h-full object-cover" />
                                {:else}
                                    <div class="w-full h-full bg-slate-100 flex items-center justify-center text-slate-400">
                                        <i class="ti ti-tools-kitchen-2 text-3xl opacity-40"></i>
                                    </div>
                                {/if}
                                <div class="absolute top-2 right-2">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold text-white {form.is_open_now ? 'bg-emerald-600' : 'bg-slate-700'}">
                                        {form.is_open_now ? 'BUKA' : 'TUTUP'}
                                    </span>
                                </div>
                                <div class="absolute bottom-2 left-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-white text-slate-700 shadow-2xs">
                                        Siap ~{form.avg_prep_time_minutes} mnt
                                    </span>
                                </div>
                            </div>
                            <div class="p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-semibold text-sm text-slate-900 truncate">
                                        {form.resto_name || 'Nama Resto Anda'}
                                    </div>
                                    <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded shrink-0">
                                        ⭐ 4.9
                                    </span>
                                </div>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">
                                    {form.culinary_category}
                                </p>
                                <p class="text-[11px] text-slate-500 mt-1 line-clamp-1">
                                    {form.address || 'Alamat resto Anda'}
                                </p>
                            </div>
                        </div>

                        {#if restoProfile.store_slug}
                            <a
                                href="/{restoProfile.store_slug}"
                                target="_blank"
                                class="inline-flex items-center justify-center gap-1.5 w-full h-9 rounded-lg text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 transition-colors"
                            >
                                <i class="ti ti-external-link"></i>
                                <span>Lihat Halaman Toko Resto</span>
                            </a>
                        {/if}
                    </div>

                    <!-- Submit Button Card -->
                    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-2xs space-y-3">
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="w-full h-10 px-4 rounded-lg text-sm font-semibold text-white shadow-xs transition-opacity hover:opacity-90 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                            style="background-color: {primary};"
                        >
                            {#if form.processing}
                                <i class="ti ti-loader-2 animate-spin text-base"></i>
                                <span>Menyimpan...</span>
                            {:else}
                                <i class="ti ti-device-floppy text-base"></i>
                                <span>Simpan Profil Dapur</span>
                            {/if}
                        </button>

                        <Link
                            href="/admin/kitchen"
                            class="inline-flex items-center justify-center gap-1.5 w-full h-9 rounded-lg text-xs font-medium text-slate-700 hover:bg-slate-50 border border-slate-200 transition-colors cursor-pointer"
                        >
                            <i class="ti ti-tools-kitchen-2 text-slate-600"></i>
                            <span>Buka Layar Monitor Dapur (KDS)</span>
                        </Link>
                    </div>
                </div>
            </div>
        </form>
    </main>
</AdminLayout>
