<script lang="ts">
    import { useForm, page, Link } from '@inertiajs/svelte';
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { showToast } from '@/utils/toast';

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');
    const user = $derived(page.props.auth?.user);

    const form = useForm({
        name: (page.props.auth as any)?.user?.name || '',
        email: (page.props.auth as any)?.user?.email || '',
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit() {
        form.put('/profile', {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Profil Anda berhasil diperbarui!', 'success', 'top');
                form.reset('current_password', 'password', 'password_confirmation');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error', 'top');
            }
        });
    }

    function goBack() {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/';
        }
    }
</script>

<svelte:head>
    <title>Profil Saya</title>
</svelte:head>

<StorefrontLayout hideMobileFooter={true}>
    <!-- Desktop & Mobile Container -->
    <div class="w-full md:max-w-6xl md:mx-auto md:px-6 lg:px-8 md:py-8 font-sans">

        <!-- ==================== MOBILE LAYOUT (hidden on desktop) ==================== -->
        <div class="max-w-md mx-auto min-h-[calc(100vh-56px)] md:hidden bg-slate-50 flex flex-col relative pb-20">
            <!-- Header -->
            <div class="sticky top-0 z-30 bg-white border-b border-slate-100 px-4 py-4 flex items-center gap-3">
                <button
                    onclick={goBack}
                    class="p-1 hover:bg-slate-100 rounded-full transition"
                    aria-label="Kembali"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </button>
                <h1 class="font-outfit font-black text-lg text-slate-800">
                    Edit Profil
                </h1>
            </div>

            <!-- Profile Summary Card -->
            <div class="bg-white p-6 border-b border-slate-100 flex flex-col items-center text-center space-y-4">
                <div
                    class="w-20 h-20 rounded-full border-4 p-0.5 flex items-center justify-center shadow-md"
                    style="border-color: {secondary};"
                >
                    <div
                        class="w-full h-full text-white font-black text-xl rounded-full flex items-center justify-center uppercase"
                        style="background: linear-gradient(135deg, {primary}, {secondary});"
                    >
                        {user ? user.name.substring(0, 2) : 'CS'}
                    </div>
                </div>
                <div>
                    <h2 class="text-base font-black text-slate-800">{user?.name}</h2>
                    <p class="text-xs text-slate-400 font-medium">{user?.email}</p>
                </div>
            </div>

            <!-- Mobile Form Form -->
            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                class="p-4 space-y-5 flex-grow"
            >
                <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm space-y-4">
                    <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase block mb-1">
                        Informasi Pribadi
                    </span>
                    <div>
                        <label for="mob-name" class="block text-xs font-bold text-slate-600 mb-1.5">Nama Lengkap</label>
                        <input
                            id="mob-name"
                            type="text"
                            bind:value={form.name}
                            required
                            placeholder="Nama Lengkap Anda"
                            class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.name ? 'border-rose-500' : ''}"
                        />
                        {#if form.errors.name}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.name}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="mob-email" class="block text-xs font-bold text-slate-600 mb-1.5">Email</label>
                        <input
                            id="mob-email"
                            type="email"
                            bind:value={form.email}
                            required
                            placeholder="Alamat Email Anda"
                            class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.email ? 'border-rose-500' : ''}"
                        />
                        {#if form.errors.email}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.email}</p>
                        {/if}
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm space-y-4">
                    <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase block mb-1">
                        Ubah Kata Sandi (Opsional)
                    </span>
                    <div>
                        <label for="mob-current-password" class="block text-xs font-bold text-slate-600 mb-1.5">Kata Sandi Lama</label>
                        <input
                            id="mob-current-password"
                            type="password"
                            bind:value={form.current_password}
                            placeholder="Kata sandi saat ini"
                            class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.current_password ? 'border-rose-500' : ''}"
                        />
                        {#if form.errors.current_password}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.current_password}</p>
                        {/if}
                    </div>
                    <div>
                        <label for="mob-password" class="block text-xs font-bold text-slate-600 mb-1.5">Kata Sandi Baru</label>
                        <input
                            id="mob-password"
                            type="password"
                            bind:value={form.password}
                            placeholder="Kata sandi baru"
                            class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.password ? 'border-rose-500' : ''}"
                        />
                        {#if form.errors.password}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.password}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="mob-password-conf" class="block text-xs font-bold text-slate-600 mb-1.5">Konfirmasi Kata Sandi Baru</label>
                        <input
                            id="mob-password-conf"
                            type="password"
                            bind:value={form.password_confirmation}
                            placeholder="Ulangi kata sandi baru"
                            class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                        />
                    </div>
                </div>

                <!-- Sticky Bottom Action Button for Mobile -->
                <div class="fixed bottom-0 left-0 right-0 max-w-md mx-auto p-4 bg-white border-t border-slate-100 z-30">
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="w-full py-3.5 rounded-2xl font-bold text-white shadow-lg transition flex items-center justify-center gap-2 hover:opacity-90 disabled:opacity-50"
                        style="background-color: {primary};"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin text-lg"></i>
                            Memperbarui...
                        {:else}
                            Simpan Perubahan
                        {/if}
                    </button>
                </div>
            </form>
        </div>

        <!-- ==================== DESKTOP LAYOUT (hidden on mobile) ==================== -->
        <div class="hidden md:block max-w-6xl mx-auto w-full">
            <!-- Right Column: Profile Form -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">
                <div class="border-b border-slate-100 pb-4">
                    <h1 class="font-outfit font-black text-xl text-slate-800">Profil Saya</h1>
                    <p class="text-xs text-slate-400 font-medium mt-1">
                        Kelola informasi akun pribadi dan kata sandi Anda.
                    </p>
                </div>

                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    class="space-y-6"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-xs font-bold text-slate-600 mb-1.5">Nama Lengkap</label>
                            <input
                                id="name"
                                type="text"
                                bind:value={form.name}
                                required
                                placeholder="Nama Lengkap Anda"
                                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.name ? 'border-rose-500' : ''}"
                            />
                            {#if form.errors.name}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.name}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="email" class="block text-xs font-bold text-slate-600 mb-1.5">Email</label>
                            <input
                                id="email"
                                type="email"
                                bind:value={form.email}
                                required
                                placeholder="Alamat Email Anda"
                                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.email ? 'border-rose-500' : ''}"
                            />
                            {#if form.errors.email}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.email}</p>
                            {/if}
                        </div>
                    </div>

                    <div class="h-px bg-slate-100 my-2"></div>

                    <span class="text-xs font-black text-slate-700 uppercase tracking-tight block">
                        Ubah Kata Sandi (Opsional)
                    </span>

                    <div>
                        <label for="current_password" class="block text-xs font-bold text-slate-600 mb-1.5">Kata Sandi Lama</label>
                        <input
                            id="current_password"
                            type="password"
                            bind:value={form.current_password}
                            placeholder="Kata sandi saat ini"
                            class="w-full sm:w-1/2 px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.current_password ? 'border-rose-500' : ''}"
                        />
                        {#if form.errors.current_password}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.current_password}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="password" class="block text-xs font-bold text-slate-600 mb-1.5">Kata Sandi Baru</label>
                            <input
                                id="password"
                                type="password"
                                bind:value={form.password}
                                placeholder="Kata sandi baru"
                                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {form.errors.password ? 'border-rose-500' : ''}"
                            />
                            {#if form.errors.password}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{form.errors.password}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="password-conf" class="block text-xs font-bold text-slate-600 mb-1.5">Konfirmasi Kata Sandi Baru</label>
                            <input
                                id="password-conf"
                                type="password"
                                bind:value={form.password_confirmation}
                                placeholder="Ulangi kata sandi baru"
                                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70"
                            style="background-color: {primary};"
                        >
                            {#if form.processing}
                                <i class="ti ti-loader animate-spin text-lg"></i>
                                Memperbarui...
                            {:else}
                                <i class="ti ti-device-floppy text-lg"></i>
                                Simpan Perubahan
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</StorefrontLayout>
