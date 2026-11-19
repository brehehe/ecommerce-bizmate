<script lang="ts">
    import { useForm, page, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        storeName = 'Bizmate Food',
        storeLogo = null,
        culinaryCategories = [],
        currentUser = null,
    } = $props();

    const primaryColor = $derived(
        (page.props as any).theme?.primary_color || '#ea580c', // Orange warm culinary theme
    );
    const secondaryColor = $derived(
        (page.props as any).theme?.secondary_color || '#f97316',
    );

    const isGuest = $derived(!currentUser);

    const form = useForm(() => ({
        // Owner / User data (for guest)
        name: currentUser?.name || '',
        email: currentUser?.email || '',
        phone_number: currentUser?.phone_number || '',
        password: '',
        password_confirmation: '',

        // Resto info
        resto_name: '',
        culinary_category: culinaryCategories[0] || 'Aneka Nasi & Lauk',
        description: '',
        full_address: '',
        province_name: '',
        regency_name: '',
        district_name: '',
        postal_code: '',
        latitude: '',
        longitude: '',
        avg_prep_time_minutes: 20,
        allow_pickup: true,
        allow_delivery: true,
        operating_hours: {
            monday: { active: true, open: '09:00', close: '21:00' },
            tuesday: { active: true, open: '09:00', close: '21:00' },
            wednesday: { active: true, open: '09:00', close: '21:00' },
            thursday: { active: true, open: '09:00', close: '21:00' },
            friday: { active: true, open: '09:00', close: '21:00' },
            saturday: { active: true, open: '09:00', close: '22:00' },
            sunday: { active: true, open: '09:00', close: '22:00' },
        },
    }));

    let currentStep = $state(1);
    let showPassword = $state(false);
    let showPasswordConfirmation = $state(false);

    const dayLabels: Record<string, string> = {
        monday: 'Senin',
        tuesday: 'Selasa',
        wednesday: 'Rabu',
        thursday: 'Kamis',
        friday: 'Jumat',
        saturday: 'Sabtu',
        sunday: 'Minggu',
    };

    const nextStep = () => {
        if (currentStep === 1 && isGuest) {
            if (!form.name || !form.email || !form.phone_number || !form.password) {
                showToast('Mohon lengkapi data pemilik & kata sandi.', 'error', 'top');
                return;
            }
            if (form.password !== form.password_confirmation) {
                showToast('Konfirmasi kata sandi tidak cocok.', 'error', 'top');
                return;
            }
        }
        if (currentStep === 2) {
            if (!form.resto_name || !form.culinary_category) {
                showToast('Mohon isi nama resto dan kategori kuliner.', 'error', 'top');
                return;
            }
        }
        if (currentStep === 3) {
            if (!form.full_address) {
                showToast('Mohon isi alamat lengkap dapur resto.', 'error', 'top');
                return;
            }
        }
        if (currentStep < 4) {
            currentStep += 1;
        }
    };

    const prevStep = () => {
        if (currentStep > 1) {
            currentStep -= 1;
        }
    };

    const submit = () => {
        form.post('/register-resto', {
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                if (firstError) {
                    showToast(firstError as string, 'error', 'top');
                }
            },
        });
    };

    const detectLocation = () => {
        if (!navigator.geolocation) {
            showToast('Browser Anda tidak mendukung deteksi lokasi.', 'warning', 'top');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                form.latitude = String(pos.coords.latitude.toFixed(7));
                form.longitude = String(pos.coords.longitude.toFixed(7));
                showToast('Koordinat GPS berhasil diperoleh!', 'success', 'top');
            },
            () => {
                showToast('Gagal memperoleh titik GPS. Silakan isi manual jika diperlukan.', 'warning', 'top');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    };
</script>

<svelte:head>
    <title>Daftar Mitra Resto - {storeName}</title>
</svelte:head>

<div class="min-h-dvh w-full bg-slate-50 flex flex-col font-sans selection:bg-orange-500 selection:text-white">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <Link href="/" class="flex items-center gap-2.5">
                {#if storeLogo}
                    <img src={storeLogo} alt={storeName} class="h-8 w-auto object-contain" />
                {:else}
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-orange-600 to-amber-500 flex items-center justify-center text-white shadow-sm font-bold text-lg">
                        🍲
                    </div>
                {/if}
                <div class="flex flex-col">
                    <span class="text-base font-extrabold text-slate-900 tracking-tight leading-none">{storeName}</span>
                    <span class="text-[11px] font-semibold text-orange-600 tracking-wider uppercase mt-0.5">Portal Mitra Resto</span>
                </div>
            </Link>

            <div class="text-xs text-slate-500">
                Sudah punya akun?
                <Link href="/login" class="text-orange-600 font-bold hover:underline ml-1">Masuk</Link>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-4xl w-full mx-auto px-4 py-8 md:py-12">
        <!-- Title & Subtitle -->
        <div class="text-center max-w-xl mx-auto mb-8">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800 mb-3">
                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                Pendaftaran Dapur Kuliner
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">
                Buka & Kembangkan Resto Anda
            </h1>
            <p class="text-sm text-slate-600 mt-2">
                Terima pesanan bawa pulang (takeaway) dan pengantaran kurir resto langsung tanpa potongan komisi mencekik.
            </p>
        </div>

        {#if currentUser}
            <div class="mb-6 p-4 rounded-xl bg-blue-50 border border-blue-200 flex items-center gap-3 text-sm text-blue-900">
                <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0 font-bold">
                    ✓
                </div>
                <div>
                    <p class="font-bold">Mendaftarkan resto untuk akun terdaftar:</p>
                    <p class="text-xs text-blue-700">{currentUser.name} ({currentUser.email}) - Akun Anda akan ditingkatkan menjadi Mitra Resto.</p>
                </div>
            </div>
        {/if}

        <!-- Wizard Stepper Indicator -->
        <div class="mb-8">
            <div class="grid grid-cols-4 gap-2 text-center text-xs font-semibold">
                <!-- Step 1 -->
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1 transition-all {currentStep >= 1 ? 'bg-orange-600 text-white shadow-sm' : 'bg-slate-200 text-slate-500'}">
                        1
                    </div>
                    <span class="{currentStep >= 1 ? 'text-slate-900 font-bold' : 'text-slate-400'}">Pemilik</span>
                </div>
                <!-- Step 2 -->
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1 transition-all {currentStep >= 2 ? 'bg-orange-600 text-white shadow-sm' : 'bg-slate-200 text-slate-500'}">
                        2
                    </div>
                    <span class="{currentStep >= 2 ? 'text-slate-900 font-bold' : 'text-slate-400'}">Resto</span>
                </div>
                <!-- Step 3 -->
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1 transition-all {currentStep >= 3 ? 'bg-orange-600 text-white shadow-sm' : 'bg-slate-200 text-slate-500'}">
                        3
                    </div>
                    <span class="{currentStep >= 3 ? 'text-slate-900 font-bold' : 'text-slate-400'}">Lokasi GPS</span>
                </div>
                <!-- Step 4 -->
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold mb-1 transition-all {currentStep >= 4 ? 'bg-orange-600 text-white shadow-sm' : 'bg-slate-200 text-slate-500'}">
                        4
                    </div>
                    <span class="{currentStep >= 4 ? 'text-slate-900 font-bold' : 'text-slate-400'}">Operasional</span>
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="w-full bg-slate-200 h-1.5 rounded-full mt-3 overflow-hidden">
                <div
                    class="h-full bg-orange-600 transition-all duration-300"
                    style="width: {(currentStep / 4) * 100}%;"
                ></div>
            </div>
        </div>

        <!-- Form Card Container -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8 shadow-xs">
            <!-- STEP 1: DATA PEMILIK -->
            {#if currentStep === 1}
                <div>
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Langkah 1: Data Pemilik / Penanggung Jawab</h2>
                    <p class="text-xs text-slate-500 mb-6">Informasi kontak ini digunakan untuk verifikasi dan notifikasi pesanan masuk.</p>

                    {#if isGuest}
                        <div class="space-y-4">
                            <div>
                                <label for="owner-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap Pemilik *</label>
                                <input
                                    id="owner-name"
                                    type="text"
                                    bind:value={form.name}
                                    placeholder="Contoh: Budi Santoso"
                                    class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                                {#if form.errors.name}
                                    <p class="text-xs text-red-600 mt-1">{form.errors.name}</p>
                                {/if}
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="owner-email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alamat Email *</label>
                                    <input
                                        id="owner-email"
                                        type="email"
                                        bind:value={form.email}
                                        placeholder="resto@anda.com"
                                        class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    />
                                    {#if form.errors.email}
                                        <p class="text-xs text-red-600 mt-1">{form.errors.email}</p>
                                    {/if}
                                </div>

                                <div>
                                    <label for="owner-phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp / HP *</label>
                                    <input
                                        id="owner-phone"
                                        type="tel"
                                        bind:value={form.phone_number}
                                        placeholder="081234567890"
                                        class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    />
                                    {#if form.errors.phone_number}
                                        <p class="text-xs text-red-600 mt-1">{form.errors.phone_number}</p>
                                    {/if}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="owner-password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kata Sandi Akun *</label>
                                    <div class="relative">
                                        <input
                                            id="owner-password"
                                            type={showPassword ? 'text' : 'password'}
                                            bind:value={form.password}
                                            placeholder="Minimal 8 karakter"
                                            class="w-full h-11 px-3.5 pr-10 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        />
                                        <button
                                            type="button"
                                            onclick={() => (showPassword = !showPassword)}
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600"
                                        >
                                            {showPassword ? 'Sembunyikan' : 'Lihat'}
                                        </button>
                                    </div>
                                    {#if form.errors.password}
                                        <p class="text-xs text-red-600 mt-1">{form.errors.password}</p>
                                    {/if}
                                </div>

                                <div>
                                    <label for="owner-password-confirm" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Konfirmasi Sandi *</label>
                                    <div class="relative">
                                        <input
                                            id="owner-password-confirm"
                                            type={showPasswordConfirmation ? 'text' : 'password'}
                                            bind:value={form.password_confirmation}
                                            placeholder="Ulangi kata sandi"
                                            class="w-full h-11 px-3.5 pr-10 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                        />
                                        <button
                                            type="button"
                                            onclick={() => (showPasswordConfirmation = !showPasswordConfirmation)}
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600"
                                        >
                                            {showPasswordConfirmation ? 'Sembunyikan' : 'Lihat'}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {:else}
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm">
                            <p class="font-semibold text-slate-900">Data Akun Terverifikasi:</p>
                            <p class="text-slate-600 text-xs mt-1">Nama: <span class="font-medium text-slate-800">{currentUser.name}</span></p>
                            <p class="text-slate-600 text-xs">Email: <span class="font-medium text-slate-800">{currentUser.email}</span></p>
                            <p class="text-slate-600 text-xs">WhatsApp: <span class="font-medium text-slate-800">{currentUser.phone_number || '-'}</span></p>
                        </div>
                    {/if}
                </div>
            {/if}

            <!-- STEP 2: PROFIL RESTO & KULINER -->
            {#if currentStep === 2}
                <div>
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Langkah 2: Identitas Resto & Konsep Menu</h2>
                    <p class="text-xs text-slate-500 mb-6">Nama dan kategori ini akan ditampilkan langsung di etalase pelanggan.</p>

                    <div class="space-y-4">
                        <div>
                            <label for="resto-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Resto / Usaha Kuliner *</label>
                            <input
                                id="resto-name"
                                type="text"
                                bind:value={form.resto_name}
                                placeholder="Contoh: Bebek Sinjay Express, Warung Kopi Senja"
                                class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            />
                            {#if form.errors.resto_name}
                                <p class="text-xs text-red-600 mt-1">{form.errors.resto_name}</p>
                            {/if}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="culinary-category" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kategori Kuliner *</label>
                                <select
                                    id="culinary-category"
                                    bind:value={form.culinary_category}
                                    class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white"
                                >
                                    {#each culinaryCategories as cat}
                                        <option value={cat}>{cat}</option>
                                    {/each}
                                </select>
                            </div>

                            <div>
                                <label for="prep-time" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Rata-rata Waktu Masak (Menit)</label>
                                <input
                                    id="prep-time"
                                    type="number"
                                    min="5"
                                    max="120"
                                    step="5"
                                    bind:value={form.avg_prep_time_minutes}
                                    class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                                <span class="text-[11px] text-slate-400 mt-0.5 block">Digunakan menghitung estimasi jam pesanan siap.</span>
                            </div>
                        </div>

                        <div>
                            <label for="resto-description" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Deskripsi Singkat Resto</label>
                            <textarea
                                id="resto-description"
                                rows="3"
                                bind:value={form.description}
                                placeholder="Jelaskan menu andalan atau keunikan rasa resto Anda..."
                                class="w-full p-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            ></textarea>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- STEP 3: LOKASI DAPUR & KOORDINAT GPS -->
            {#if currentStep === 3}
                <div>
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Langkah 3: Alamat Dapur & Titik Koordinat GPS</h2>
                    <p class="text-xs text-slate-500 mb-6">Titik GPS sangat penting agar sistem dapat menghitung radius antar kurir resto & ongkos kirim secara presisi.</p>

                    <div class="space-y-4">
                        <div>
                            <label for="full-address" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alamat Lengkap Dapur / Outlet *</label>
                            <textarea
                                id="full-address"
                                rows="3"
                                bind:value={form.full_address}
                                placeholder="Nama jalan, nomor ruko/rumah, patokan gerbang, RT/RW, kelurahan, kecamatan..."
                                class="w-full p-3 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            ></textarea>
                            {#if form.errors.full_address}
                                <p class="text-xs text-red-600 mt-1">{form.errors.full_address}</p>
                            {/if}
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="regency-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kota / Kabupaten</label>
                                <input
                                    id="regency-name"
                                    type="text"
                                    bind:value={form.regency_name}
                                    placeholder="Contoh: Kota Malang"
                                    class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                            </div>
                            <div>
                                <label for="district-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kecamatan</label>
                                <input
                                    id="district-name"
                                    type="text"
                                    bind:value={form.district_name}
                                    placeholder="Contoh: Lowokwaru"
                                    class="w-full h-11 px-3.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                />
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-amber-50 border border-amber-200">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <span class="text-xs font-bold text-amber-900 uppercase tracking-wider block">Titik Koordinat GPS</span>
                                    <span class="text-[11px] text-amber-700">Dapatkan titik akurat via sensor lokasi browser</span>
                                </div>
                                <button
                                    type="button"
                                    onclick={detectLocation}
                                    class="px-3 py-1.5 text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow-xs transition-colors flex items-center gap-1.5"
                                >
                                    <span>📍</span> Deteksi GPS Saya
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label for="latitude" class="block text-[11px] font-semibold text-amber-900 mb-1">Latitude</label>
                                    <input
                                        id="latitude"
                                        type="text"
                                        bind:value={form.latitude}
                                        placeholder="-7.982614"
                                        class="w-full h-10 px-3 rounded-lg border border-amber-300 bg-white text-xs focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    />
                                </div>
                                <div>
                                    <label for="longitude" class="block text-[11px] font-semibold text-amber-900 mb-1">Longitude</label>
                                    <input
                                        id="longitude"
                                        type="text"
                                        bind:value={form.longitude}
                                        placeholder="112.630789"
                                        class="w-full h-10 px-3 rounded-lg border border-amber-300 bg-white text-xs focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- STEP 4: JAM OPERASIONAL & LAYANAN -->
            {#if currentStep === 4}
                <div>
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Langkah 4: Opsi Layanan & Jam Buka Dapur</h2>
                    <p class="text-xs text-slate-500 mb-6">Atur apakah resto menerima pesanan ambil di tempat, pengantaran kurir, serta jam aktif.</p>

                    <div class="space-y-6">
                        <!-- Opsi Layanan -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="p-4 rounded-xl border-2 cursor-pointer transition-all flex items-start gap-3 {form.allow_pickup ? 'border-orange-500 bg-orange-50/50' : 'border-slate-200 bg-white'}">
                                <input
                                    type="checkbox"
                                    bind:checked={form.allow_pickup}
                                    class="mt-1 w-4 h-4 text-orange-600 rounded border-slate-300 focus:ring-orange-500"
                                />
                                <div>
                                    <span class="text-sm font-bold text-slate-900 block">Ambil di Resto (Self-Pickup)</span>
                                    <span class="text-xs text-slate-500">Pelanggan bisa memesan duluan lalu mengambil makanan sendiri di dapur (Takeaway/Bungkus).</span>
                                </div>
                            </label>

                            <label class="p-4 rounded-xl border-2 cursor-pointer transition-all flex items-start gap-3 {form.allow_delivery ? 'border-orange-500 bg-orange-50/50' : 'border-slate-200 bg-white'}">
                                <input
                                    type="checkbox"
                                    bind:checked={form.allow_delivery}
                                    class="mt-1 w-4 h-4 text-orange-600 rounded border-slate-300 focus:ring-orange-500"
                                />
                                <div>
                                    <span class="text-sm font-bold text-slate-900 block">Kurir Resto (Delivery)</span>
                                    <span class="text-xs text-slate-500">Dapur siap mengantarkan pesanan dengan kurir internal resto berbasis jarak kilometer.</span>
                                </div>
                            </label>
                        </div>

                        <!-- Ringkasan Jam Operasional -->
                        <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3">Jam Operasional Standar</h3>
                            <div class="space-y-2 text-xs">
                                {#each Object.entries(dayLabels) as [key, label]}
                                    <div class="flex items-center justify-between py-1 border-b border-slate-200 last:border-b-0">
                                        <span class="font-medium text-slate-700 w-24">{label}</span>
                                        <div class="flex items-center gap-2">
                                            <label class="inline-flex items-center gap-1 cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    bind:checked={form.operating_hours[key].active}
                                                    class="w-3.5 h-3.5 text-orange-600 rounded"
                                                />
                                                <span class="text-[11px] text-slate-600">Buka</span>
                                            </label>
                                            {#if form.operating_hours[key].active}
                                                <input
                                                    type="time"
                                                    bind:value={form.operating_hours[key].open}
                                                    class="px-2 py-0.5 border border-slate-300 rounded text-xs bg-white"
                                                />
                                                <span>s/d</span>
                                                <input
                                                    type="time"
                                                    bind:value={form.operating_hours[key].close}
                                                    class="px-2 py-0.5 border border-slate-300 rounded text-xs bg-white"
                                                />
                                            {:else}
                                                <span class="text-[11px] text-red-500 italic">Tutup</span>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Bottom Navigation Actions -->
            <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-between">
                {#if currentStep > 1}
                    <button
                        type="button"
                        onclick={prevStep}
                        class="px-5 py-2.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-colors"
                    >
                        ← Kembali
                    </button>
                {:else}
                    <div></div>
                {/if}

                {#if currentStep < 4}
                    <button
                        type="button"
                        onclick={nextStep}
                        class="px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white text-xs font-bold shadow-sm transition-all flex items-center gap-1.5"
                    >
                        Lanjut ke Langkah {currentStep + 1} →
                    </button>
                {:else}
                    <button
                        type="button"
                        onclick={submit}
                        disabled={form.processing}
                        class="px-8 py-3 rounded-xl bg-orange-600 hover:bg-orange-700 text-white text-sm font-extrabold shadow-md transition-all disabled:opacity-50 flex items-center gap-2"
                    >
                        {#if form.processing}
                            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            Mendaftarkan Resto...
                        {:else}
                            ✓ Selesaikan Pendaftaran Resto
                        {/if}
                    </button>
                {/if}
            </div>
        </div>

        <!-- Footnote: Back to customer registration -->
        <div class="text-center mt-6 text-xs text-slate-500">
            Ingin mendaftar sebagai pembeli makanan biasa?
            <Link href="/register" class="text-orange-600 font-bold hover:underline ml-1">Daftar Akun Pelanggan</Link>
        </div>
    </main>
</div>

