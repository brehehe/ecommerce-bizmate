<script lang="ts">
    import { useForm, page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Input from '@/components/ui/Input.svelte';
    import { showToast } from '@/utils/toast';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const user = $derived(page.props.auth?.user);
    const storeName = $derived(
        (page.props as any).settings?.store_name || 'Bizmate',
    );
    const fallbackEmail = $derived(
        'admin@' + storeName.toLowerCase().replace(/[^a-z0-9]/g, '') + '.id',
    );

    const profileForm = useForm({
        _method: 'put',
        name: (page.props.auth as any)?.user?.name || '',
        email: (page.props.auth as any)?.user?.email || '',
        phone_number: (page.props.auth as any)?.user?.phone_number || '',
        gender: (page.props.auth as any)?.user?.gender || '',
        birth_date: (page.props.auth as any)?.user?.birth_date || '',
        avatar: null as File | null,
        current_password: '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    let localPreviewUrl = $state<string | null>(null);
    const previewUrl = $derived(
        localPreviewUrl || (user?.avatar ? `/storage/${user.avatar}` : null),
    );
    let fileInput: HTMLInputElement;
    let showPasswordModal = $state(false);

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
        profileForm.post('/admin/profile', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                profileForm.reset('current_password');
                localPreviewUrl = null;
                showPasswordModal = false;
                showToast('Profil berhasil diperbarui!', 'success');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error');
            },
        });
    }

    function submitPassword() {
        passwordForm.put('/admin/profile/password', {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
                showToast('Kata sandi berhasil diperbarui!', 'success');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error');
            },
        });
    }
</script>

<svelte:head>
    <title>Edit Profil Saya</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-8">
        <!-- Page Header -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2"
        >
            <div>
                <h1 class="font-outfit font-black text-2xl text-slate-800">
                    Profil Pengelola
                </h1>
                <p
                    class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                >
                    Kelola informasi akun dan keamanan administratif Anda.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left Col: Photo + Role Summary -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Avatar Card -->
                <div
                    class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 flex flex-col items-center text-center space-y-5"
                >
                    <button
                        type="button"
                        onclick={() => fileInput.click()}
                        class="relative group focus:outline-none"
                    >
                        <div
                            class="w-28 h-28 rounded-full border-4 p-1 flex items-center justify-center shadow-lg overflow-hidden relative"
                            style="border-color: {secondaryColor};"
                        >
                            {#if previewUrl}
                                <img
                                    src={previewUrl}
                                    alt="Avatar"
                                    class="w-full h-full object-cover rounded-full"
                                />
                            {:else}
                                <div
                                    class="w-full h-full text-white font-black text-3xl rounded-full flex items-center justify-center uppercase"
                                    style="background: linear-gradient(135deg, {primaryColor}, {secondaryColor});"
                                >
                                    {user ? user.name.substring(0, 2) : 'AD'}
                                </div>
                            {/if}
                            <div
                                class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition"
                            >
                                <i
                                    class="ti ti-camera text-white text-2xl mb-0.5"
                                ></i>
                                <span
                                    class="text-[10px] text-white font-black tracking-wider"
                                    >UBAH FOTO</span
                                >
                            </div>
                        </div>
                    </button>
                    <input
                        type="file"
                        bind:this={fileInput}
                        accept="image/*"
                        class="hidden"
                        onchange={handleFileChange}
                    />

                    <div class="space-y-1">
                        <h2
                            class="font-outfit font-black text-slate-800 text-lg"
                        >
                            {user?.name || 'Administrator'}
                        </h2>
                        <p class="text-xs text-slate-500 font-medium">
                            {user?.email || fallbackEmail}
                        </p>
                    </div>

                    <div class="h-px w-full bg-slate-100"></div>

                    <!-- Roles -->
                    <div class="w-full space-y-2">
                        <span
                            class="text-[10px] font-black tracking-widest text-slate-400 uppercase block"
                            >Peran Akses Sistem</span
                        >
                        <div class="flex flex-wrap gap-2 justify-center">
                            {#if user?.roles && user.roles.length > 0}
                                {#each user.roles as role}
                                    <span
                                        class="px-3 py-1 text-[10px] font-black tracking-wide uppercase rounded-full"
                                        style="color: {primaryColor}; background-color: {primaryColor}15;"
                                    >
                                        {role.name}
                                    </span>
                                {/each}
                            {:else}
                                <span
                                    class="px-3 py-1 text-[10px] font-black tracking-wide uppercase rounded-full"
                                    style="color: {primaryColor}; background-color: {primaryColor}15;"
                                >
                                    Super Admin
                                </span>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Col: Forms -->
            <div class="lg:col-span-8 space-y-8">
                <!-- Form Informasi Pribadi -->
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        triggerProfileSave();
                    }}
                    class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                >
                    <div
                        class="flex items-center gap-3 border-b border-slate-100 pb-4"
                    >
                        <div
                            class="p-2.5 rounded-xl"
                            style="color: {primaryColor}; background-color: {primaryColor}1A;"
                        >
                            <i class="ti ti-user-edit text-lg"></i>
                        </div>
                        <div>
                            <h3
                                class="font-outfit font-black text-slate-800 text-base leading-none"
                            >
                                Informasi Pribadi
                            </h3>
                            <p class="text-xs text-slate-400 font-medium mt-1">
                                Perbarui nama, email, dan detail profil lengkap
                                Anda.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <Input
                            id="input-name"
                            bind:value={profileForm.name}
                            label="Nama Lengkap"
                            placeholder="Masukkan nama lengkap"
                            required={true}
                            error={profileForm.errors.name}
                        />
                        <Input
                            id="input-email"
                            bind:value={profileForm.email}
                            label="Alamat Email"
                            type="email"
                            placeholder="Masukkan alamat email"
                            required={true}
                            error={profileForm.errors.email}
                        />
                        <Input
                            id="input-phone"
                            bind:value={profileForm.phone_number}
                            label="Nomor Telepon"
                            placeholder="08xxxxxxxxx"
                            error={profileForm.errors.phone_number}
                        />
                        <div>
                            <label
                                for="input-gender"
                                class="block text-xs font-bold text-slate-600 mb-1.5"
                                >Jenis Kelamin</label
                            >
                            <select
                                id="input-gender"
                                bind:value={profileForm.gender}
                                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm
                                    .errors.gender
                                    ? 'border-rose-500'
                                    : ''}"
                            >
                                <option value="">Pilih</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                            {#if profileForm.errors.gender}
                                <p
                                    class="text-[10px] text-rose-500 font-bold mt-1"
                                >
                                    {profileForm.errors.gender}
                                </p>
                            {/if}
                        </div>
                        <div>
                            <label
                                for="input-dob"
                                class="block text-xs font-bold text-slate-600 mb-1.5"
                                >Tanggal Lahir</label
                            >
                            <input
                                id="input-dob"
                                type="date"
                                bind:value={profileForm.birth_date}
                                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm
                                    .errors.birth_date
                                    ? 'border-rose-500'
                                    : ''}"
                            />
                            {#if profileForm.errors.birth_date}
                                <p
                                    class="text-[10px] text-rose-500 font-bold mt-1"
                                >
                                    {profileForm.errors.birth_date}
                                </p>
                            {/if}
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={profileForm.processing}
                            class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70"
                            style="background-color: {primaryColor};"
                        >
                            {#if profileForm.processing}
                                <i class="ti ti-loader animate-spin text-lg"
                                ></i> Menyimpan...
                            {:else}
                                <i class="ti ti-device-floppy text-lg"></i> Simpan
                                Profil
                            {/if}
                        </button>
                    </div>
                </form>

                <!-- Form Kata Sandi -->
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        submitPassword();
                    }}
                    class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                >
                    <div
                        class="flex items-center gap-3 border-b border-slate-100 pb-4"
                    >
                        <div
                            class="p-2.5 rounded-xl"
                            style="color: {secondaryColor}; background-color: {secondaryColor}1A;"
                        >
                            <i class="ti ti-key text-lg"></i>
                        </div>
                        <div>
                            <h3
                                class="font-outfit font-black text-slate-800 text-base leading-none"
                            >
                                Ubah Kata Sandi
                            </h3>
                            <p class="text-xs text-slate-400 font-medium mt-1">
                                Masukkan kata sandi saat ini untuk dapat
                                mengubahnya.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="sm:col-span-2">
                            <Input
                                id="input-current-password"
                                bind:value={passwordForm.current_password}
                                label="Kata Sandi Saat Ini"
                                type="password"
                                placeholder="Masukkan kata sandi saat ini"
                                required={true}
                                error={passwordForm.errors.current_password}
                            />
                        </div>
                        <Input
                            id="input-password"
                            bind:value={passwordForm.password}
                            label="Kata Sandi Baru"
                            type="password"
                            placeholder="Masukkan kata sandi baru"
                            required={true}
                            error={passwordForm.errors.password}
                        />
                        <Input
                            id="input-password-confirmation"
                            bind:value={passwordForm.password_confirmation}
                            label="Konfirmasi Kata Sandi Baru"
                            type="password"
                            placeholder="Konfirmasi kata sandi baru"
                            required={true}
                        />
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={passwordForm.processing}
                            class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70"
                            style="background-color: {secondaryColor};"
                        >
                            {#if passwordForm.processing}
                                <i class="ti ti-loader animate-spin text-lg"
                                ></i> Memperbarui...
                            {:else}
                                <i class="ti ti-shield-check text-lg"></i> Perbarui
                                Kata Sandi
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</AdminLayout>

<!-- Password Verification Modal -->
{#if showPasswordModal}
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <button
            type="button"
            class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm w-full h-full cursor-default border-none p-0 focus:outline-none"
            onclick={() => (showPasswordModal = false)}
            aria-label="Tutup"
        ></button>
        <div
            class="bg-white rounded-3xl shadow-xl w-full max-w-sm relative z-10 overflow-hidden"
        >
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="p-2 rounded-xl"
                        style="color: {primaryColor}; background-color: {primaryColor}1A;"
                    >
                        <i class="ti ti-lock text-lg"></i>
                    </div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Verifikasi Keamanan
                    </h3>
                </div>
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
                <p class="text-sm text-slate-600 mb-5">
                    Masukkan kata sandi akun Anda untuk mengonfirmasi perubahan
                    profil.
                </p>
                <Input
                    id="modal-current-password"
                    bind:value={profileForm.current_password}
                    label="Kata Sandi Akun"
                    type="password"
                    placeholder="Kata sandi Anda"
                    required={true}
                    error={profileForm.errors.current_password}
                />
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
                        style="background-color: {primaryColor};"
                    >
                        {#if profileForm.processing}
                            <i class="ti ti-loader animate-spin text-lg"></i> Memproses...
                        {:else}
                            Lanjutkan & Simpan
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}
