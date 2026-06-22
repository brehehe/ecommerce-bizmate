<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import { fade } from 'svelte/transition';
    import { bulkDelete as socialMediaBulkDelete } from '@/routes/admin/master-data/social-media';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    let { socialMediaLinks = { data: [], links: [], total: 0 }, filters = {} } =
        $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 25);
    let searchTimeout: any;

    // Checkbox state
    let selectedSocialMedia = $state<string[]>([]);
    let selectAll = $derived(
        selectedSocialMedia.length === socialMediaLinks.data.length &&
            socialMediaLinks.data.length > 0,
    );

    // Modal state
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state<number | null>(null);
    let deleteModalOpen = $state(false);
    let deleteBulkModalOpen = $state(false);
    let itemToDelete = $state<any>(null);
    let submittingBulkDelete = $state(false);

    function toggleSelectAll() {
        if (selectAll) {
            selectedSocialMedia = [];
        } else {
            selectedSocialMedia = socialMediaLinks.data.map((s: any) => s.id);
        }
    }

    function toggleSelect(id: string) {
        if (selectedSocialMedia.includes(id)) {
            selectedSocialMedia = selectedSocialMedia.filter((sId) => sId !== id);
        } else {
            selectedSocialMedia = [...selectedSocialMedia, id];
        }
    }

    // Platform options with icons
    const platformOptions = [
        {
            id: 'instagram',
            name: 'Instagram',
            icon: 'ti-brand-instagram',
            color: '#E1306C',
        },
        {
            id: 'tiktok',
            name: 'TikTok',
            icon: 'ti-brand-tiktok',
            color: '#010101',
        },
        {
            id: 'facebook',
            name: 'Facebook',
            icon: 'ti-brand-facebook',
            color: '#1877F2',
        },
        {
            id: 'twitter',
            name: 'Twitter / X',
            icon: 'ti-brand-x',
            color: '#000000',
        },
        {
            id: 'youtube',
            name: 'YouTube',
            icon: 'ti-brand-youtube',
            color: '#FF0000',
        },
        {
            id: 'whatsapp',
            name: 'WhatsApp',
            icon: 'ti-brand-whatsapp',
            color: '#25D366',
        },
        {
            id: 'telegram',
            name: 'Telegram',
            icon: 'ti-brand-telegram',
            color: '#2CA5E0',
        },
        {
            id: 'linkedin',
            name: 'LinkedIn',
            icon: 'ti-brand-linkedin',
            color: '#0A66C2',
        },
        {
            id: 'shopee',
            name: 'Shopee',
            icon: 'ti-shopping-bag',
            color: '#EE4D2D',
        },
        {
            id: 'tokopedia',
            name: 'Tokopedia',
            icon: 'ti-shopping-cart',
            color: '#03AC0E',
        },
        {
            id: 'lazada',
            name: 'Lazada',
            icon: 'ti-shopping-bag',
            color: '#0F146D',
        },
        { id: 'website', name: 'Website', icon: 'ti-world', color: '#6366F1' },
        { id: 'other', name: 'Lainnya', icon: 'ti-link', color: '#64748B' },
    ];

    const platformMap = Object.fromEntries(
        platformOptions.map((p) => [p.id, p]),
    );

    function getPlatformIcon(platform: string): string {
        return platformMap[platform]?.icon || 'ti-link';
    }

    function getPlatformColor(platform: string): string {
        return platformMap[platform]?.color || '#64748B';
    }

    function getPlatformName(platform: string): string {
        return platformMap[platform]?.name || platform;
    }

    const form = useForm({
        platform: 'instagram',
        label: '',
        url: '',
        icon: 'ti-brand-instagram',
        order: 0,
        is_active: true,
    });

    // Auto-fill icon when platform changes
    $effect(() => {
        const found = platformMap[form.platform];
        if (found) {
            form.icon = found.icon;
            if (!isEditing) {
                form.label = found.name;
            }
        }
    });

    function updateQuery() {
        router.get(
            '/admin/master-data/social-media',
            { search: searchQuery, perPage: perPage },
            { preserveState: true, replace: true },
        );
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            updateQuery();
        }, 500);
    }

    function handlePerPageChange() {
        updateQuery();
    }

    function openAddModal() {
        isEditing = false;
        editId = null;
        form.reset();
        form.platform = 'instagram';
        form.icon = 'ti-brand-instagram';
        form.label = 'Instagram';
        form.is_active = true;
        form.order = (socialMediaLinks.total || 0) + 1;
        form.clearErrors();
        isModalOpen = true;
    }

    function openEditModal(item: any) {
        isEditing = true;
        editId = item.id;
        form.reset();
        form.clearErrors();
        form.platform = item.platform;
        form.label = item.label;
        form.url = item.url;
        form.icon = item.icon;
        form.order = item.order;
        form.is_active = item.is_active ?? true;
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        form.reset();
        form.clearErrors();
    }

    function saveSocialMedia(e: SubmitEvent) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/master-data/social-media/${editId}`, {
                onSuccess: () => {
                    showToast('Media sosial berhasil diperbarui', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/social-media', {
                onSuccess: () => {
                    showToast('Media sosial berhasil ditambahkan', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        }
    }

    function confirmDelete(item: any) {
        itemToDelete = item;
        deleteModalOpen = true;
    }

    function executeBulkDelete() {
        if (selectedSocialMedia.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            socialMediaBulkDelete.url(),
            {
                ids: selectedSocialMedia,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedSocialMedia = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first = Object.values(err)[0] || 'Gagal menghapus media sosial terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                }
            }
        );
    }

    function executeDelete() {
        if (!itemToDelete) return;
        router.delete(`/admin/master-data/social-media/${itemToDelete.id}`, {
            onSuccess: () => {
                showToast('Media sosial berhasil dihapus', 'success');
                deleteModalOpen = false;
                itemToDelete = null;
            },
            onError: (err: any) => {
                showToast(err?.error || 'Gagal menghapus data', 'error');
                deleteModalOpen = false;
            },
        });
    }

    function toggleStatus(item: any) {
        router.post(
            `/admin/master-data/social-media/${item.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        `Status ${item.label} berhasil diubah`,
                        'success',
                    );
                },
                onError: (err: any) => {
                    showToast(err?.error || 'Gagal mengubah status', 'error');
                },
            },
        );
    }

    // Drag-and-drop reorder state
    let draggingId = $state<number | null>(null);
    let dragOverId = $state<number | null>(null);

    function handleDragStart(e: DragEvent, id: number) {
        draggingId = id;
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
        }
    }

    function handleDragOver(e: DragEvent, id: number) {
        e.preventDefault();
        dragOverId = id;
    }

    function handleDrop(e: DragEvent, targetId: number) {
        e.preventDefault();
        if (draggingId === null || draggingId === targetId) {
            draggingId = null;
            dragOverId = null;
            return;
        }

        const items = [...socialMediaLinks.data];
        const fromIdx = items.findIndex((i: any) => i.id === draggingId);
        const toIdx = items.findIndex((i: any) => i.id === targetId);

        if (fromIdx === -1 || toIdx === -1) return;

        const [moved] = items.splice(fromIdx, 1);
        items.splice(toIdx, 0, moved);

        const reorderPayload = items.map((item: any, index: number) => ({
            id: item.id,
            order: index + 1,
        }));

        draggingId = null;
        dragOverId = null;

        router.post(
            '/admin/master-data/social-media/reorder',
            { items: reorderPayload },
            {
                preserveScroll: true,
                onSuccess: () =>
                    showToast('Urutan berhasil disimpan', 'success'),
                onError: () => showToast('Gagal menyimpan urutan', 'error'),
            },
        );
    }

    function handleDragEnd() {
        draggingId = null;
        dragOverId = null;
    }

    // Helper: build public URL/handle display
    function getDisplayUrl(item: any): string {
        if (!item.url) return '';
        if (item.platform === 'whatsapp') {
            return `wa.me/${item.url.replace(/\D/g, '')}`;
        }
        if (item.platform === 'instagram') {
            return `@${item.url.replace(/^@/, '')}`;
        }
        if (item.platform === 'tiktok') {
            return `@${item.url.replace(/^@/, '')}`;
        }
        return item.url;
    }

    // Helper: build clickable URL
    function getClickableUrl(item: any): string {
        if (!item.url) return '#';
        const url = item.url.trim();
        if (url.startsWith('http://') || url.startsWith('https://')) {
            return url;
        }
        if (item.platform === 'whatsapp') {
            return `https://wa.me/${url.replace(/\D/g, '')}`;
        }
        if (item.platform === 'instagram') {
            return `https://instagram.com/${url.replace(/^@/, '')}`;
        }
        if (item.platform === 'tiktok') {
            return `https://tiktok.com/@${url.replace(/^@/, '')}`;
        }
        if (item.platform === 'facebook') {
            return `https://facebook.com/${url}`;
        }
        if (item.platform === 'twitter') {
            return `https://x.com/${url.replace(/^@/, '')}`;
        }
        if (item.platform === 'youtube') {
            return `https://youtube.com/@${url.replace(/^@/, '')}`;
        }
        if (item.platform === 'telegram') {
            return `https://t.me/${url.replace(/^@/, '')}`;
        }
        if (item.platform === 'linkedin') {
            return `https://linkedin.com/in/${url}`;
        }
        return url;
    }
</script>

<svelte:head>
    <title>Master Data: Media Sosial</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
            <!-- Page Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h1 class="font-outfit font-black text-2xl text-slate-800">
                        Master Media Sosial
                    </h1>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Kelola akun media sosial toko yang tampil di storefront
                    </p>
                </div>
                <button
                    onclick={openAddModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shrink-0 font-outfit uppercase tracking-wider"
                    style="background-color: {primaryColor}; box-shadow: 0 8px 24px {primaryColor}33;"
                >
                    <i class="ti ti-plus text-base"></i>
                    <span>Tambah Akun Sosmed</span>
                </button>
            </div>

            <!-- Info Banner -->
            <div
                class="bg-blue-50 border border-blue-100 rounded-2xl p-4 flex gap-3 items-start"
            >
                <div
                    class="w-8 h-8 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5"
                >
                    <i class="ti ti-info-circle text-base"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-blue-800">
                        Tips Penggunaan
                    </p>
                    <p class="text-xs text-blue-600 mt-0.5 leading-relaxed">
                        Akun sosmed yang aktif akan otomatis tampil di <strong
                            >footer storefront</strong
                        >. Untuk Instagram/TikTok, isi kolom URL dengan username
                        (contoh:
                        <code class="bg-blue-100 px-1 rounded"
                            >bizmate.official</code
                        >). Untuk WhatsApp, isi nomor lengkap dengan kode negara
                        (contoh:
                        <code class="bg-blue-100 px-1 rounded"
                            >6281234567890</code
                        >). Anda bisa <strong>drag & drop</strong> baris untuk mengatur
                        urutan tampilan.
                    </p>
                </div>
            </div>

            <!-- Main Table Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden"
            >
                <!-- Header, PerPage & Search -->
                <div
                    class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20"
                >
                    <div class="shrink-0 w-full sm:w-32">
                        <Select
                            bind:value={perPage}
                            options={[
                                { id: 10, name: '10 Data' },
                                { id: 25, name: '25 Data' },
                                { id: 50, name: '50 Data' },
                            ]}
                            onchange={handlePerPageChange}
                        />
                    </div>
                    <div class="flex-grow sm:max-w-md w-full sm:ml-auto">
                        <Input
                            type="text"
                            bind:value={searchQuery}
                            oninput={handleSearch}
                            placeholder="Cari platform, label, atau URL..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                {#if selectedSocialMedia.length > 0}
                    <div
                        transition:fade={{ duration: 150 }}
                        class="px-6 py-4 bg-brand-blueLight/30 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-555 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-soft font-outfit uppercase tracking-wider flex items-center gap-1.5">
                                <i class="ti ti-checkbox text-brand-blueRoyal text-sm"></i>
                                {selectedSocialMedia.length} Sosmed Terpilih
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick={() => {
                                    selectedSocialMedia = [];
                                }}
                                class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-555 font-bold rounded-xl text-xs transition uppercase tracking-wider font-outfit cursor-pointer"
                            >
                                Batal Pilihan
                            </button>
                            <button
                                onclick={() => (deleteBulkModalOpen = true)}
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-red-500/20 uppercase tracking-wider font-outfit flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="ti ti-trash"></i>
                                Hapus Terpilih
                            </button>
                        </div>
                    </div>
                {/if}

                {#if socialMediaLinks.data.length === 0}
                    <div
                        class="py-16 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i
                            class="ti ti-share text-5xl block mb-3 text-slate-200"
                        ></i>
                        <p class="text-slate-500 font-black">
                            Belum ada akun media sosial.
                        </p>
                        <p class="text-xs mt-1 text-slate-400 font-medium">
                            Klik "Tambah Akun Sosmed" untuk memulai.
                        </p>
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                                >
                                    <th class="py-4 px-6 w-12 text-center">
                                        <input
                                            type="checkbox"
                                            checked={selectAll}
                                            onchange={toggleSelectAll}
                                            class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                        />
                                    </th>
                                    <th class="py-4 px-4 w-10 text-center"
                                        >Urut</th
                                    >
                                    <th class="py-4 px-6">Platform</th>
                                    <th class="py-4 px-6">Label</th>
                                    <th class="py-4 px-6">URL / Handle</th>
                                    <th class="py-4 px-6">Status</th>
                                    <th class="py-4 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each socialMediaLinks.data as item (item.id)}
                                    {@const isActive = item.is_active ?? true}
                                    {@const isDragging = draggingId === item.id}
                                    {@const isDragOver = dragOverId === item.id}

                                    <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100 cursor-grab active:cursor-grabbing {isDragging
                                            ? 'opacity-40 bg-slate-50'
                                            : ''} {isDragOver
                                            ? 'bg-blue-50/40 border-t-2 border-t-blue-200'
                                            : ''} {selectedSocialMedia.includes(item.id)
                                            ? 'bg-brand-blueRoyal/5'
                                            : ''}"
                                        draggable={true}
                                        ondragstart={(e) =>
                                            handleDragStart(e, item.id)}
                                        ondragover={(e) =>
                                            handleDragOver(e, item.id)}
                                        ondrop={(e) => handleDrop(e, item.id)}
                                        ondragend={handleDragEnd}
                                    >
                                        <td class="py-5 px-6 text-center" onclick={(e) => e.stopPropagation()}>
                                            <input
                                                type="checkbox"
                                                checked={selectedSocialMedia.includes(item.id)}
                                                onchange={() => toggleSelect(item.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-5 px-4 text-center">
                                            <div
                                                class="flex items-center justify-center"
                                            >
                                                <i
                                                    class="ti ti-grip-vertical text-slate-300 text-lg"
                                                ></i>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0"
                                                    style="background-color: {getPlatformColor(
                                                        item.platform,
                                                    )};"
                                                >
                                                    <i
                                                        class="ti {item.icon ||
                                                            getPlatformIcon(
                                                                item.platform,
                                                            )} text-lg"
                                                    ></i>
                                                </div>
                                                <span
                                                    class="font-bold text-slate-800 text-sm"
                                                >
                                                    {getPlatformName(
                                                        item.platform,
                                                    )}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6">
                                            <span
                                                class="font-semibold text-slate-700"
                                                >{item.label}</span
                                            >
                                        </td>
                                        <td class="py-5 px-6">
                                            <a
                                                href={getClickableUrl(item)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-xs font-mono text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1 max-w-[220px] truncate"
                                                title={item.url}
                                            >
                                                <i
                                                    class="ti ti-external-link text-xs shrink-0"
                                                ></i>
                                                {getDisplayUrl(item)}
                                            </a>
                                        </td>
                                        <td class="py-5 px-6">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {isActive
                                                    ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/50'
                                                    : 'bg-slate-50 text-slate-500 border border-slate-200/50'}"
                                            >
                                                {isActive
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <button
                                                    aria-label="Edit"
                                                    onclick={() =>
                                                        openEditModal(item)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-blue-50 hover:text-blue-600 text-slate-500 flex items-center justify-center transition"
                                                    title="Ubah Data"
                                                >
                                                    <i
                                                        class="ti ti-pencil text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        toggleStatus(item)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 {isActive
                                                        ? 'hover:bg-amber-50 hover:text-amber-600 text-slate-500'
                                                        : 'hover:bg-emerald-50 hover:text-emerald-600 text-slate-400'} flex items-center justify-center transition"
                                                    title={isActive
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'}
                                                >
                                                    <i
                                                        class="ti {isActive
                                                            ? 'ti-eye-off'
                                                            : 'ti-eye'} text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        confirmDelete(item)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition"
                                                    title="Hapus"
                                                >
                                                    <i
                                                        class="ti ti-trash text-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}

                <Pagination paginator={socialMediaLinks} />
            </div>
        </main>
    </div>
</AdminLayout>

<!-- Add / Edit Modal -->
{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            onclick={closeModal}
        ></div>
        <div
            class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-lg relative z-10 transform transition-all duration-300 overflow-hidden animate-in fade-in zoom-in duration-200"
        >
            <!-- Modal Header -->
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center text-white"
                        style="background-color: {getPlatformColor(
                            form.platform,
                        )};"
                    >
                        <i
                            class="ti {form.icon ||
                                getPlatformIcon(form.platform)} text-lg"
                        ></i>
                    </div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        {isEditing ? 'Ubah Akun Sosmed' : 'Tambah Akun Sosmed'}
                    </h3>
                </div>
                <button
                    aria-label="Tutup"
                    type="button"
                    onclick={closeModal}
                    class="p-1 text-slate-400 hover:text-slate-700 transition"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <form onsubmit={saveSocialMedia} class="p-6 space-y-4">
                <!-- Platform Selector -->
                <div>
                    <span
                        class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider"
                    >
                        Platform <span class="text-red-500">*</span>
                    </span>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        {#each platformOptions as opt}
                            <button
                                type="button"
                                onclick={() => (form.platform = opt.id)}
                                class="flex flex-col items-center gap-1.5 p-2.5 rounded-xl border-2 transition text-xs font-bold {form.platform ===
                                opt.id
                                    ? 'border-transparent text-white shadow-lg scale-105'
                                    : 'border-slate-100 text-slate-500 hover:border-slate-200 bg-slate-50'}"
                                style={form.platform === opt.id
                                    ? `background-color: ${opt.color}; border-color: ${opt.color};`
                                    : ''}
                            >
                                <i class="ti {opt.icon} text-xl"></i>
                                <span
                                    class="text-[10px] leading-tight text-center"
                                    >{opt.name}</span
                                >
                            </button>
                        {/each}
                    </div>
                    {#if form.errors.platform}
                        <p class="text-xs text-red-500 mt-1">
                            {form.errors.platform}
                        </p>
                    {/if}
                </div>

                <!-- Label -->
                <Input
                    id="input-social-label"
                    bind:value={form.label}
                    label="Label / Nama Tampil"
                    placeholder="Contoh: Instagram Toko Kami"
                    required={true}
                    error={form.errors.label}
                />

                <!-- URL / Handle -->
                <div>
                    <label
                        for="input-social-url"
                        class="block text-xs font-bold text-slate-600 mb-2 uppercase tracking-wider"
                    >
                        URL / Username / Nomor <span class="text-red-500"
                            >*</span
                        >
                    </label>
                    {#if form.platform === 'instagram' || form.platform === 'tiktok' || form.platform === 'twitter' || form.platform === 'youtube'}
                        <div class="flex">
                            <span
                                class="inline-flex items-center px-3 text-xs font-bold text-slate-500 bg-slate-100 border border-r-0 border-slate-200 rounded-l-xl"
                                >@</span
                            >
                            <input
                                id="input-social-url"
                                bind:value={form.url}
                                type="text"
                                placeholder="username_toko"
                                class="flex-1 px-3 py-2.5 text-sm border border-slate-200 rounded-r-xl focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition {form
                                    .errors.url
                                    ? 'border-red-400'
                                    : ''}"
                            />
                        </div>
                    {:else if form.platform === 'whatsapp'}
                        <div class="flex">
                            <span
                                class="inline-flex items-center px-3 text-xs font-bold text-slate-500 bg-slate-100 border border-r-0 border-slate-200 rounded-l-xl"
                                >+</span
                            >
                            <input
                                id="input-social-url"
                                bind:value={form.url}
                                type="text"
                                placeholder="6281234567890"
                                class="flex-1 px-3 py-2.5 text-sm border border-slate-200 rounded-r-xl focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition {form
                                    .errors.url
                                    ? 'border-red-400'
                                    : ''}"
                            />
                        </div>
                    {:else}
                        <input
                            id="input-social-url"
                            bind:value={form.url}
                            type="text"
                            placeholder="https://..."
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition {form
                                .errors.url
                                ? 'border-red-400'
                                : ''}"
                        />
                    {/if}
                    {#if form.errors.url}
                        <p class="text-xs text-red-500 mt-1">
                            {form.errors.url}
                        </p>
                    {:else}
                        <p class="text-[10px] text-slate-400 mt-1">
                            {#if form.platform === 'instagram' || form.platform === 'tiktok'}
                                Masukkan username tanpa @ (contoh: <code
                                    >bizmate.official</code
                                >)
                            {:else if form.platform === 'whatsapp'}
                                Nomor lengkap dengan kode negara, tanpa +
                                (contoh: <code>6281234567890</code>)
                            {:else if form.platform === 'twitter'}
                                Username Twitter/X tanpa @ (contoh: <code
                                    >bizmate_id</code
                                >)
                            {:else if form.platform === 'youtube'}
                                Username channel YouTube (contoh: <code
                                    >BizmateOfficial</code
                                >)
                            {:else if form.platform === 'telegram'}
                                Username Telegram tanpa @ (contoh: <code
                                    >bizmatechannel</code
                                >)
                            {:else}
                                URL lengkap (contoh: <code
                                    >https://shopee.co.id/bizmate</code
                                >)
                            {/if}
                        </p>
                    {/if}
                </div>

                <!-- Order -->
                <Input
                    id="input-social-order"
                    bind:value={form.order}
                    type="number"
                    label="Urutan Tampil"
                    placeholder="0"
                    error={form.errors.order}
                />

                <!-- Status Toggle -->
                <div class="pt-1">
                    <Toggle
                        bind:checked={form.is_active}
                        label="Tampilkan di Storefront"
                        description="Jika aktif, akun ini akan muncul di footer halaman storefront toko."
                        icon="ti-eye"
                    />
                </div>

                <!-- Buttons -->
                <div
                    class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 mt-4"
                >
                    <button
                        type="button"
                        onclick={closeModal}
                        class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition duration-200 uppercase tracking-wider font-outfit"
                        >Batal</button
                    >
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-5 py-3 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg uppercase tracking-wider font-outfit disabled:opacity-70 flex items-center gap-2"
                        style="background-color: {primaryColor};"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin"></i>
                        {/if}
                        {isEditing ? 'Simpan Perubahan' : 'Tambah Sosmed'}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- Delete Confirmation Modal -->
{#if deleteModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (deleteModalOpen = false)}
        ></div>

        <div
            class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200"
        >
            <div
                class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto"
            >
                <i class="ti ti-alert-triangle"></i>
            </div>
            <h4
                class="font-outfit font-black text-xl text-center text-slate-800 mb-2"
            >
                Hapus Akun Sosmed?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Akun <strong>{itemToDelete?.label}</strong> ({getPlatformName(
                    itemToDelete?.platform,
                )}) akan dihapus secara permanen.
            </p>
            <div class="flex items-center gap-3">
                <button
                    onclick={() => (deleteModalOpen = false)}
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition"
                >
                    Batal
                </button>
                <button
                    onclick={executeDelete}
                    class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition"
                >
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Bulk Delete Confirmation Modal -->
{#if deleteBulkModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (deleteBulkModalOpen = false)}
            onkeypress={() => (deleteBulkModalOpen = false)}
            role="button"
            tabindex="0"
        ></div>

        <div
            class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200"
        >
            <div
                class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto"
            >
                <i class="ti ti-alert-triangle"></i>
            </div>
            <h4
                class="font-outfit font-black text-xl text-center text-slate-800 mb-2"
            >
                Hapus {selectedSocialMedia.length} Sosmed Terpilih?
            </h4>
            <p class="text-sm text-center text-slate-555 font-medium mb-8">
                Apakah Anda yakin ingin menghapus <strong>{selectedSocialMedia.length} akun media sosial</strong> yang terpilih secara permanen dari sistem? Tindakan ini tidak dapat dibatalkan.
            </p>
            <div class="flex items-center gap-3">
                <button
                    onclick={() => (deleteBulkModalOpen = false)}
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    onclick={executeBulkDelete}
                    disabled={submittingBulkDelete}
                    class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition cursor-pointer disabled:opacity-50"
                >
                    {submittingBulkDelete ? 'Memproses...' : 'Ya, Hapus Semua'}
                </button>
            </div>
        </div>
    </div>
{/if}
