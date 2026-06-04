<script lang="ts">
    import { useForm, page, Link, router } from '@inertiajs/svelte';
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { showToast } from '@/utils/toast';

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');
    const user = $derived(page.props.auth?.user);

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
    const previewUrl = $derived(localPreviewUrl || (user?.avatar ? `/storage/${user.avatar}` : null));
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
            }
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
            }
        });
    }

    let showPasswordModal = $state(false);
    let sendingReset = $state(false);

    function sendResetLink() {
        const email = user?.email;
        if (!email) return;

        sendingReset = true;
        router.post('/forgot-password', {
            email: email,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Handled globally
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error', 'top');
            },
            onFinish: () => {
                sendingReset = false;
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
    <div class="w-full md:max-w-6xl md:mx-auto md:px-6 lg:px-8 md:py-8 font-sans">
        <!-- ==================== MOBILE LAYOUT ==================== -->
        <div class="max-w-md mx-auto min-h-[calc(100vh-56px)] md:hidden bg-slate-50 flex flex-col relative pb-32">
            <!-- Header -->
            <div class="sticky top-0 z-30 bg-white border-b border-slate-100 px-4 py-4 flex items-center gap-3">
                <button onclick={goBack} class="p-1 hover:bg-slate-100 rounded-full transition" aria-label="Kembali">
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </button>
                <h1 class="font-outfit font-black text-lg text-slate-800">Edit Profil</h1>
            </div>

            <!-- Profile Summary Card -->
            <div class="bg-white p-6 border-b border-slate-100 flex flex-col items-center text-center space-y-4">
                <button type="button" onclick={() => fileInput.click()} class="relative group">
                    <div class="w-24 h-24 rounded-full border-4 p-0.5 flex items-center justify-center shadow-md overflow-hidden relative" style="border-color: {secondary};">
                        {#if previewUrl}
                            <img src={previewUrl} alt="Avatar" class="w-full h-full object-cover rounded-full" />
                        {:else}
                            <div class="w-full h-full text-white font-black text-2xl rounded-full flex items-center justify-center uppercase" style="background: linear-gradient(135deg, {primary}, {secondary});">
                                {user ? user.name.substring(0, 2) : 'CS'}
                            </div>
                        {/if}
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition">
                            <i class="ti ti-camera text-white text-2xl"></i>
                        </div>
                    </div>
                </button>
                <input type="file" bind:this={fileInput} accept="image/*" class="hidden" onchange={handleFileChange} />
                <div>
                    <h2 class="text-base font-black text-slate-800">{user?.name}</h2>
                    <p class="text-xs text-slate-400 font-medium">{user?.email}</p>
                </div>
            </div>

            <div class="p-4 space-y-5 flex-grow">
                <!-- Form Profil Mobile -->
                <form onsubmit={(e) => { e.preventDefault(); triggerProfileSave(); }}>
                    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm space-y-4">
                        <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase block mb-1">Informasi Pribadi</span>
                        
                        <div>
                            <label for="mob-name" class="block text-xs font-bold text-slate-600 mb-1.5">Nama Lengkap</label>
                            <input id="mob-name" type="text" bind:value={profileForm.name} required placeholder="Nama Lengkap Anda" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.name ? 'border-rose-500' : ''}" />
                            {#if profileForm.errors.name}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.name}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="mob-email" class="block text-xs font-bold text-slate-600 mb-1.5">Email</label>
                            <input id="mob-email" type="email" bind:value={profileForm.email} required placeholder="Alamat Email Anda" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.email ? 'border-rose-500' : ''}" />
                            {#if profileForm.errors.email}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.email}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="mob-phone" class="block text-xs font-bold text-slate-600 mb-1.5">Nomor Telepon</label>
                            <input id="mob-phone" type="text" bind:value={profileForm.phone_number} placeholder="08xxxxxxxxx" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.phone_number ? 'border-rose-500' : ''}" />
                            {#if profileForm.errors.phone_number}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.phone_number}</p>
                            {/if}
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="mob-gender" class="block text-xs font-bold text-slate-600 mb-1.5">Jenis Kelamin</label>
                                <select id="mob-gender" bind:value={profileForm.gender} class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.gender ? 'border-rose-500' : ''}">
                                    <option value="">Pilih</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                                {#if profileForm.errors.gender}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.gender}</p>
                                {/if}
                            </div>

                            <div>
                                <label for="mob-dob" class="block text-xs font-bold text-slate-600 mb-1.5">Tanggal Lahir</label>
                                <input id="mob-dob" type="date" bind:value={profileForm.birth_date} class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.birth_date ? 'border-rose-500' : ''}" />
                                {#if profileForm.errors.birth_date}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.birth_date}</p>
                                {/if}
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" disabled={profileForm.processing} class="w-full py-3.5 rounded-2xl font-bold text-white shadow-lg transition flex items-center justify-center gap-2 hover:opacity-90 disabled:opacity-50" style="background-color: {primary};">
                                {#if profileForm.processing}
                                    <i class="ti ti-loader animate-spin text-lg"></i> Menyimpan...
                                {:else}
                                    Simpan Profil
                                {/if}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Form Password Mobile -->
                <form onsubmit={(e) => { e.preventDefault(); submitPassword(); }}>
                    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm space-y-4">
                        <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase block mb-1">Ubah Kata Sandi</span>
                        
                        <div>
                            <div class="flex justify-between items-center mb-1.5">
                                <label for="mob-current-password" class="block text-xs font-bold text-slate-600">Kata Sandi Saat Ini</label>
                                <button type="button" onclick={sendResetLink} disabled={sendingReset} class="text-[10px] font-black uppercase tracking-wider hover:underline" style="color: {secondary};">
                                    {sendingReset ? 'Mengirim...' : 'Lupa Kata Sandi?'}
                                </button>
                            </div>
                            <input id="mob-current-password" type="password" bind:value={passwordForm.current_password} required placeholder="Kata sandi saat ini" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {passwordForm.errors.current_password ? 'border-rose-500' : ''}" />
                            {#if passwordForm.errors.current_password}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{passwordForm.errors.current_password}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="mob-password" class="block text-xs font-bold text-slate-600 mb-1.5">Kata Sandi Baru</label>
                            <input id="mob-password" type="password" bind:value={passwordForm.password} required placeholder="Kata sandi baru" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {passwordForm.errors.password ? 'border-rose-500' : ''}" />
                            {#if passwordForm.errors.password}
                                <p class="text-[10px] text-rose-500 font-bold mt-1">{passwordForm.errors.password}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="mob-password-conf" class="block text-xs font-bold text-slate-600 mb-1.5">Konfirmasi Kata Sandi Baru</label>
                            <input id="mob-password-conf" type="password" bind:value={passwordForm.password_confirmation} required placeholder="Ulangi kata sandi baru" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition" />
                        </div>

                        <div class="pt-2">
                            <button type="submit" disabled={passwordForm.processing} class="w-full py-3.5 rounded-2xl font-bold text-white shadow-lg transition flex items-center justify-center gap-2 hover:opacity-90 disabled:opacity-50" style="background-color: {secondary};">
                                {#if passwordForm.processing}
                                    <i class="ti ti-loader animate-spin text-lg"></i> Memperbarui...
                                {:else}
                                    Perbarui Kata Sandi
                                {/if}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ==================== DESKTOP LAYOUT ==================== -->
        <div class="hidden md:block max-w-6xl mx-auto w-full">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Left Sidebar: Profile Picture & Summary -->
                <div class="col-span-1 space-y-6">
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col items-center text-center space-y-4">
                        <button type="button" onclick={() => fileInput.click()} class="relative group">
                            <div class="w-32 h-32 rounded-full border-4 p-1 flex items-center justify-center shadow-lg overflow-hidden relative" style="border-color: {secondary};">
                                {#if previewUrl}
                                    <img src={previewUrl} alt="Avatar" class="w-full h-full object-cover rounded-full" />
                                {:else}
                                    <div class="w-full h-full text-white font-black text-4xl rounded-full flex items-center justify-center uppercase" style="background: linear-gradient(135deg, {primary}, {secondary});">
                                        {user ? user.name.substring(0, 2) : 'CS'}
                                    </div>
                                {/if}
                                <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition">
                                    <i class="ti ti-camera text-white text-3xl mb-1"></i>
                                    <span class="text-[10px] text-white font-bold tracking-wider">UBAH FOTO</span>
                                </div>
                            </div>
                        </button>
                        <div>
                            <h2 class="text-lg font-black text-slate-800">{user?.name}</h2>
                            <p class="text-sm text-slate-500 font-medium">{user?.email}</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Forms -->
                <div class="col-span-2 space-y-6">
                    <!-- Form Profil -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-8">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h1 class="font-outfit font-black text-xl text-slate-800">Informasi Pribadi</h1>
                            <p class="text-xs text-slate-400 font-medium mt-1">Kelola data profil lengkap Anda.</p>
                        </div>

                        <form onsubmit={(e) => { e.preventDefault(); triggerProfileSave(); }} class="space-y-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label for="name" class="block text-xs font-bold text-slate-600 mb-1.5">Nama Lengkap</label>
                                    <input id="name" type="text" bind:value={profileForm.name} required placeholder="Nama Lengkap Anda" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.name ? 'border-rose-500' : ''}" />
                                    {#if profileForm.errors.name}
                                        <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.name}</p>
                                    {/if}
                                </div>

                                <div>
                                    <label for="email" class="block text-xs font-bold text-slate-600 mb-1.5">Email</label>
                                    <input id="email" type="email" bind:value={profileForm.email} required placeholder="Alamat Email Anda" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.email ? 'border-rose-500' : ''}" />
                                    {#if profileForm.errors.email}
                                        <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.email}</p>
                                    {/if}
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-xs font-bold text-slate-600 mb-1.5">Nomor Telepon</label>
                                    <input id="phone" type="text" bind:value={profileForm.phone_number} placeholder="08xxxxxxxxx" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.phone_number ? 'border-rose-500' : ''}" />
                                    {#if profileForm.errors.phone_number}
                                        <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.phone_number}</p>
                                    {/if}
                                </div>
                                
                                <div>
                                    <label for="gender" class="block text-xs font-bold text-slate-600 mb-1.5">Jenis Kelamin</label>
                                    <select id="gender" bind:value={profileForm.gender} class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.gender ? 'border-rose-500' : ''}">
                                        <option value="">Pilih</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </select>
                                    {#if profileForm.errors.gender}
                                        <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.gender}</p>
                                    {/if}
                                </div>
                                
                                <div>
                                    <label for="dob" class="block text-xs font-bold text-slate-600 mb-1.5">Tanggal Lahir</label>
                                    <input id="dob" type="date" bind:value={profileForm.birth_date} class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.birth_date ? 'border-rose-500' : ''}" />
                                    {#if profileForm.errors.birth_date}
                                        <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.birth_date}</p>
                                    {/if}
                                </div>
                            </div>

                            <div class="flex justify-end pt-4">
                                <button type="submit" disabled={profileForm.processing} class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70" style="background-color: {primary};">
                                    {#if profileForm.processing}
                                        <i class="ti ti-loader animate-spin text-lg"></i> Menyimpan...
                                    {:else}
                                        <i class="ti ti-device-floppy text-lg"></i> Simpan Profil
                                    {/if}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Form Password -->
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-8">
                        <div class="border-b border-slate-100 pb-4 mb-6">
                            <h2 class="font-outfit font-black text-xl text-slate-800">Keamanan</h2>
                            <p class="text-xs text-slate-400 font-medium mt-1">Ubah kata sandi akun Anda.</p>
                        </div>

                        <form onsubmit={(e) => { e.preventDefault(); submitPassword(); }} class="space-y-6">
                            <div>
                                <div class="flex justify-between items-center mb-1.5 sm:w-1/2">
                                    <label for="current_password" class="block text-xs font-bold text-slate-600">Kata Sandi Saat Ini</label>
                                    <button type="button" onclick={sendResetLink} disabled={sendingReset} class="text-xs font-bold hover:underline" style="color: {secondary};">
                                        {sendingReset ? 'Mengirim...' : 'Lupa Kata Sandi?'}
                                    </button>
                                </div>
                                <input id="current_password" type="password" bind:value={passwordForm.current_password} required placeholder="Kata sandi saat ini" class="w-full sm:w-1/2 px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {passwordForm.errors.current_password ? 'border-rose-500' : ''}" />
                                {#if passwordForm.errors.current_password}
                                    <p class="text-[10px] text-rose-500 font-bold mt-1">{passwordForm.errors.current_password}</p>
                                {/if}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <label for="password" class="block text-xs font-bold text-slate-600 mb-1.5">Kata Sandi Baru</label>
                                    <input id="password" type="password" bind:value={passwordForm.password} required placeholder="Kata sandi baru" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {passwordForm.errors.password ? 'border-rose-500' : ''}" />
                                    {#if passwordForm.errors.password}
                                        <p class="text-[10px] text-rose-500 font-bold mt-1">{passwordForm.errors.password}</p>
                                    {/if}
                                </div>

                                <div>
                                    <label for="password-conf" class="block text-xs font-bold text-slate-600 mb-1.5">Konfirmasi Kata Sandi Baru</label>
                                    <input id="password-conf" type="password" bind:value={passwordForm.password_confirmation} required placeholder="Ulangi kata sandi baru" class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition" />
                                </div>
                            </div>

                            <div class="flex justify-end pt-4">
                                <button type="submit" disabled={passwordForm.processing} class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70" style="background-color: {secondary};">
                                    {#if passwordForm.processing}
                                        <i class="ti ti-loader animate-spin text-lg"></i> Memperbarui...
                                    {:else}
                                        <i class="ti ti-shield-check text-lg"></i> Perbarui Kata Sandi
                                    {/if}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</StorefrontLayout>

{#if showPasswordModal}
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <button type="button" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm w-full h-full cursor-default border-none p-0 focus:outline-none" onclick={() => showPasswordModal = false} aria-label="Tutup"></button>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm relative z-10 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-outfit font-black text-lg text-slate-800">Verifikasi Keamanan</h3>
                <button type="button" onclick={() => showPasswordModal = false} class="text-slate-400 hover:text-slate-600 transition" aria-label="Tutup">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>
            
            <form onsubmit={(e) => { e.preventDefault(); submitProfile(); }} class="p-6">
                <p class="text-sm text-slate-600 mb-4">
                    Masukkan kata sandi akun Anda untuk mengonfirmasi perubahan profil.
                </p>
                
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label for="modal_current_password" class="block text-xs font-bold text-slate-600">Kata Sandi Akun</label>
                            <button type="button" onclick={sendResetLink} disabled={sendingReset} class="text-[10px] font-black uppercase tracking-wider hover:underline" style="color: {secondary};">
                                {sendingReset ? 'Mengirim...' : 'Lupa Kata Sandi?'}
                            </button>
                        </div>
                        <input id="modal_current_password" type="password" bind:value={profileForm.current_password} required placeholder="Kata sandi akun Anda" class="w-full px-4 py-3 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition {profileForm.errors.current_password ? 'border-rose-500' : ''}" />
                        {#if profileForm.errors.current_password}
                            <p class="text-[10px] text-rose-500 font-bold mt-1">{profileForm.errors.current_password}</p>
                        {/if}
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick={() => showPasswordModal = false} class="px-4 py-2 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" disabled={profileForm.processing} class="px-5 py-2 text-sm font-bold text-white rounded-xl shadow-md flex items-center gap-2 disabled:opacity-70 transition hover:shadow-lg" style="background-color: {primary};">
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
