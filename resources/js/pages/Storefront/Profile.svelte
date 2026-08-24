<script lang="ts">
    import { useForm, page, Link, router } from '@inertiajs/svelte';
    import AccountLayout from '@/components/layouts/AccountLayout.svelte';
    import { showToast } from '@/utils/toast';

    const primary = $derived(page.props.theme?.primary_color || '#fa7315');
    const secondary = $derived(page.props.theme?.secondary_color || '#0c4cb4');
    const user = $derived(page.props.auth?.user);
    const membershipInfo = $derived((page.props as any).membershipInfo ?? null);
    const isSellerEnabled = $derived(((page.props as any).app_config?.is_seller_enabled ?? (page.props as any).settings?.is_seller_enabled) ?? false);

    function fmtCurrency(n: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);
    }

    const profileForm = useForm({
        _method: 'put',
        name: (page.props.auth as any)?.user?.name || '',
        email: (page.props.auth as any)?.user?.email || '',
        phone_number: (page.props.auth as any)?.user?.phone_number || '',
        gender: (page.props.auth as any)?.user?.gender || 'Laki-laki',
        birth_date: (page.props.auth as any)?.user?.birth_date || '',
        avatar: null as File | null,
        current_password: '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    let showSellerModal = $state(false);

    // Regional cascading state for Seller Onboarding
    let provinces = $state<{ id: string; name: string }[]>([]);
    let regencies = $state<{ id: string; name: string }[]>([]);
    let districts = $state<{ id: string; name: string }[]>([]);
    let villages = $state<{ id: string; name: string }[]>([]);
    let loadingRegional = $state(false);

    const sellerForm = useForm({
        store_name: (page.props.auth as any)?.user?.store_name || ((page.props.auth as any)?.user?.name ? (page.props.auth as any)?.user?.name + ' Store' : ''),
        store_description: '',
        phone_number: (page.props.auth as any)?.user?.phone_number || '',
        receiver_name: (page.props.auth as any)?.user?.name || '',
        full_address: '',
        note: '',
        province_id: '',
        province_name: '',
        regency_id: '',
        regency_name: '',
        district_id: '',
        district_name: '',
        village_id: '',
        village_name: '',
        postal_code: '',
        bank_name: 'BCA',
        account_number: '',
        account_name: (page.props.auth as any)?.user?.name || '',
    });

    async function loadProvinces() {
        if (provinces.length > 0) return;
        loadingRegional = true;
        try {
            const res = await fetch('https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json');
            provinces = await res.json();
        } catch (e) {
            console.error('Failed to load provinces', e);
        } finally {
            loadingRegional = false;
        }
    }

    async function handleProvinceChange(e: Event) {
        const select = e.target as HTMLSelectElement;
        const selectedId = select.value;
        const selectedObj = provinces.find(p => p.id === selectedId);
        sellerForm.province_id = selectedId;
        sellerForm.province_name = selectedObj ? selectedObj.name : '';

        regencies = [];
        districts = [];
        villages = [];
        sellerForm.regency_id = '';
        sellerForm.regency_name = '';
        sellerForm.district_id = '';
        sellerForm.district_name = '';
        sellerForm.village_id = '';
        sellerForm.village_name = '';

        if (selectedId) {
            try {
                const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${selectedId}.json`);
                regencies = await res.json();
            } catch (err) {
                console.error(err);
            }
        }
    }

    async function handleRegencyChange(e: Event) {
        const select = e.target as HTMLSelectElement;
        const selectedId = select.value;
        const selectedObj = regencies.find(r => r.id === selectedId);
        sellerForm.regency_id = selectedId;
        sellerForm.regency_name = selectedObj ? selectedObj.name : '';

        districts = [];
        villages = [];
        sellerForm.district_id = '';
        sellerForm.district_name = '';
        sellerForm.village_id = '';
        sellerForm.village_name = '';

        if (selectedId) {
            try {
                const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/districts/${selectedId}.json`);
                districts = await res.json();
            } catch (err) {
                console.error(err);
            }
        }
    }

    async function handleDistrictChange(e: Event) {
        const select = e.target as HTMLSelectElement;
        const selectedId = select.value;
        const selectedObj = districts.find(d => d.id === selectedId);
        sellerForm.district_id = selectedId;
        sellerForm.district_name = selectedObj ? selectedObj.name : '';

        villages = [];
        sellerForm.village_id = '';
        sellerForm.village_name = '';

        if (selectedId) {
            try {
                const res = await fetch(`https://www.emsifa.com/api-wilayah-indonesia/api/villages/${selectedId}.json`);
                villages = await res.json();
            } catch (err) {
                console.error(err);
            }
        }
    }

    function handleVillageChange(e: Event) {
        const select = e.target as HTMLSelectElement;
        const selectedId = select.value;
        const selectedObj = villages.find(v => v.id === selectedId);
        sellerForm.village_id = selectedId;
        sellerForm.village_name = selectedObj ? selectedObj.name : '';
    }

    function openSellerOnboarding() {
        showSellerModal = true;
        loadProvinces();
    }

    function submitBecomeSeller() {
        sellerForm.post('/profile/become-seller', {
            onSuccess: () => {
                showSellerModal = false;
                showToast('Selamat! Toko Anda berhasil diaktifkan.', 'success', 'top');
            },
            onError: (errs) => {
                const msg = Object.values(errs)[0] || 'Gagal mengaktifkan toko. Mohon periksa kembali isian form.';
                showToast(String(msg), 'error', 'top');
            }
        });
    }

    let localPreviewUrl = $state<string | null>(null);
    const previewUrl = $derived(
        localPreviewUrl || (user?.avatar ? `/storage/${user.avatar}` : null),
    );
    let fileInput: HTMLInputElement;

    function handleFileChange(event: Event) {
        const input = event.target as HTMLInputElement;
        if (input.files && input.files[0]) {
            const file = input.files[0];
            profileForm.avatar = file;
            const reader = new FileReader();
            reader.onload = (e) => {
                localPreviewUrl = e.target?.result as string;
            };
            reader.readAsDataURL(file);
        }
    }

    function triggerProfileSave() {
        if (!profileForm.current_password) {
            showPasswordModal = true;
        } else {
            submitProfile();
        }
    }

    function submitProfile() {
        profileForm.post('/profile', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                profileForm.reset('current_password');
                localPreviewUrl = null;
                showPasswordModal = false;
                isEditingEmail = false;
                showToast('Profil Anda berhasil diperbarui!', 'success', 'top');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error', 'top');
                if (errors.email) {
                    isEditingEmail = true;
                }
            },
        });
    }

    function submitPassword() {
        passwordForm.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
                showToast('Kata sandi berhasil diperbarui!', 'success', 'top');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error', 'top');
            },
        });
    }

    let isEditingEmail = $state(false);
    let showPasswordModal = $state(false);
    let sendingReset = $state(false);
    let activeTab = $state<'profile' | 'password'>('profile');

    $effect(() => {
        const urlStr = page.url || (typeof window !== 'undefined' ? window.location.search : '');
        if (urlStr.includes('tab=password')) {
            activeTab = 'password';
        } else {
            activeTab = 'profile';
        }

        if (urlStr.includes('open_seller=1') || urlStr.includes('buka_toko=1')) {
            openSellerOnboarding();
        }
    });

    function sendResetLink() {
        const email = user?.email;
        if (!email) return;

        sendingReset = true;
        router.post(
            '/forgot-password',
            { email: email },
            {
                preserveScroll: true,
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    showToast(firstError as string, 'error', 'top');
                },
                onFinish: () => {
                    sendingReset = false;
                },
            },
        );
    }

    const maskedEmail = $derived.by(() => {
        if (!user?.email) return '';
        const parts = user.email.split('@');
        if (parts.length < 2) return user.email;
        const name = parts[0];
        const maskedName = name.length > 2 ? name.substring(0, 2) + '*'.repeat(Math.max(name.length - 2, 4)) : name + '****';
        return `${maskedName}@${parts[1]}`;
    });
</script>

<svelte:head>
    <title>Profil Saya</title>
</svelte:head>

<AccountLayout activeMenu={activeTab}>
    <!-- Hidden File Input for Avatar Upload -->
    <input
        type="file"
        bind:this={fileInput}
        accept="image/*"
        class="hidden"
        onchange={handleFileChange}
    />

    <div class="space-y-6">
        {#if activeTab === 'profile'}
            <!-- BANNER BUKA TOKO (Jika user belum jadi seller dan seller mode aktif) -->
            {#if isSellerEnabled && !user?.is_seller}
                <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-orange-50 via-amber-50 to-orange-50/50 border border-orange-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-2xs">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-500 text-white flex items-center justify-center shrink-0 shadow-md shadow-orange-500/20">
                            <i class="ti ti-building-store text-xl sm:text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="font-outfit font-black text-sm sm:text-base text-slate-800">
                                Mulai Jual Barang & Buka Toko
                            </h3>
                            <p class="text-xs text-slate-600 font-medium mt-0.5">
                                Lengkapi identitas toko, nomor kontak pengiriman, dan alamat gudang untuk mulai berjualan.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onclick={openSellerOnboarding}
                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl font-bold text-xs text-white shadow-md hover:opacity-90 active:scale-95 transition flex items-center justify-center gap-2 shrink-0 cursor-pointer"
                        style="background-color: {primary};"
                    >
                        <i class="ti ti-plus text-sm"></i>
                        <span>Buka Toko Sekarang</span>
                    </button>
                </div>
            {/if}

            <!-- PROFIL SAYA CARD -->
            <div class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-200/80 shadow-2xs p-4 sm:p-6 md:p-8">
                <!-- Card Header -->
                <div class="border-b border-slate-100 pb-4 mb-6">
                    <h1 class="text-lg font-bold text-slate-800 font-outfit">
                        Profil Saya
                    </h1>
                    <p class="text-xs text-slate-400 font-medium mt-0.5">
                        Kelola informasi profil Anda untuk mengontrol, melindungi dan mengamankan akun
                    </p>
                </div>

                <!-- Two Column Layout: Form Inputs (Left) & Avatar (Right) -->
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        triggerProfileSave();
                    }}
                    class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start"
                >
                    <!-- Left Column: Form Fields (2/3 width) -->
                    <div class="md:col-span-2 space-y-5">
                        <!-- Username -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4">
                            <label for="username-view" class="text-xs font-semibold text-slate-500 sm:text-right">
                                Username
                            </label>
                            <div class="sm:col-span-3">
                                <span class="text-sm font-bold text-slate-800">
                                    {user?.name ? user.name.toLowerCase().replace(/\s+/g, '') : 'user'}
                                </span>
                            </div>
                        </div>

                        <!-- Nama Lengkap -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4">
                            <label for="name" class="text-xs font-semibold text-slate-500 sm:text-right">
                                Nama Lengkap
                            </label>
                            <div class="sm:col-span-3">
                                <input
                                    id="name"
                                    type="text"
                                    bind:value={profileForm.name}
                                    required
                                    placeholder="Nama Lengkap Anda"
                                    class="w-full px-3.5 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 transition text-slate-800 font-medium {profileForm.errors.name ? 'border-rose-500' : ''}"
                                />
                                {#if profileForm.errors.name}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {profileForm.errors.name}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4">
                            <label for="email" class="text-xs font-semibold text-slate-500 sm:text-right">
                                Email
                            </label>
                            {#if isEditingEmail}
                                <div class="sm:col-span-3 space-y-1">
                                    <div class="flex items-center gap-2">
                                        <input
                                            id="email"
                                            type="email"
                                            bind:value={profileForm.email}
                                            required
                                            placeholder="email@domain.com"
                                            class="w-full px-3.5 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 transition text-slate-800 font-medium {profileForm.errors.email ? 'border-rose-500' : ''}"
                                        />
                                        <button
                                            type="button"
                                            onclick={() => {
                                                isEditingEmail = false;
                                                profileForm.email = user?.email || '';
                                                delete profileForm.errors.email;
                                            }}
                                            class="text-xs font-bold text-slate-400 hover:text-slate-600 px-2.5 py-2 rounded-xl hover:bg-slate-100 border border-slate-200 transition shrink-0"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                    {#if profileForm.errors.email}
                                        <p class="text-[10px] text-rose-500 font-bold mt-1">
                                            {profileForm.errors.email}
                                        </p>
                                    {/if}
                                </div>
                            {:else}
                                <div class="sm:col-span-3 flex items-center gap-3">
                                    <span class="text-xs font-bold text-slate-700">
                                        {maskedEmail || user?.email}
                                    </span>
                                    <button
                                        type="button"
                                        onclick={() => {
                                            isEditingEmail = true;
                                            profileForm.email = user?.email || '';
                                        }}
                                        class="text-xs font-bold hover:underline"
                                        style="color: {primary};"
                                    >
                                        Ubah
                                    </button>
                                </div>
                            {/if}
                        </div>

                        <!-- Nomor Telepon -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4">
                            <label for="phone_number" class="text-xs font-semibold text-slate-500 sm:text-right">
                                Nomor Telepon
                            </label>
                            <div class="sm:col-span-3">
                                <input
                                    id="phone_number"
                                    type="text"
                                    bind:value={profileForm.phone_number}
                                    placeholder="08xxxxxxxxx"
                                    class="w-full px-3.5 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 transition text-slate-800 font-medium {profileForm.errors.phone_number ? 'border-rose-500' : ''}"
                                />
                                {#if profileForm.errors.phone_number}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {profileForm.errors.phone_number}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <!-- Nama Toko (if seller) -->
                        {#if user?.is_seller}
                            <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4">
                                <label for="store_name_view" class="text-xs font-semibold text-slate-500 sm:text-right">
                                    Nama Toko
                                </label>
                                <div class="sm:col-span-3 flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-800 bg-amber-50 text-amber-800 px-2.5 py-1 rounded-lg border border-amber-200">
                                        <i class="ti ti-building-store text-xs mr-1"></i>
                                        {user.store_name || 'Toko Saya'}
                                    </span>
                                </div>
                            </div>
                        {/if}

                        <!-- Jenis Kelamin -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4">
                            <span class="text-xs font-semibold text-slate-500 sm:text-right">
                                Jenis Kelamin
                            </span>
                            <div class="sm:col-span-3 flex items-center gap-6">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                    <input
                                        type="radio"
                                        name="gender"
                                        value="Laki-laki"
                                        bind:group={profileForm.gender}
                                        class="w-4 h-4 text-amber-500 focus:ring-amber-400"
                                    />
                                    Laki-laki
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                    <input
                                        type="radio"
                                        name="gender"
                                        value="Perempuan"
                                        bind:group={profileForm.gender}
                                        class="w-4 h-4 text-amber-500 focus:ring-amber-400"
                                    />
                                    Perempuan
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                                    <input
                                        type="radio"
                                        name="gender"
                                        value="Lainnya"
                                        bind:group={profileForm.gender}
                                        class="w-4 h-4 text-amber-500 focus:ring-amber-400"
                                    />
                                    Lainnya
                                </label>
                            </div>
                        </div>

                        <!-- Tanggal Lahir -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4">
                            <label for="birth_date" class="text-xs font-semibold text-slate-500 sm:text-right">
                                Tanggal Lahir
                            </label>
                            <div class="sm:col-span-3">
                                <input
                                    id="birth_date"
                                    type="date"
                                    bind:value={profileForm.birth_date}
                                    class="w-full sm:w-auto px-3.5 py-2 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 transition text-slate-800 font-medium"
                                />
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="grid grid-cols-1 sm:grid-cols-4 items-center gap-2 sm:gap-4 pt-4">
                            <div></div>
                            <div class="sm:col-span-3">
                                <button
                                    type="submit"
                                    disabled={profileForm.processing}
                                    class="px-8 py-2.5 rounded-xl font-bold text-xs text-white shadow-md hover:opacity-90 active:scale-95 transition flex items-center justify-center gap-2 disabled:opacity-50"
                                    style="background-color: {primary};"
                                >
                                    {#if profileForm.processing}
                                        <i class="ti ti-loader animate-spin text-sm"></i>
                                        Menyimpan...
                                    {:else}
                                        Simpan
                                    {/if}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Avatar Upload Section (1/3 width with left border line) -->
                    <div class="md:col-span-1 border-b pb-6 md:pb-0 md:border-b-0 md:border-l border-slate-100 pt-2 md:pt-0 md:pl-8 flex flex-col items-center justify-center text-center space-y-4 order-first md:order-last">
                        <button
                            type="button"
                            onclick={() => fileInput.click()}
                            class="relative group cursor-pointer"
                        >
                            <div class="w-32 h-32 rounded-full border-4 border-slate-100 shadow-md overflow-hidden relative flex items-center justify-center bg-slate-100">
                                {#if previewUrl}
                                    <img src={previewUrl} alt="Avatar Preview" class="w-full h-full object-cover rounded-full" />
                                {:else}
                                    <div
                                        class="w-full h-full text-white font-black text-3xl flex items-center justify-center uppercase"
                                        style="background: linear-gradient(135deg, {primary}, {secondary});"
                                    >
                                        {user ? user.name.substring(0, 2) : 'CS'}
                                    </div>
                                {/if}
                                <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition">
                                    <i class="ti ti-camera text-white text-2xl mb-1"></i>
                                    <span class="text-[10px] text-white font-bold tracking-wider">UBAH FOTO</span>
                                </div>
                            </div>
                        </button>

                        <button
                            type="button"
                            onclick={() => fileInput.click()}
                            class="px-4 py-2 border border-slate-200 hover:border-slate-300 rounded-xl text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-2xs"
                        >
                            Pilih Gambar
                        </button>

                        <div class="text-[11px] text-slate-400 font-medium space-y-1 max-w-[200px]">
                            <p>Ukuran gambar: maks. 2 MB</p>
                            <p>Format gambar: .JPEG, .PNG</p>
                        </div>
                    </div>
                </form>
            </div>
        {:else if activeTab === 'password'}
            <!-- KEAMANAN / UBAH PASSWORD CARD -->
            <div class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-200/80 shadow-2xs p-4 sm:p-6 md:p-8">
                <div class="border-b border-slate-100 pb-4 mb-6">
                    <h1 class="text-lg font-bold text-slate-800 font-outfit">
                        Keamanan & Kata Sandi
                    </h1>
                    <p class="text-xs text-slate-400 font-medium mt-0.5">
                        Untuk keamanan akun Anda, mohon tidak memberikan kata sandi kepada siapapun.
                    </p>
                </div>

                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        submitPassword();
                    }}
                    class="max-w-lg space-y-5"
                >
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label for="current_password" class="block text-xs font-semibold text-slate-600">
                                Kata Sandi Saat Ini
                            </label>
                            <button
                                type="button"
                                onclick={sendResetLink}
                                disabled={sendingReset}
                                class="text-xs font-bold hover:underline"
                                style="color: {primary};"
                            >
                                {sendingReset ? 'Mengirim...' : 'Lupa Kata Sandi?'}
                            </button>
                        </div>
                        <input
                            id="current_password"
                            type="password"
                            bind:value={passwordForm.current_password}
                            required
                            placeholder="Masukkan kata sandi saat ini"
                            class="w-full px-3.5 py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 transition text-slate-800 {passwordForm.errors.current_password ? 'border-rose-500' : ''}"
                        />
                        {#if passwordForm.errors.current_password}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">
                                {passwordForm.errors.current_password}
                            </p>
                        {/if}
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-600 mb-1.5">
                            Kata Sandi Baru
                        </label>
                        <input
                            id="password"
                            type="password"
                            bind:value={passwordForm.password}
                            required
                            placeholder="Masukkan kata sandi baru"
                            class="w-full px-3.5 py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 transition text-slate-800 {passwordForm.errors.password ? 'border-rose-500' : ''}"
                        />
                        {#if passwordForm.errors.password}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">
                                {passwordForm.errors.password}
                            </p>
                        {/if}
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold text-slate-600 mb-1.5">
                            Konfirmasi Kata Sandi Baru
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            bind:value={passwordForm.password_confirmation}
                            required
                            placeholder="Ulangi kata sandi baru"
                            class="w-full px-3.5 py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 transition text-slate-800"
                        />
                    </div>

                    <div class="pt-4">
                        <button
                            type="submit"
                            disabled={passwordForm.processing}
                            class="px-8 py-2.5 rounded-xl font-bold text-xs text-white shadow-md hover:opacity-90 active:scale-95 transition flex items-center justify-center gap-2 disabled:opacity-50"
                            style="background-color: {primary};"
                        >
                            {#if passwordForm.processing}
                                <i class="ti ti-loader animate-spin text-sm"></i>
                                Memperbarui...
                            {:else}
                                Perbarui Kata Sandi
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        {/if}
    </div>
</AccountLayout>

<!-- Modal Password Verification for Profile Saving -->
{#if showPasswordModal}
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <button
            type="button"
            class="absolute inset-0 bg-slate-900/40 backdrop-blur-xs w-full h-full cursor-default border-none p-0 focus:outline-none"
            onclick={() => (showPasswordModal = false)}
            aria-label="Tutup"
        ></button>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm relative z-10 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    Verifikasi Keamanan
                </h3>
                <button
                    type="button"
                    onclick={() => (showPasswordModal = false)}
                    class="text-slate-400 hover:text-slate-600 transition"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    submitProfile();
                }}
                class="p-6"
            >
                <p class="text-sm text-slate-600 mb-4">
                    Masukkan kata sandi akun Anda untuk mengonfirmasi perubahan profil.
                </p>

                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label for="modal_current_password" class="block text-xs font-bold text-slate-600">
                                Kata Sandi Akun
                            </label>
                            <button
                                type="button"
                                onclick={sendResetLink}
                                disabled={sendingReset}
                                class="text-[10px] font-black uppercase tracking-wider hover:underline"
                                style="color: {primary};"
                            >
                                {sendingReset ? 'Mengirim...' : 'Lupa Kata Sandi?'}
                            </button>
                        </div>
                        <input
                            id="modal_current_password"
                            type="password"
                            bind:value={profileForm.current_password}
                            required
                            placeholder="Kata sandi akun Anda"
                            class="w-full px-4 py-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.current_password ? 'border-rose-500' : ''}"
                        />
                        {#if profileForm.errors.current_password}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">
                                {profileForm.errors.current_password}
                            </p>
                        {/if}
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onclick={() => (showPasswordModal = false)}
                        class="px-4 py-2 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={profileForm.processing}
                        class="px-5 py-2 text-sm font-bold text-white rounded-xl shadow-md flex items-center gap-2 disabled:opacity-70 transition hover:shadow-lg"
                        style="background-color: {primary};"
                    >
                        {#if profileForm.processing}
                            <i class="ti ti-loader animate-spin text-lg"></i> Memproses...
                        {:else}
                            Lanjutkan
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- MODAL SELLER ONBOARDING (LENGKAPI IDENTITAS TOKO, ALAMAT, KONTAK & REKENING) -->
{#if showSellerModal}
    <div class="fixed inset-0 z-[999999] flex items-center justify-center p-2.5 sm:p-4 overflow-y-auto">
        <button
            type="button"
            class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs w-full h-full cursor-default border-none p-0 focus:outline-none"
            onclick={() => (showSellerModal = false)}
            aria-label="Tutup"
        ></button>

        <div class="bg-white rounded-2xl sm:rounded-3xl shadow-2xl w-full max-w-2xl relative z-10 max-h-[calc(100dvh-1.5rem)] sm:max-h-[88vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200 my-auto border border-slate-100">
            <!-- Modal Header -->
            <div class="px-4 py-3 sm:px-6 sm:py-4 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/80">
                <div class="flex items-center gap-2.5 sm:gap-3">
                    <div
                        class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center text-white shrink-0 shadow-sm"
                        style="background: linear-gradient(135deg, {primary}, {secondary});"
                    >
                        <i class="ti ti-building-store text-lg sm:text-xl"></i>
                    </div>
                    <div>
                        <h2 class="font-outfit font-black text-sm sm:text-lg text-slate-800 leading-tight">
                            Buka Toko & Mulai Berjualan
                        </h2>
                        <p class="text-[11px] sm:text-xs text-slate-500 font-medium mt-0.5 line-clamp-1">
                            Lengkapi identitas toko, nomor kontak, dan alamat pengiriman Anda
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={() => (showSellerModal = false)}
                    class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition cursor-pointer shrink-0 ml-2"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Modal Form with Scrollable Body & Sticky Footer -->
            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    submitBecomeSeller();
                }}
                class="flex flex-col flex-1 overflow-hidden min-h-0"
            >
                <!-- Modal Body (Scrollable) -->
                <div class="p-3.5 sm:p-6 overflow-y-auto space-y-4 sm:space-y-5 flex-1 overscroll-contain">
                    <!-- Section 1: Informasi Toko & Penjual -->
                    <div class="space-y-3 bg-slate-50/80 p-3.5 sm:p-5 rounded-xl sm:rounded-2xl border border-slate-100">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-200/60">
                            <i class="ti ti-id text-sm sm:text-base text-orange-600"></i>
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                1. Informasi Toko & Penjual
                            </h4>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label for="seller_store_name" class="block text-xs font-bold text-slate-700 mb-1">
                                    Nama Toko <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="seller_store_name"
                                    type="text"
                                    bind:value={sellerForm.store_name}
                                    required
                                    placeholder="Contoh: Toko Raket Padel Maju, Bintang Sport"
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition {sellerForm.errors.store_name ? 'border-rose-500' : ''}"
                                />
                                {#if sellerForm.errors.store_name}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {sellerForm.errors.store_name}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label for="seller_phone_number" class="block text-xs font-bold text-slate-700 mb-1">
                                    Nomor HP / WhatsApp Aktif <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="seller_phone_number"
                                    type="text"
                                    bind:value={sellerForm.phone_number}
                                    required
                                    placeholder="08xxxxxxxxxx"
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition {sellerForm.errors.phone_number ? 'border-rose-500' : ''}"
                                />
                                {#if sellerForm.errors.phone_number}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {sellerForm.errors.phone_number}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label for="seller_receiver_name" class="block text-xs font-bold text-slate-700 mb-1">
                                    Nama Penanggung Jawab / PIC
                                </label>
                                <input
                                    id="seller_receiver_name"
                                    type="text"
                                    bind:value={sellerForm.receiver_name}
                                    placeholder="Nama PIC Toko"
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition"
                                />
                            </div>

                            <div class="sm:col-span-2">
                                <label for="seller_store_description" class="block text-xs font-bold text-slate-700 mb-1">
                                    Deskripsi Singkat Toko
                                </label>
                                <textarea
                                    id="seller_store_description"
                                    bind:value={sellerForm.store_description}
                                    rows="2"
                                    placeholder="Jelaskan produk yang Anda jual atau keunggulan toko Anda..."
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition resize-none"
                                ></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Alamat Toko / Pengiriman (Pickup Address) -->
                    <div class="space-y-3 bg-slate-50/80 p-3.5 sm:p-5 rounded-xl sm:rounded-2xl border border-slate-100">
                        <div class="flex items-center gap-2 pb-2 border-b border-slate-200/60">
                            <i class="ti ti-map-pin text-sm sm:text-base text-blue-600"></i>
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                2. Alamat Toko / Lokasi Pengambilan Kurir
                            </h4>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2">
                                <label for="seller_full_address" class="block text-xs font-bold text-slate-700 mb-1">
                                    Alamat Lengkap Toko / Gudang <span class="text-rose-500">*</span>
                                </label>
                                <textarea
                                    id="seller_full_address"
                                    bind:value={sellerForm.full_address}
                                    required
                                    rows="2"
                                    placeholder="Nama jalan, nomor bangunan, RT/RW, kelurahan..."
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition resize-none {sellerForm.errors.full_address ? 'border-rose-500' : ''}"
                                ></textarea>
                                {#if sellerForm.errors.full_address}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {sellerForm.errors.full_address}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label for="seller_province" class="block text-xs font-bold text-slate-700 mb-1">
                                    Provinsi <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="seller_province"
                                    required
                                    onchange={handleProvinceChange}
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition"
                                >
                                    <option value="">-- Pilih Provinsi --</option>
                                    {#each provinces as p}
                                        <option value={p.id} selected={sellerForm.province_id === p.id}>{p.name}</option>
                                    {/each}
                                </select>
                                {#if sellerForm.errors.province_name}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {sellerForm.errors.province_name}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label for="seller_regency" class="block text-xs font-bold text-slate-700 mb-1">
                                    Kota / Kabupaten <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="seller_regency"
                                    required
                                    disabled={regencies.length === 0}
                                    onchange={handleRegencyChange}
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition disabled:bg-slate-100 disabled:text-slate-400"
                                >
                                    <option value="">-- Pilih Kota / Kabupaten --</option>
                                    {#each regencies as r}
                                        <option value={r.id} selected={sellerForm.regency_id === r.id}>{r.name}</option>
                                    {/each}
                                </select>
                                {#if sellerForm.errors.regency_name}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {sellerForm.errors.regency_name}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label for="seller_district" class="block text-xs font-bold text-slate-700 mb-1">
                                    Kecamatan <span class="text-rose-500">*</span>
                                </label>
                                <select
                                    id="seller_district"
                                    required
                                    disabled={districts.length === 0}
                                    onchange={handleDistrictChange}
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition disabled:bg-slate-100 disabled:text-slate-400"
                                >
                                    <option value="">-- Pilih Kecamatan --</option>
                                    {#each districts as d}
                                        <option value={d.id} selected={sellerForm.district_id === d.id}>{d.name}</option>
                                    {/each}
                                </select>
                                {#if sellerForm.errors.district_name}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {sellerForm.errors.district_name}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label for="seller_village" class="block text-xs font-bold text-slate-700 mb-1">
                                    Kelurahan / Desa
                                </label>
                                <select
                                    id="seller_village"
                                    disabled={villages.length === 0}
                                    onchange={handleVillageChange}
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition disabled:bg-slate-100 disabled:text-slate-400"
                                >
                                    <option value="">-- Pilih Kelurahan / Desa --</option>
                                    {#each villages as v}
                                        <option value={v.id} selected={sellerForm.village_id === v.id}>{v.name}</option>
                                    {/each}
                                </select>
                            </div>

                            <div>
                                <label for="seller_postal_code" class="block text-xs font-bold text-slate-700 mb-1">
                                    Kode Pos <span class="text-rose-500">*</span>
                                </label>
                                <input
                                    id="seller_postal_code"
                                    type="text"
                                    bind:value={sellerForm.postal_code}
                                    required
                                    placeholder="Contoh: 60241"
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition {sellerForm.errors.postal_code ? 'border-rose-500' : ''}"
                                />
                                {#if sellerForm.errors.postal_code}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">
                                        {sellerForm.errors.postal_code}
                                    </p>
                                {/if}
                            </div>

                            <div>
                                <label for="seller_note" class="block text-xs font-bold text-slate-700 mb-1">
                                    Patokan / Catatan Lokasi
                                </label>
                                <input
                                    id="seller_note"
                                    type="text"
                                    bind:value={sellerForm.note}
                                    placeholder="Contoh: Ruko seberang minimarket"
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Rekening Bank Penarikan Dana (Opsional) -->
                    <div class="space-y-3 bg-slate-50/80 p-3.5 sm:p-5 rounded-xl sm:rounded-2xl border border-slate-100">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200/60">
                            <div class="flex items-center gap-2">
                                <i class="ti ti-credit-card text-sm sm:text-base text-emerald-600"></i>
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                    3. Rekening Penarikan Dana Penjualan
                                </h4>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200/70 text-slate-600">
                                Opsional
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label for="seller_bank_name" class="block text-xs font-bold text-slate-700 mb-1">
                                    Nama Bank
                                </label>
                                <select
                                    id="seller_bank_name"
                                    bind:value={sellerForm.bank_name}
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition"
                                >
                                    <option value="BCA">BCA (Bank Central Asia)</option>
                                    <option value="Mandiri">Bank Mandiri</option>
                                    <option value="BNI">BNI (Bank Negara Indonesia)</option>
                                    <option value="BRI">BRI (Bank Rakyat Indonesia)</option>
                                    <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                                    <option value="CIMB Niaga">CIMB Niaga</option>
                                    <option value="Bank Jago">Bank Jago</option>
                                    <option value="SeaBank">SeaBank</option>
                                    <option value="BCA Syariah">BCA Syariah</option>
                                    <option value="Permata">Bank Permata</option>
                                    <option value="Danamon">Bank Danamon</option>
                                    <option value="Lainnya">Bank Lainnya</option>
                                </select>
                            </div>

                            <div>
                                <label for="seller_account_number" class="block text-xs font-bold text-slate-700 mb-1">
                                    Nomor Rekening
                                </label>
                                <input
                                    id="seller_account_number"
                                    type="text"
                                    bind:value={sellerForm.account_number}
                                    placeholder="Nomor rekening bank"
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition"
                                />
                            </div>

                            <div>
                                <label for="seller_account_name" class="block text-xs font-bold text-slate-700 mb-1">
                                    Nama Pemilik Rekening
                                </label>
                                <input
                                    id="seller_account_name"
                                    type="text"
                                    bind:value={sellerForm.account_name}
                                    placeholder="Nama sesuai buku tabungan"
                                    class="w-full px-3 py-2 sm:px-3.5 sm:py-2.5 text-xs bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-medium text-slate-800 transition"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sticky Footer Actions (Always visible on mobile above bottom navigation) -->
                <div class="px-4 py-3 sm:px-6 sm:py-4 border-t border-slate-100 bg-white flex items-center justify-end gap-2.5 shrink-0 shadow-[0_-4px_12px_rgba(0,0,0,0.03)] z-10">
                    <button
                        type="button"
                        onclick={() => (showSellerModal = false)}
                        class="w-1/3 sm:w-auto px-4 py-2.5 text-xs font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer text-center"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={sellerForm.processing}
                        class="w-2/3 sm:w-auto px-6 py-2.5 text-xs font-bold text-white rounded-xl shadow-md flex items-center justify-center gap-2 disabled:opacity-50 transition hover:shadow-lg cursor-pointer"
                        style="background-color: {primary};"
                    >
                        {#if sellerForm.processing}
                            <i class="ti ti-loader animate-spin text-sm"></i>
                            <span>Memproses...</span>
                        {:else}
                            <i class="ti ti-check text-sm"></i>
                            <span>Aktifkan Toko Saya</span>
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

