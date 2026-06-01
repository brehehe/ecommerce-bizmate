<script lang="ts">
    import { onMount } from 'svelte';
    import { useForm, router, page } from '@inertiajs/svelte';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { slide } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    import Input from '@/components/ui/Input.svelte';
    import Textarea from '@/components/ui/Textarea.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import ColorPicker from '@/components/ui/ColorPicker.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';

    let { settings = {} } = $props();

    const form = useForm({
        store_logo: null,
        store_name: settings.store_name || '',
        store_email: settings.store_email || '',
        store_phone: settings.store_phone || '',
        store_whatsapp: settings.store_whatsapp || '',
        store_instagram: settings.store_instagram || '',
        store_tiktok: settings.store_tiktok || '',
        store_description: settings.store_description || '',

        primary_color: settings.primary_color || '#0c4cb4',
        secondary_color: settings.secondary_color || '#fa7315',

        tax_enabled:
            settings.tax_enabled === 'true' ||
            settings.tax_enabled === true ||
            settings.tax_enabled === '1',
        tax_percentage: settings.tax_percentage || 0,

        province_id: settings.province_id || '',
        province_name: settings.province_name || '',
        regency_id: settings.regency_id || '',
        regency_name: settings.regency_name || '',
        district_id: settings.district_id || '',
        district_name: settings.district_name || '',
        village_id: settings.village_id || '',
        village_name: settings.village_name || '',
        postal_code: settings.postal_code || '',
        address: settings.address || '',
        latitude: settings.latitude || '-6.2088',
        longitude: settings.longitude || '106.8456',

        bank_name: settings.bank_name || '',
        bank_account: settings.bank_account || '',
        bank_holder: settings.bank_holder || '',
        shipping_rate: settings.shipping_rate || '',
        bank_name: settings.bank_name || '',
        bank_account: settings.bank_account || '',
        bank_holder: settings.bank_holder || '',
        shipping_rate: settings.shipping_rate || '',
        
        rajaongkir_url: settings.rajaongkir_url || 'https://rajaongkir.komerce.id/api/v1/',
        rajaongkir_key: settings.rajaongkir_key || '390d25e9d86ded71cb771c363778cccf',
        storefront_cart_button_style: settings.storefront_cart_button_style || 'button',
        enable_cod:
            settings.enable_cod === 'true' ||
            settings.enable_cod === true ||
            settings.enable_cod === '1',

        coins_enabled:
            settings.coins_enabled === 'true' ||
            settings.coins_enabled === true ||
            settings.coins_enabled === '1',
        coin_conversion_rate: settings.coin_conversion_rate || 1,
        coin_earning_method: settings.coin_earning_method || 'proportional',
        coin_earning_rate_rupiah: settings.coin_earning_rate_rupiah || 1000,
        coin_earning_rate_coins: settings.coin_earning_rate_coins || 1,
        coin_earning_tiers: Array.isArray(settings.coin_earning_tiers)
            ? settings.coin_earning_tiers
            : (settings.coin_earning_tiers ? JSON.parse(settings.coin_earning_tiers) : []),
        coin_min_purchase_redeem: settings.coin_min_purchase_redeem || 0,
        coin_max_redeem_per_txn: settings.coin_max_redeem_per_txn || 50000,
        coin_max_redeem_percentage: settings.coin_max_redeem_percentage || 100,
        coin_terms_conditions: settings.coin_terms_conditions || '',
    });

    let imagePreview = $state(null);

    let provinces = $state([]);
    let regencies = $state([]);
    let districts = $state([]);
    let villages = $state([]);

    let mapContainer: HTMLElement;
    let map: any;
    let marker: any;
    let searchQuery = $state('');
    let mapSearchResults = $state([]);
    let showMapSearchDropdown = $state(false);

    onMount(async () => {
        await fetchProvinces();

        if (form.province_id) await fetchRegencies(form.province_id);
        if (form.regency_id) await fetchDistricts(form.regency_id);
        if (form.district_id) await fetchVillages(form.district_id);

        const L = await import('leaflet');
        await import('leaflet/dist/leaflet.css');

        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl:
                'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
            iconUrl:
                'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
            shadowUrl:
                'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
        });

        map = L.map(mapContainer).setView(
            [parseFloat(form.latitude), parseFloat(form.longitude)],
            13,
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
        }).addTo(map);

        marker = L.marker(
            [parseFloat(form.latitude), parseFloat(form.longitude)],
            { draggable: true },
        ).addTo(map);

        marker.on('dragend', function (e) {
            const position = marker.getLatLng();
            form.latitude = position.lat.toString();
            form.longitude = position.lng.toString();
        });

        map.on('click', function (e) {
            marker.setLatLng(e.latlng);
            form.latitude = e.latlng.lat.toString();
            form.longitude = e.latlng.lng.toString();
        });
    });

    async function searchLocation() {
        if (!searchQuery) return;
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}`,
            );
            const data = await res.json();
            if (data && data.length > 0) {
                mapSearchResults = data;
                showMapSearchDropdown = true;
            } else {
                mapSearchResults = [];
                showMapSearchDropdown = true;
            }
        } catch (e) {
            console.error('Geocoding error', e);
        }
    }

    function selectMapResult(result) {
        const lat = parseFloat(result.lat);
        const lon = parseFloat(result.lon);
        map.setView([lat, lon], 15);
        marker.setLatLng([lat, lon]);
        form.latitude = lat.toString();
        form.longitude = lon.toString();
        searchQuery = result.display_name;
        showMapSearchDropdown = false;
    }

    async function fetchProvinces() {
        const res = await fetch(
            'https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json',
        );
        provinces = await res.json();
    }

    async function fetchRegencies(provinceId: string) {
        const res = await fetch(
            `https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provinceId}.json`,
        );
        regencies = await res.json();
    }

    async function fetchDistricts(regencyId: string) {
        const res = await fetch(
            `https://www.emsifa.com/api-wilayah-indonesia/api/districts/${regencyId}.json`,
        );
        districts = await res.json();
    }

    async function fetchVillages(districtId: string) {
        const res = await fetch(
            `https://www.emsifa.com/api-wilayah-indonesia/api/villages/${districtId}.json`,
        );
        villages = await res.json();
    }

    function handleProvinceChange(id) {
        form.province_name = provinces.find((p) => p.id === id)?.name || '';
        form.regency_id = '';
        form.regency_name = '';
        form.district_id = '';
        form.district_name = '';
        form.village_id = '';
        form.village_name = '';
        regencies = [];
        districts = [];
        villages = [];
        if (id) fetchRegencies(id);
    }

    function handleRegencyChange(id) {
        form.regency_name = regencies.find((r) => r.id === id)?.name || '';
        form.district_id = '';
        form.district_name = '';
        form.village_id = '';
        form.village_name = '';
        districts = [];
        villages = [];
        if (id) fetchDistricts(id);
    }

    function handleDistrictChange(id) {
        form.district_name = districts.find((d) => d.id === id)?.name || '';
        form.village_id = '';
        form.village_name = '';
        villages = [];
        if (id) fetchVillages(id);
    }

    function handleVillageChange(id) {
        form.village_name = villages.find((v) => v.id === id)?.name || '';
    }

    function handleLogoChange(e) {
        if (e.target.files.length) {
            const file = e.target.files[0];
            // Batas maksimal 2MB (2 * 1024 * 1024 byte)
            if (file.size > 2 * 1024 * 1024) {
                showToast(
                    'Ukuran berkas logo terlalu besar. Maksimal 2MB.',
                    'error',
                );
                e.target.value = ''; // Reset file input
                return;
            }
            form.store_logo = file;
            imagePreview = URL.createObjectURL(file);
        }
    }

    function addEarningTier() {
        form.coin_earning_tiers = [
            ...form.coin_earning_tiers,
            { min_purchase: 50000, earn_coins: 50 }
        ];
    }

    function removeEarningTier(index: number) {
        form.coin_earning_tiers = form.coin_earning_tiers.filter((_, i) => i !== index);
    }

    function submit() {
        form.transform((data) => ({
            ...data,
            coin_earning_tiers: JSON.stringify(data.coin_earning_tiers || []),
        })).post('/admin/settings', {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Pengaturan berhasil disimpan!', 'success');
            },
        });
    }

    function backupDatabase() {
        alert('Fitur Backup sedang dalam pengembangan.');
    }
    function restoreDatabase(e) {
        alert('Fitur Restore sedang dalam pengembangan.');
    }
    function resetDatabase() {
        if (confirm('Yakin ingin melakukan reset?'))
            alert('Fitur Reset sedang dalam pengembangan.');
    }
</script>

<svelte:head>
    <title>Pengaturan Toko</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-8">
        <form
            onsubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <div
                class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8"
            >
                <div>
                    <h1 class="font-outfit font-black text-2xl text-slate-800">
                        Pengaturan Terpadu
                    </h1>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider"
                    >
                        Konfigurasi informasi toko, rekening pembayaran, metode
                        checkout, dan branding warna sistem.
                    </p>
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 self-stretch sm:self-auto justify-center disabled:opacity-70"
                    style="background-color: {primaryColor};"
                    aria-label="Simpan Semua Pengaturan"
                >
                    {#if form.processing}
                        <i class="ti ti-loader animate-spin text-lg"></i>
                        Menyimpan...
                    {:else}
                        <i class="ti ti-device-floppy text-lg"></i>
                        Simpan Semua
                    {/if}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div class="lg:col-span-8 space-y-8">
                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-slate-50 rounded-xl"
                                style="color: {primaryColor}; background-color: {primaryColor}1A;"
                            >
                                <i class="ti ti-building-store text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Profil & Informasi Toko
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Informasi utama toko yang ditampilkan di
                                    landing page & invoice
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <Input
                                id="input-shop-name"
                                bind:value={form.store_name}
                                label="Nama Toko"
                                placeholder="Contoh: Bizmate Premium Store"
                                required={true}
                            />
                            <Input
                                id="input-shop-email"
                                bind:value={form.store_email}
                                label="Email Kontak Toko"
                                type="email"
                                placeholder="Contoh: support@bizmate.id"
                                required={true}
                            />
                            <Input
                                id="input-shop-phone"
                                bind:value={form.store_phone}
                                label="Telepon Toko"
                                placeholder="Contoh: 021-xxxxxx"
                            />
                            <Input
                                id="input-shop-whatsapp"
                                bind:value={form.store_whatsapp}
                                label="WhatsApp Toko (WA-Link)"
                                placeholder="Contoh: 6285179720622"
                                required={true}
                            />
                            <Input
                                id="input-shop-instagram"
                                bind:value={form.store_instagram}
                                label="Instagram Toko"
                                placeholder="bizmate.premium"
                                prefix="@"
                            />
                            <Input
                                id="input-shop-tiktok"
                                bind:value={form.store_tiktok}
                                label="TikTok Toko"
                                placeholder="bizmate.official"
                                prefix="@"
                            />

                            <div class="sm:col-span-2">
                                <Textarea
                                    id="input-shop-description"
                                    bind:value={form.store_description}
                                    label="Deskripsi Singkat Toko"
                                    placeholder="Tuliskan slogan / penjelasan singkat mengenai kelebihan toko Anda..."
                                    rows={3}
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-rose-50 text-rose-500 rounded-xl"
                            >
                                <i class="ti ti-map-pin text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Alamat Lengkap & Peta
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Konfigurasi lokasi toko untuk perhitungan
                                    ongkos kirim dan navigasi.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <SelectSearch
                                bind:value={form.province_id}
                                options={provinces}
                                label="Provinsi"
                                placeholder="Pilih Provinsi"
                                required={true}
                                onchange={handleProvinceChange}
                            />
                            <SelectSearch
                                bind:value={form.regency_id}
                                options={regencies}
                                label="Kota / Kabupaten"
                                placeholder="Pilih Kota/Kabupaten"
                                required={true}
                                disabled={!regencies.length &&
                                    !form.province_id}
                                onchange={handleRegencyChange}
                            />
                            <SelectSearch
                                bind:value={form.district_id}
                                options={districts}
                                label="Kecamatan"
                                placeholder="Pilih Kecamatan"
                                required={true}
                                disabled={!districts.length && !form.regency_id}
                                onchange={handleDistrictChange}
                            />
                            <SelectSearch
                                bind:value={form.village_id}
                                options={villages}
                                label="Kelurahan"
                                placeholder="Pilih Kelurahan"
                                required={true}
                                disabled={!villages.length && !form.district_id}
                                onchange={handleVillageChange}
                            />

                            <div class="col-span-full">
                                <Input
                                    bind:value={form.postal_code}
                                    label="Kode Pos"
                                    placeholder="Masukkan Kode Pos"
                                    required={true}
                                />
                            </div>

                            <div class="col-span-full">
                                <Textarea
                                    bind:value={form.address}
                                    label="Alamat Lengkap"
                                    placeholder="Nama Jalan, Gedung, No. Rumah..."
                                    rows={2}
                                />
                            </div>
                        </div>

                        <div class="mt-8 border-t border-slate-100 pt-6">
                            <label
                                class="block text-xs font-bold text-slate-600 mb-2"
                                >Titik Peta (Pin Lokasi)</label
                            >

                            <div class="relative mb-4">
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        bind:value={searchQuery}
                                        placeholder="Cari alamat di peta..."
                                        class="flex-grow px-4 py-2.5 rounded-lg border border-slate-200 outline-none text-sm"
                                        onkeydown={(e) =>
                                            e.key === 'Enter' &&
                                            searchLocation()}
                                    />
                                    <button
                                        type="button"
                                        onclick={searchLocation}
                                        class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-sm transition flex items-center gap-2"
                                    >
                                        <i class="ti ti-search"></i> Cari
                                    </button>
                                </div>

                                {#if showMapSearchDropdown}
                                    <div
                                        class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto flex flex-col"
                                    >
                                        <div
                                            class="flex justify-between items-center p-3 border-b border-slate-100 bg-slate-50 sticky top-0"
                                        >
                                            <span
                                                class="text-xs font-bold text-slate-500"
                                                >Hasil Pencarian</span
                                            >
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    (showMapSearchDropdown = false)}
                                                class="text-slate-400 hover:text-slate-700"
                                            >
                                                <i class="ti ti-x"></i>
                                            </button>
                                        </div>
                                        {#if mapSearchResults.length === 0}
                                            <div
                                                class="p-4 text-center text-sm text-slate-500"
                                            >
                                                Alamat tidak ditemukan
                                            </div>
                                        {:else}
                                            {#each mapSearchResults as result}
                                                <div
                                                    class="p-3 text-sm hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0"
                                                    onclick={() =>
                                                        selectMapResult(result)}
                                                >
                                                    <span
                                                        class="block text-slate-800 font-medium"
                                                        >{result.name ||
                                                            result.display_name.split(
                                                                ',',
                                                            )[0]}</span
                                                    >
                                                    <span
                                                        class="block text-slate-500 text-xs mt-0.5 truncate"
                                                        >{result.display_name}</span
                                                    >
                                                </div>
                                            {/each}
                                        {/if}
                                    </div>
                                {/if}
                            </div>

                            <div
                                bind:this={mapContainer}
                                class="w-full h-80 rounded-2xl border border-slate-200 z-10 relative mb-4"
                            ></div>

                            <div class="flex gap-4">
                                <div class="flex-grow">
                                    <Input
                                        bind:value={form.latitude}
                                        label="Latitude"
                                        readonly={true}
                                    />
                                </div>
                                <div class="flex-grow">
                                    <Input
                                        bind:value={form.longitude}
                                        label="Longitude"
                                        readonly={true}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LOYALTY COIN SYSTEM -->
                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-amber-50 text-amber-500 rounded-xl"
                            >
                                <i class="ti ti-coins text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Sistem Loyalty Koin Toko
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Atur konversi koin belanja, batas penukaran, dan metode perolehan koin kustom.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Toggle Active -->
                            <Toggle
                                bind:checked={form.coins_enabled}
                                label="Aktifkan Sistem Koin Toko"
                                icon="ti-coin"
                            />

                            {#if form.coins_enabled}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2" transition:slide>
                                    <!-- Conversion rate: 1 koin = X Rupiah -->
                                    <InputCurrency
                                        id="input-coin-rate"
                                        bind:value={form.coin_conversion_rate}
                                        label="Nilai Tukar Koin (1 Koin = Berapa Rupiah?)"
                                        placeholder="1"
                                        required={true}
                                    />

                                    <!-- Min purchase to redeem coins -->
                                    <InputCurrency
                                        id="input-coin-min-redeem"
                                        bind:value={form.coin_min_purchase_redeem}
                                        label="Minimal Subtotal Pembelian untuk Menukar Koin"
                                        placeholder="0"
                                        required={true}
                                    />

                                    <!-- Max redeem per transaction -->
                                    <InputCurrency
                                        id="input-coin-max-redeem"
                                        bind:value={form.coin_max_redeem_per_txn}
                                        label="Maksimal Koin yang Dapat Ditukar per Transaksi"
                                        placeholder="50000"
                                        required={true}
                                    />

                                    <!-- Max percentage of transaction that can be paid using coins -->
                                    <div class="space-y-1.5 font-sans">
                                        <label for="input-coin-max-percentage" class="block text-xs font-bold text-slate-600">
                                            Maksimal Persentase Diskon Koin (%)
                                        </label>
                                        <div class="relative">
                                            <input
                                                id="input-coin-max-percentage"
                                                type="number"
                                                min="1"
                                                max="100"
                                                bind:value={form.coin_max_redeem_percentage}
                                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition font-sans"
                                                placeholder="Contoh: 50"
                                                required
                                            />
                                            <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-400 font-bold font-sans">
                                                %
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="h-px bg-slate-100 my-2"></div>

                                <!-- EARNING METHOD -->
                                <div class="space-y-4" transition:slide>
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-tight block">
                                        Metode Perolehan Koin (Earning)
                                    </span>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <button
                                            type="button"
                                            onclick={() => form.coin_earning_method = 'proportional'}
                                            class="flex flex-col items-start p-4 rounded-2xl border text-left transition duration-200 hover:bg-slate-50 cursor-pointer
                                                   {form.coin_earning_method === 'proportional'
                                                       ? 'border-blue-500 bg-blue-50/20'
                                                       : 'border-slate-200 bg-white'}"
                                        >
                                            <span class="text-xs font-bold text-slate-800">Kelipatan Belanja (Akumulatif)</span>
                                            <span class="text-[10px] text-slate-400 mt-1">Dapatkan koin berdasarkan kelipatan nilai total belanja.</span>
                                        </button>

                                        <button
                                            type="button"
                                            onclick={() => form.coin_earning_method = 'tiered'}
                                            class="flex flex-col items-start p-4 rounded-2xl border text-left transition duration-200 hover:bg-slate-50 cursor-pointer
                                                   {form.coin_earning_method === 'tiered'
                                                       ? 'border-blue-500 bg-blue-50/20'
                                                       : 'border-slate-200 bg-white'}"
                                        >
                                            <span class="text-xs font-bold text-slate-800">Tingkat Belanja (Custom Tiers)</span>
                                            <span class="text-[10px] text-slate-400 mt-1">Dapatkan jumlah koin tetap berdasarkan tingkat nominal belanja tertentu.</span>
                                        </button>
                                    </div>

                                    {#if form.coin_earning_method === 'proportional'}
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-slate-50 p-5 rounded-2xl border border-slate-100/60 mt-3" transition:slide>
                                            <!-- Earning Rate: X Rupiah spent earns Y coins -->
                                            <InputCurrency
                                                id="input-coin-earn-rupiah"
                                                bind:value={form.coin_earning_rate_rupiah}
                                                label="Setiap Kelipatan Pembelian Sebesar"
                                                placeholder="1000"
                                                required={true}
                                            />

                                            <div class="space-y-1.5 font-sans">
                                                <label for="input-coin-earn-coins" class="block text-xs font-bold text-slate-600">
                                                    Mendapatkan Koin Sejumlah
                                                </label>
                                                <input
                                                    id="input-coin-earn-coins"
                                                    type="number"
                                                    min="1"
                                                    bind:value={form.coin_earning_rate_coins}
                                                    class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition font-sans"
                                                    placeholder="1"
                                                    required
                                                />
                                            </div>
                                            <p class="col-span-full text-[10px] text-slate-400 font-bold leading-normal italic font-sans">
                                                * Contoh: Jika diset Rp 10.000 mendapatkan 1 Koin, pembeli dengan transaksi Rp 50.000 akan mendapatkan 5 Koin secara akumulatif.
                                            </p>
                                        </div>
                                    {:else if form.coin_earning_method === 'tiered'}
                                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100/60 space-y-4 mt-3" transition:slide>
                                            <div class="flex justify-between items-center font-sans">
                                                <span class="text-xs font-bold text-slate-700">Aturan Tingkat Earning Koin</span>
                                                <button
                                                    type="button"
                                                    onclick={addEarningTier}
                                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] rounded-xl flex items-center gap-1 transition shadow-sm"
                                                >
                                                    <i class="ti ti-plus"></i> Tambah Tingkatan
                                                </button>
                                            </div>

                                            {#if form.coin_earning_tiers.length === 0}
                                                <div class="text-center py-6 border border-dashed border-slate-200 rounded-xl bg-white font-sans">
                                                    <i class="ti ti-info-circle text-slate-300 text-2xl mb-1.5 block"></i>
                                                    <span class="text-[11px] font-bold text-slate-500">Belum Ada Aturan Tingkatan</span>
                                                    <p class="text-[9px] text-slate-400 mt-0.5 leading-none">Klik "+ Tambah Tingkatan" untuk membuat aturan baru.</p>
                                                </div>
                                            {:else}
                                                <div class="space-y-3.5 font-sans">
                                                    {#each form.coin_earning_tiers as tier, index}
                                                        <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-200/50 shadow-sm" transition:slide>
                                                            <div class="flex-grow grid grid-cols-2 gap-3.5">
                                                                <div class="space-y-1">
                                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Min. Belanja (Subtotal)</span>
                                                                    <div class="relative">
                                                                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">Rp</span>
                                                                        <input
                                                                            type="number"
                                                                            min="0"
                                                                            bind:value={tier.min_purchase}
                                                                            class="w-full pl-9 pr-3.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:outline-none transition font-sans"
                                                                            placeholder="50000"
                                                                            required
                                                                        />
                                                                    </div>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Koin yang Didapat</span>
                                                                    <div class="relative font-sans">
                                                                        <input
                                                                            type="number"
                                                                            min="1"
                                                                            bind:value={tier.earn_coins}
                                                                            class="w-full px-3.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:outline-none transition font-sans"
                                                                            placeholder="50"
                                                                            required
                                                                        />
                                                                        <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-bold font-sans">Koin</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onclick={() => removeEarningTier(index)}
                                                                class="p-2.5 text-rose-500 hover:bg-rose-50 rounded-xl transition self-end cursor-pointer"
                                                                title="Hapus Aturan"
                                                            >
                                                                <i class="ti ti-trash text-sm"></i>
                                                            </button>
                                                        </div>
                                                    {/each}
                                                    <p class="text-[10px] text-slate-400 font-bold leading-normal italic font-sans">
                                                        * Sistem akan memilih tingkatan dengan syarat nominal pembelian tertinggi yang berhasil dipenuhi oleh belanjaan pelanggan.
                                                    </p>
                                                </div>
                                            {/if}
                                        </div>
                                    {/if}
                                </div>

                                <div class="h-px bg-slate-100 my-2"></div>

                                <!-- Terms & Conditions -->
                                <div class="space-y-1.5" transition:slide>
                                    <Textarea
                                        id="input-coin-tnc"
                                        bind:value={form.coin_terms_conditions}
                                        label="Syarat & Ketentuan Sistem Koin (S&K)"
                                        placeholder="Tuliskan aturan, ketentuan kedaluwarsa, dan S&K penggunaan koin untuk dibaca oleh pelanggan..."
                                        rows={3}
                                    />
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4 space-y-8">
                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-purple-50 text-purple-500 rounded-xl"
                            >
                                <i class="ti ti-photo text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Logo Toko
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Klik kotak untuk mengganti logo
                                </p>
                            </div>
                        </div>

                        <div
                            class="relative w-full aspect-video sm:aspect-square max-w-[240px] mx-auto rounded-3xl border-2 border-dashed border-slate-200 bg-slate-50 transition cursor-pointer flex flex-col items-center justify-center overflow-hidden group"
                        >
                            <input
                                type="file"
                                accept="image/*"
                                onchange={handleLogoChange}
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                            />

                            {#if imagePreview || settings.store_logo}
                                <img
                                    src={imagePreview || settings.store_logo}
                                    alt="Logo"
                                    class="w-full h-full object-cover"
                                />
                                <div
                                    class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center pointer-events-none"
                                >
                                    <div class="flex flex-col items-center">
                                        <i
                                            class="ti ti-photo-edit text-2xl text-white mb-1"
                                        ></i>
                                        <span
                                            class="text-white font-bold text-xs"
                                            >Ubah Logo</span
                                        >
                                    </div>
                                </div>
                            {:else}
                                <i
                                    class="ti ti-cloud-upload text-4xl text-slate-300 mb-2 transition-colors"
                                ></i>
                                <span
                                    class="text-xs font-bold text-slate-400 transition-colors text-center px-4"
                                    >Upload gambar JPG/PNG</span
                                >
                            {/if}
                        </div>
                    </div>

                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-slate-50"
                                style="color: {secondaryColor}; background-color: {secondaryColor}1A; rounded-xl"
                            >
                                <i class="ti ti-palette text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Warna Brand (Theme)
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Warna branding toko.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <ColorPicker
                                id="primary_color"
                                label="Primary Color"
                                bind:value={form.primary_color}
                                class="font-mono uppercase"
                            />
                            <ColorPicker
                                id="secondary_color"
                                label="Secondary Color"
                                bind:value={form.secondary_color}
                                class="font-mono uppercase"
                            />
                        </div>
                    </div>


                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-indigo-50 text-indigo-500 rounded-xl"
                            >
                                <i class="ti ti-shopping-cart-discount text-lg"
                                ></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Checkout & Ongkir
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Konfigurasi metode kirim & bayar.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div class="space-y-3.5">
                                <span
                                    class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                    >Pengaturan Pajak</span
                                >
                                <Toggle
                                    bind:checked={form.tax_enabled}
                                    label="Pajak (Tax) Aktif"
                                    icon="ti-receipt-tax"
                                />

                                {#if form.tax_enabled}
                                    <div transition:slide>
                                        <label
                                            class="block text-xs font-bold text-slate-600 mb-2 mt-2"
                                            >Nominal Pajak (%)</label
                                        >
                                        <div class="relative">
                                            <input
                                                type="number"
                                                step="0.1"
                                                bind:value={form.tax_percentage}
                                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition"
                                                placeholder="Contoh: 11"
                                            />
                                            <div
                                                class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-400 font-bold"
                                            >
                                                %
                                            </div>
                                        </div>
                                    </div>
                                {/if}
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <div class="space-y-2">
                                <InputCurrency
                                    id="input-app-fee"
                                    bind:value={form.shipping_rate}
                                    label="Biaya Aplikasi"
                                    placeholder="2500"
                                    required={true}
                                />
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <!-- <div class="space-y-3.5">
                                <span
                                    class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                    >Metode Pembayaran</span
                                >
                                <Toggle
                                    bind:checked={form.enable_cod}
                                    label="COD (Bayar di Tempat)"
                                    icon="ti-cash"
                                />
                            </div> -->

                            <div class="space-y-3.5">
                                <span class="text-xs font-black text-slate-700 uppercase tracking-tight block">
                                    Konfigurasi Rajaongkir
                                </span>
                                <Input
                                    id="input-rajaongkir-url"
                                    bind:value={form.rajaongkir_url}
                                    label="Rajaongkir Base URL"
                                    placeholder="Contoh: https://rajaongkir.komerce.id/api/v1/"
                                    required={true}
                                />
                                <Input
                                    id="input-rajaongkir-key"
                                    bind:value={form.rajaongkir_key}
                                    label="Rajaongkir API Key"
                                    placeholder="Masukkan API Key Rajaongkir Anda"
                                    required={true}
                                />
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <div class="space-y-3">
                                <span class="text-xs font-black text-slate-700 uppercase tracking-tight block">
                                    Desain Tombol Keranjang (Storefront)
                                </span>
                                <p class="text-[11px] text-slate-400 font-bold">
                                    Pilih tampilan tombol "+ Keranjang" pada daftar produk di halaman depan toko Anda.
                                </p>
                                <div class="grid grid-cols-3 gap-2 mt-2">
                                    <button
                                        type="button"
                                        onclick={() => form.storefront_cart_button_style = 'button'}
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition duration-200 hover:bg-slate-50 cursor-pointer
                                               {form.storefront_cart_button_style === 'button'
                                                   ? 'border-blue-500 bg-blue-50/20'
                                                   : 'border-slate-200 bg-white'}"
                                    >
                                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mb-2">
                                            <i class="ti ti-shopping-cart text-lg"></i>
                                        </div>
                                        <span class="text-[11px] font-bold text-slate-700">Tombol Bawah</span>
                                        <span class="text-[9px] text-slate-400 mt-0.5 leading-none">"+ KERANJANG"</span>
                                    </button>

                                    <button
                                        type="button"
                                        onclick={() => form.storefront_cart_button_style = 'icon'}
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition duration-200 hover:bg-slate-50 cursor-pointer
                                               {form.storefront_cart_button_style === 'icon'
                                                   ? 'border-blue-500 bg-blue-50/20'
                                                   : 'border-slate-200 bg-white'}"
                                    >
                                        <div class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center mb-2">
                                            <i class="ti ti-plus text-lg"></i>
                                        </div>
                                        <span class="text-[11px] font-bold text-slate-700">Ikon Pojok</span>
                                        <span class="text-[9px] text-slate-400 mt-0.5 leading-none">"+" di Foto</span>
                                    </button>

                                    <button
                                        type="button"
                                        onclick={() => form.storefront_cart_button_style = 'none'}
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition duration-200 hover:bg-slate-50 cursor-pointer
                                               {form.storefront_cart_button_style === 'none'
                                                   ? 'border-blue-500 bg-blue-50/20'
                                                   : 'border-slate-200 bg-white'}"
                                    >
                                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-2">
                                            <i class="ti ti-eye text-lg"></i>
                                        </div>
                                        <span class="text-[11px] font-bold text-slate-700">Tanpa Tombol</span>
                                        <span class="text-[9px] text-slate-400 mt-0.5 leading-none">Hanya Detail</span>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-rose-50 text-rose-600 rounded-xl"
                            >
                                <i class="ti ti-database text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Database Maintenance
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Pemeliharaan, backup, dan reset database
                                    sistem
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <button
                                type="button"
                                onclick={backupDatabase}
                                class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl transition flex items-center justify-center gap-2"
                            >
                                <i class="ti ti-download"></i>
                                Backup Database (JSON)
                            </button>

                            <div class="space-y-2">
                                <span
                                    class="text-xs font-bold text-slate-500 block"
                                    >Restore Database</span
                                >
                                <div class="relative">
                                    <label
                                        for="restore-file-input"
                                        class="w-full py-3 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-sm rounded-xl transition flex items-center justify-center gap-2 cursor-pointer"
                                    >
                                        <i class="ti ti-upload"></i> Upload Backup
                                        File
                                    </label>
                                    <input
                                        type="file"
                                        id="restore-file-input"
                                        accept=".json"
                                        class="hidden"
                                        onchange={restoreDatabase}
                                    />
                                </div>
                            </div>

                            <div class="h-px bg-slate-100 my-4"></div>

                            <button
                                type="button"
                                onclick={resetDatabase}
                                class="w-full py-3 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-sm rounded-xl transition flex items-center justify-center gap-2"
                            >
                                <i class="ti ti-refresh-alert"></i>
                                Reset ke Kondisi Pabrik (Re-seed)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</AdminLayout>
