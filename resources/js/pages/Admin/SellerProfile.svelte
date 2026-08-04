<script lang="ts">
    import { useForm, page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Input from '@/components/ui/Input.svelte';
    import { showToast } from '@/utils/toast';
    import { fade, slide } from 'svelte/transition';

    let {
        user,
        addresses = [],
        isBiteshipEnabled = false,
        isRajaOngkirEnabled = false,
    } = $props();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    // ── Tabs ──────────────────────────────────────────────────
    let activeTab = $state<'store' | 'address'>('store');

    // ── Store Info Form ───────────────────────────────────────
    const storeForm = useForm({
        _method: 'put',
        store_name: user?.store_name || '',
        store_description: user?.store_description || '',
        store_logo: null as File | null,
    });

    let logoPreviewUrl = $state<string | null>(null);
    const logoDisplayUrl = $derived(
        logoPreviewUrl ||
            (user?.store_logo ? `/storage/${user.store_logo}` : null),
    );
    let logoFileInput: HTMLInputElement;

    function handleLogoChange(event: Event) {
        const input = event.target as HTMLInputElement;
        if (input.files && input.files[0]) {
            const file = input.files[0];
            storeForm.store_logo = file;
            const reader = new FileReader();
            reader.onload = (e) => {
                logoPreviewUrl = e.target?.result as string;
            };
            reader.readAsDataURL(file);
        }
    }

    function submitStore() {
        storeForm.post('/admin/seller/profile', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                logoPreviewUrl = null;
                showToast('Info toko berhasil diperbarui!', 'success');
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error');
            },
        });
    }

    let showAddressForm = $state(false);
    let editingAddressId = $state<string | null>(null);

    const addressForm = useForm({
        label: 'Toko',
        receiver_name: user?.name || '',
        phone_number: user?.phone_number || '',
        full_address: '',
        province_name: '',
        regency_name: '',
        district_name: '',
        village_name: '',
        postal_code: '',
        note: '',
        is_primary: addresses.length === 0,
    });

    function openNewAddress() {
        editingAddressId = null;
        addressForm.label = 'Toko';
        addressForm.receiver_name = user?.name || '';
        addressForm.phone_number = user?.phone_number || '';
        addressForm.full_address = '';
        addressForm.province_name = '';
        addressForm.regency_name = '';
        addressForm.district_name = '';
        addressForm.village_name = '';
        addressForm.postal_code = '';
        addressForm.note = '';
        addressForm.is_primary = addresses.length === 0;
        showAddressForm = true;
    }

    function openEditAddress(addr: any) {
        editingAddressId = addr.id;
        addressForm.label = addr.label || 'Toko';
        addressForm.receiver_name = addr.receiver_name || '';
        addressForm.phone_number = addr.phone_number || '';
        addressForm.full_address = addr.full_address || '';
        addressForm.province_name = addr.province_name || '';
        addressForm.regency_name = addr.regency_name || '';
        addressForm.district_name = addr.district_name || '';
        addressForm.village_name = addr.village_name || '';
        addressForm.postal_code = addr.postal_code || '';
        addressForm.note = addr.note || '';
        addressForm.is_primary = addr.is_primary || false;
        showAddressForm = true;
    }

    function cancelAddressForm() {
        showAddressForm = false;
        editingAddressId = null;
    }

    function submitAddress() {
        if (editingAddressId) {
            addressForm.put(`/admin/seller/addresses/${editingAddressId}`, {
                preserveScroll: true,
                onSuccess: () => {
                    showAddressForm = false;
                    editingAddressId = null;
                    showToast('Alamat berhasil diperbarui!', 'success');
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    showToast(firstError as string, 'error');
                },
            });
        } else {
            addressForm.post('/admin/seller/addresses', {
                preserveScroll: true,
                onSuccess: () => {
                    showAddressForm = false;
                    showToast('Alamat berhasil ditambahkan!', 'success');
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    showToast(firstError as string, 'error');
                },
            });
        }
    }

    function deleteAddress(id: string) {
        if (!confirm('Hapus alamat ini?')) {
            return;
        }
        router.delete(`/admin/seller/addresses/${id}`, {
            preserveScroll: true,
            onSuccess: () => showToast('Alamat dihapus.', 'success'),
        });
    }

    function makePrimary(id: string) {
        router.post(
            `/admin/seller/addresses/${id}/make-primary`,
            {},
            {
                preserveScroll: true,
                onSuccess: () =>
                    showToast('Alamat utama diperbarui.', 'success'),
            },
        );
    }

    const labelOptions = ['Toko', 'Gudang', 'Kantor', 'Rumah', 'Lainnya'];
</script>

<svelte:head>
    <title>Profil Toko Saya</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
        <!-- Page Header -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2"
        >
            <div>
                <h1 class="font-outfit font-black text-2xl text-slate-800">
                    Profil Toko Saya
                </h1>
                <p
                    class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                >
                    Kelola identitas toko dan alamat pengiriman Anda.
                </p>
            </div>
        </div>

        <!-- Store Identity Card (always visible) -->
        <div
            class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 flex items-center gap-5"
        >
            <!-- Logo -->
            <button
                type="button"
                onclick={() => logoFileInput.click()}
                class="relative group shrink-0 focus:outline-none"
            >
                <div
                    class="w-20 h-20 rounded-2xl border-2 overflow-hidden flex items-center justify-center shadow-sm"
                    style="border-color: {primaryColor}30;"
                >
                    {#if logoDisplayUrl}
                        <img
                            src={logoDisplayUrl}
                            alt="Logo Toko"
                            class="w-full h-full object-cover"
                        />
                    {:else}
                        <div
                            class="w-full h-full flex items-center justify-center text-white text-2xl font-black"
                            style="background: linear-gradient(135deg, {primaryColor}, {secondaryColor});"
                        >
                            {user?.store_name?.substring(0, 1)?.toUpperCase() ||
                                'T'}
                        </div>
                    {/if}
                    <div
                        class="absolute inset-0 bg-black/40 flex items-center justify-center rounded-2xl opacity-0 group-hover:opacity-100 transition"
                    >
                        <i class="ti ti-camera text-white text-xl"></i>
                    </div>
                </div>
            </button>
            <input
                type="file"
                bind:this={logoFileInput}
                accept="image/*"
                class="hidden"
                onchange={handleLogoChange}
            />
            <div class="min-w-0 flex-1">
                <p
                    class="font-outfit font-black text-xl text-slate-800 truncate"
                >
                    {user?.store_name || 'Toko Saya'}
                </p>
                <p class="text-xs text-slate-400 mt-1">
                    <span
                        class="font-mono bg-slate-50 border border-slate-100 rounded px-2 py-0.5"
                    >
                        /{user?.store_slug || '—'}
                    </span>
                </p>
                <p class="text-xs text-slate-500 mt-2 line-clamp-2">
                    {user?.store_description || 'Belum ada deskripsi toko.'}
                </p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 bg-slate-100 rounded-2xl p-1">
            <button
                type="button"
                onclick={() => (activeTab = 'store')}
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold rounded-xl transition
                    {activeTab === 'store'
                    ? 'bg-white shadow-sm text-slate-900'
                    : 'text-slate-500 hover:text-slate-700'}"
            >
                <i class="ti ti-building-store text-base"></i>
                Info Toko
            </button>
            <button
                type="button"
                onclick={() => (activeTab = 'address')}
                class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold rounded-xl transition
                    {activeTab === 'address'
                    ? 'bg-white shadow-sm text-slate-900'
                    : 'text-slate-500 hover:text-slate-700'}"
            >
                <i class="ti ti-map-pin text-base"></i>
                Alamat
                {#if addresses.length > 0}
                    <span
                        class="ml-1 px-1.5 py-0.5 text-[10px] font-black rounded-full text-white"
                        style="background-color: {primaryColor};"
                    >
                        {addresses.length}
                    </span>
                {/if}
            </button>
        </div>

        <!-- ── Tab: Store Info ─────────────────────────────── -->
        {#if activeTab === 'store'}
            <div transition:fade={{ duration: 150 }}>
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        submitStore();
                    }}
                    class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 sm:p-8 space-y-6"
                >
                    <div
                        class="flex items-center gap-3 border-b border-slate-100 pb-4"
                    >
                        <div
                            class="p-2.5 rounded-xl"
                            style="color: {primaryColor}; background-color: {primaryColor}1A;"
                        >
                            <i class="ti ti-building-store text-lg"></i>
                        </div>
                        <div>
                            <h3
                                class="font-outfit font-black text-slate-800 text-base leading-none"
                            >
                                Informasi Toko
                            </h3>
                            <p class="text-xs text-slate-400 font-medium mt-1">
                                Perbarui nama, logo, dan deskripsi toko Anda.
                            </p>
                        </div>
                    </div>

                    <!-- Logo upload section -->
                    <div>
                        <label
                            class="block text-xs font-bold text-slate-600 mb-2"
                            >Logo Toko</label
                        >
                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                onclick={() => logoFileInput.click()}
                                class="relative group focus:outline-none"
                            >
                                <div
                                    class="w-16 h-16 rounded-2xl border-2 overflow-hidden flex items-center justify-center"
                                    style="border-color: {primaryColor}40;"
                                >
                                    {#if logoDisplayUrl}
                                        <img
                                            src={logoDisplayUrl}
                                            alt="Logo"
                                            class="w-full h-full object-cover"
                                        />
                                    {:else}
                                        <div
                                            class="w-full h-full flex items-center justify-center text-white text-xl font-black"
                                            style="background: linear-gradient(135deg, {primaryColor}, {secondaryColor});"
                                        >
                                            {user?.store_name
                                                ?.substring(0, 1)
                                                ?.toUpperCase() || 'T'}
                                        </div>
                                    {/if}
                                    <div
                                        class="absolute inset-0 bg-black/30 flex items-center justify-center rounded-2xl opacity-0 group-hover:opacity-100 transition"
                                    >
                                        <i class="ti ti-camera text-white"></i>
                                    </div>
                                </div>
                            </button>
                            <div class="text-xs text-slate-400 space-y-1">
                                <p>Klik logo untuk mengganti gambar.</p>
                                <p>Format: JPG, PNG, WebP · Maks 2MB</p>
                                {#if storeForm.errors.store_logo}
                                    <p class="text-rose-500 font-bold">
                                        {storeForm.errors.store_logo}
                                    </p>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="sm:col-span-2">
                            <Input
                                id="store-name"
                                bind:value={storeForm.store_name}
                                label="Nama Toko"
                                placeholder="Nama toko Anda"
                                required={true}
                                error={storeForm.errors.store_name}
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <label
                                for="store-description"
                                class="block text-xs font-bold text-slate-600 mb-1.5"
                            >
                                Deskripsi Toko
                            </label>
                            <textarea
                                id="store-description"
                                bind:value={storeForm.store_description}
                                rows="4"
                                placeholder="Ceritakan tentang toko Anda..."
                                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition resize-none {storeForm
                                    .errors.store_description
                                    ? 'border-rose-500'
                                    : ''}"
                            ></textarea>
                            {#if storeForm.errors.store_description}
                                <p
                                    class="text-[10px] text-rose-500 font-bold mt-1"
                                >
                                    {storeForm.errors.store_description}
                                </p>
                            {/if}
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            disabled={storeForm.processing}
                            class="px-6 py-3 text-white font-bold text-sm rounded-2xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70"
                            style="background-color: {primaryColor};"
                        >
                            {#if storeForm.processing}
                                <i class="ti ti-loader animate-spin text-lg"
                                ></i> Menyimpan...
                            {:else}
                                <i class="ti ti-device-floppy text-lg"></i> Simpan
                                Info Toko
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        {/if}

        <!-- ── Tab: Addresses ──────────────────────────────── -->
        {#if activeTab === 'address'}
            <div class="space-y-4" transition:fade={{ duration: 150 }}>
                <!-- Store Address Banner & Action Button -->
                {#if !showAddressForm}
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 bg-blue-50/70 border border-blue-100 rounded-2xl">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0">
                                <i class="ti ti-building-store text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-slate-800">Alamat Toko Utama Penjual</h4>
                                <p class="text-xs text-slate-500 font-medium mt-0.5">
                                    Khusus Penjual hanya memiliki 1 Alamat Toko Utama yang digunakan sebagai lokasi pengiriman produk.
                                </p>
                            </div>
                        </div>
                        {#if addresses.length === 0}
                            <button
                                type="button"
                                onclick={openNewAddress}
                                class="flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white rounded-2xl shadow-md hover:shadow-lg transition shrink-0"
                                style="background-color: {primaryColor};"
                            >
                                <i class="ti ti-plus text-base"></i>
                                Tambah Alamat Toko
                            </button>
                        {:else}
                            <button
                                type="button"
                                onclick={() => openEditAddress(addresses[0])}
                                class="flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white rounded-2xl shadow-md hover:shadow-lg transition shrink-0"
                                style="background-color: {primaryColor};"
                            >
                                <i class="ti ti-pencil text-base"></i>
                                Edit Alamat Toko
                            </button>
                        {/if}
                    </div>
                {/if}

                <!-- Address Form -->
                {#if showAddressForm}
                    <div
                        transition:slide={{ duration: 200 }}
                        class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 space-y-5"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 pb-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="p-2.5 rounded-xl"
                                    style="color: {primaryColor}; background-color: {primaryColor}1A;"
                                >
                                    <i class="ti ti-map-pin text-lg"></i>
                                </div>
                                <div>
                                    <h3
                                        class="font-outfit font-black text-slate-800 text-base leading-none"
                                    >
                                        {editingAddressId
                                            ? 'Edit Alamat'
                                            : 'Tambah Alamat Baru'}
                                    </h3>
                                    <p
                                        class="text-xs text-slate-400 font-medium mt-1"
                                    >
                                        Alamat toko/gudang pengiriman Anda.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onclick={cancelAddressForm}
                                class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded-xl transition"
                            >
                                <i class="ti ti-x text-lg"></i>
                            </button>
                        </div>

                        <form
                            onsubmit={(e) => {
                                e.preventDefault();
                                submitAddress();
                            }}
                            class="space-y-5"
                        >
                            <!-- Label -->
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                    >Label Alamat</label
                                >
                                <div class="flex flex-wrap gap-2">
                                    {#each labelOptions as opt}
                                        <button
                                            type="button"
                                            onclick={() =>
                                                (addressForm.label = opt)}
                                            class="px-3 py-1.5 text-xs font-bold rounded-xl border transition {addressForm.label ===
                                            opt
                                                ? 'text-white border-transparent'
                                                : 'text-slate-500 border-slate-200 hover:border-slate-300'}"
                                            style={addressForm.label === opt
                                                ? `background-color: ${primaryColor}; border-color: ${primaryColor};`
                                                : ''}
                                        >
                                            {opt}
                                        </button>
                                    {/each}
                                </div>
                                {#if addressForm.errors.label}
                                    <p
                                        class="text-[10px] text-rose-500 font-bold mt-1"
                                    >
                                        {addressForm.errors.label}
                                    </p>
                                {/if}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Input
                                    id="addr-receiver"
                                    bind:value={addressForm.receiver_name}
                                    label="Nama Penerima"
                                    placeholder="Nama penerima paket"
                                    required={true}
                                    error={addressForm.errors.receiver_name}
                                />
                                <Input
                                    id="addr-phone"
                                    bind:value={addressForm.phone_number}
                                    label="Nomor Telepon"
                                    placeholder="08xxxxxxxxxx"
                                    required={true}
                                    error={addressForm.errors.phone_number}
                                />
                            </div>

                            <div>
                                <label
                                    for="addr-full"
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                >
                                    Alamat Lengkap <span class="text-rose-500"
                                        >*</span
                                    >
                                </label>
                                <textarea
                                    id="addr-full"
                                    bind:value={addressForm.full_address}
                                    rows="3"
                                    placeholder="Nama jalan, nomor, RT/RW, dll."
                                    required
                                    class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition resize-none {addressForm
                                        .errors.full_address
                                        ? 'border-rose-500'
                                        : ''}"
                                ></textarea>
                                {#if addressForm.errors.full_address}
                                    <p
                                        class="text-[10px] text-rose-500 font-bold mt-1"
                                    >
                                        {addressForm.errors.full_address}
                                    </p>
                                {/if}
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <Input
                                    id="addr-province"
                                    bind:value={addressForm.province_name}
                                    label="Provinsi"
                                    placeholder="Contoh: Jawa Timur"
                                    error={addressForm.errors.province_name}
                                />
                                <Input
                                    id="addr-regency"
                                    bind:value={addressForm.regency_name}
                                    label="Kota / Kabupaten"
                                    placeholder="Contoh: Kota Surabaya"
                                    error={addressForm.errors.regency_name}
                                />
                                <Input
                                    id="addr-district"
                                    bind:value={addressForm.district_name}
                                    label="Kecamatan"
                                    placeholder="Contoh: Wonokromo"
                                    error={addressForm.errors.district_name}
                                />
                                <Input
                                    id="addr-postal"
                                    bind:value={addressForm.postal_code}
                                    label="Kode Pos"
                                    placeholder="Contoh: 60244"
                                    error={addressForm.errors.postal_code}
                                />
                            </div>

                            <Input
                                id="addr-note"
                                bind:value={addressForm.note}
                                label="Catatan (Opsional)"
                                placeholder="Contoh: Masuk gang kecil, cat pagar biru"
                                error={addressForm.errors.note}
                            />

                            <!-- Primary toggle -->
                            <label
                                class="flex items-center gap-3 cursor-pointer group"
                            >
                                <input
                                    type="checkbox"
                                    bind:checked={addressForm.is_primary}
                                    class="w-4 h-4 rounded accent-current"
                                    style="accent-color: {primaryColor};"
                                />
                                <span
                                    class="text-sm font-medium text-slate-600 group-hover:text-slate-800 transition"
                                >
                                    Jadikan alamat utama
                                </span>
                            </label>

                            <div
                                class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100"
                            >
                                <button
                                    type="button"
                                    onclick={cancelAddressForm}
                                    class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-2xl transition"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={addressForm.processing}
                                    class="px-6 py-2.5 text-white font-bold text-sm rounded-2xl shadow-md hover:shadow-lg transition flex items-center gap-2 disabled:opacity-70"
                                    style="background-color: {primaryColor};"
                                >
                                    {#if addressForm.processing}
                                        <i class="ti ti-loader animate-spin"
                                        ></i> Menyimpan...
                                    {:else}
                                        <i class="ti ti-device-floppy"></i>
                                        {editingAddressId
                                            ? 'Perbarui Alamat'
                                            : 'Simpan Alamat'}
                                    {/if}
                                </button>
                            </div>
                        </form>
                    </div>
                {/if}

                <!-- Address List -->
                {#if addresses.length === 0 && !showAddressForm}
                    <div
                        class="bg-white rounded-3xl border border-slate-100 shadow-sm p-12 text-center"
                    >
                        <div
                            class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                            style="background-color: {primaryColor}15;"
                        >
                            <i
                                class="ti ti-map-pin-off text-3xl"
                                style="color: {primaryColor};"
                            ></i>
                        </div>
                        <p
                            class="font-outfit font-black text-slate-700 text-lg mb-1"
                        >
                            Belum Ada Alamat
                        </p>
                        <p class="text-sm text-slate-400 mb-5">
                            Tambahkan alamat toko atau gudang Anda untuk
                            memulai.
                        </p>
                        <button
                            type="button"
                            onclick={openNewAddress}
                            class="inline-flex items-center gap-2 px-6 py-3 text-sm font-bold text-white rounded-2xl shadow-md hover:shadow-lg transition"
                            style="background-color: {primaryColor};"
                        >
                            <i class="ti ti-plus"></i> Tambah Alamat Pertama
                        </button>
                    </div>
                {:else}
                    <div class="space-y-3">
                        {#each addresses as addr (addr.id)}
                            <div
                                class="bg-white rounded-2xl border shadow-sm p-5 flex items-start gap-4 transition-all {addr.is_primary
                                    ? 'border-[color:var(--pc)] shadow-md'
                                    : 'border-slate-100 hover:border-slate-200'}"
                                style="--pc: {primaryColor}40;"
                            >
                                <!-- Icon -->
                                <div
                                    class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center"
                                    style={addr.is_primary
                                        ? `background-color: ${primaryColor}15; color: ${primaryColor};`
                                        : 'background-color: #f1f5f9; color: #94a3b8;'}
                                >
                                    <i
                                        class="ti {addr.label === 'Rumah'
                                            ? 'ti-home'
                                            : addr.label === 'Gudang'
                                              ? 'ti-building-warehouse'
                                              : addr.label === 'Kantor'
                                                ? 'ti-building-office'
                                                : 'ti-building-store'} text-lg"
                                    ></i>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div
                                        class="flex items-center gap-2 flex-wrap mb-1"
                                    >
                                        <span
                                            class="text-sm font-black text-slate-800"
                                            >{addr.label}</span
                                        >
                                        {#if addr.is_primary}
                                            <span
                                                class="px-2 py-0.5 text-[10px] font-black rounded-full text-white"
                                                style="background-color: {primaryColor};"
                                            >
                                                UTAMA
                                            </span>
                                        {/if}
                                    </div>
                                    <p
                                        class="text-sm font-medium text-slate-700"
                                    >
                                        {addr.receiver_name} · {addr.phone_number}
                                    </p>
                                    <p
                                        class="text-xs text-slate-500 mt-0.5 line-clamp-2"
                                    >
                                        {addr.full_address}
                                        {#if addr.district_name || addr.regency_name}
                                            , {[
                                                addr.district_name,
                                                addr.regency_name,
                                                addr.province_name,
                                            ]
                                                .filter(Boolean)
                                                .join(', ')}
                                        {/if}
                                        {#if addr.postal_code}
                                            {addr.postal_code}
                                        {/if}
                                    </p>
                                    {#if addr.note}
                                        <p
                                            class="text-xs text-slate-400 mt-1 italic"
                                        >
                                            {addr.note}
                                        </p>
                                    {/if}
                                </div>

                                <!-- Actions -->
                                <div class="shrink-0 flex items-center gap-2">
                                    <button
                                        type="button"
                                        onclick={() => openEditAddress(addr)}
                                        class="px-3.5 py-2 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200/80 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-2xs"
                                    >
                                        <i class="ti ti-pencil text-sm"></i>
                                        <span>Edit Alamat Toko</span>
                                    </button>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        {/if}
    </main>
</AdminLayout>
