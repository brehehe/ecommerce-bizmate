<script lang="ts">
    import { useForm, page } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { showToast } from '@/utils/toast';
    import Input from '@/components/ui/Input.svelte';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const user = $derived(page.props.auth?.user);

    const form = useForm({
        // svelte-ignore state_referenced_locally
        name: user?.name || '',
        // svelte-ignore state_referenced_locally
        email: user?.email || '',
        password: '',
        password_confirmation: '',
    });

    function submit() {
        form.put('/admin/profile', {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Profil Anda berhasil diperbarui!', 'success');
                form.reset('password', 'password_confirmation');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error');
            }
        });
    }
</script>

<svelte:head>
    <title>Edit Profil Saya</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-8">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div>
                <h1 class="font-outfit font-black text-2xl text-slate-800">
                    Profil Pengelola
                </h1>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider">
                    Ubah informasi nama, email, dan kata sandi akun administratif Anda.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left Side: Profile Information & Password Form -->
            <div class="lg:col-span-8 space-y-8">
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    class="space-y-8"
                >
                    <!-- Personal Info Card -->
                    <div class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <div
                                class="p-2.5 rounded-xl"
                                style="color: {primaryColor}; background-color: {primaryColor}1A;"
                            >
                                <i class="ti ti-user-edit text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-outfit font-black text-slate-800 text-base leading-none">
                                    Informasi Pribadi
                                </h3>
                                <p class="text-xs text-slate-400 font-medium mt-1">
                                    Perbarui nama publik dan alamat email resmi Anda.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="col-span-1">
                                <Input
                                    id="input-name"
                                    bind:value={form.name}
                                    label="Nama Lengkap"
                                    placeholder="Masukkan nama lengkap Anda"
                                    required={true}
                                    error={form.errors.name}
                                />
                            </div>

                            <div class="col-span-1">
                                <Input
                                    id="input-email"
                                    bind:value={form.email}
                                    label="Alamat Email"
                                    type="email"
                                    placeholder="Masukkan alamat email Anda"
                                    required={true}
                                    error={form.errors.email}
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Change Password Card -->
                    <div class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <div
                                class="p-2.5 rounded-xl"
                                style="color: {secondaryColor}; background-color: {secondaryColor}1A;"
                            >
                                <i class="ti ti-key text-lg"></i>
                            </div>
                            <div>
                                <h3 class="font-outfit font-black text-slate-800 text-base leading-none">
                                    Ubah Kata Sandi
                                </h3>
                                <p class="text-xs text-slate-400 font-medium mt-1">
                                    Kosongkan jika Anda tidak ingin mengubah kata sandi saat ini.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="col-span-1">
                                <Input
                                    id="input-password"
                                    bind:value={form.password}
                                    label="Kata Sandi Baru"
                                    type="password"
                                    placeholder="Masukkan kata sandi baru"
                                    error={form.errors.password}
                                />
                            </div>

                            <div class="col-span-1">
                                <Input
                                    id="input-password-confirmation"
                                    bind:value={form.password_confirmation}
                                    label="Konfirmasi Kata Sandi Baru"
                                    type="password"
                                    placeholder="Konfirmasi kata sandi baru"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70"
                            style="background-color: {primaryColor};"
                        >
                            {#if form.processing}
                                <i class="ti ti-loader animate-spin text-lg"></i>
                                Memperbarui...
                            {:else}
                                <i class="ti ti-device-floppy text-lg"></i>
                                Perbarui Profil
                            {/if}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Side: Profile Summary Card -->
            <div class="lg:col-span-4 space-y-8">
                <div class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 flex flex-col items-center text-center space-y-6">
                    <div
                        class="w-24 h-24 rounded-full border-4 p-1 flex items-center justify-center shrink-0 shadow-md"
                        style="border-color: {secondaryColor};"
                    >
                        <div
                            class="w-full h-full text-white font-black text-2xl rounded-full flex items-center justify-center uppercase"
                            style="background: linear-gradient(135deg, {primaryColor}, {secondaryColor});"
                        >
                            {user ? user.name.substring(0, 2) : 'AD'}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <h2 class="font-outfit font-black text-slate-800 text-lg">
                            {user?.name || 'Administrator'}
                        </h2>
                        <p class="text-xs text-slate-500 font-medium">
                            {user?.email || 'admin@bizmate.id'}
                        </p>
                    </div>

                    <div class="h-px w-full bg-slate-100"></div>

                    <!-- Active Roles Info -->
                    <div class="w-full space-y-2">
                        <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase block mb-1">
                            Peran Akses Sistem
                        </span>
                        <div class="flex flex-wrap gap-2 justify-center">
                            {#if user?.roles && user.roles.length > 0}
                                {#each user.roles as role}
                                    <span
                                        class="px-3 py-1 bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black tracking-wide uppercase rounded-full"
                                        style="color: {primaryColor}; background-color: {primaryColor}10;"
                                    >
                                        {role.name}
                                    </span>
                                {/each}
                            {:else}
                                <span
                                    class="px-3 py-1 bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black tracking-wide uppercase rounded-full"
                                    style="color: {primaryColor}; background-color: {primaryColor}10;"
                                >
                                    Super Admin
                                </span>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</AdminLayout>
