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
    const sellerForm = useForm({
        store_name: (page.props.auth as any)?.user?.store_name || ((page.props.auth as any)?.user?.name ? (page.props.auth as any)?.user?.name + ' Store' : ''),
        store_description: '',
    });

    function submitBecomeSeller() {
        sellerForm.post('/profile/become-seller', {
            onSuccess: () => {
                showSellerModal = false;
                showToast('Toko berhasil diaktifkan! Mengalihkan ke Dashboard...', 'success', 'top');
            },
            onError: (errs) => {
                const msg = Object.values(errs)[0] || 'Gagal mengaktifkan toko';
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
                showToast('Profil Anda berhasil diperbarui!', 'success', 'top');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error', 'top');
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

    let showPasswordModal = $state(false);
    let sendingReset = $state(false);
    let activeTab = $state<'profile' | 'password'>('profile');

    $effect(() => {
        if (typeof window !== 'undefined') {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'password') {
                activeTab = 'password';
            }
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

<AccountLayout activeMenu={activeTab === 'password' ? 'profile' : 'profile'}>
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
            <!-- PROFIL SAYA CARD -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 md:p-8">
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
                            <div class="sm:col-span-3 flex items-center gap-3">
                                <span class="text-xs font-bold text-slate-700">
                                    {maskedEmail || user?.email}
                                </span>
                                <button
                                    type="button"
                                    onclick={() => (activeTab = 'profile')}
                                    class="text-xs font-bold hover:underline"
                                    style="color: {primary};"
                                >
                                    Ubah
                                </button>
                            </div>
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
                    <div class="md:col-span-1 border-t md:border-t-0 md:border-l border-slate-100 pt-6 md:pt-0 md:pl-8 flex flex-col items-center justify-center text-center space-y-4">
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
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 md:p-8">
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
