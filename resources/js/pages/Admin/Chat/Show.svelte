<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { usePage, router, Link } from '@inertiajs/svelte';
    import { onMount, onDestroy } from 'svelte';
    import { showToast } from '@/utils/toast';

    let {
        chat = {
            id: 0,
            subject: '',
            status: '',
            product: null,
            user: { id: 0, name: '', email: '' },
        },
        initialMessages = [],
        chats = { data: [], links: [] },
        totalUnread = 0,
    } = $props();

    const page = usePage();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    const storeName = $derived(
        (page.props as any).settings?.store_name || 'Bizmate',
    );

    // svelte-ignore state_referenced_locally
    let messages = $state<any[]>(initialMessages);
    let replyInput = $state('');
    let pollingInterval: any = null;

    let attachedImage = $state<File | null>(null);
    let attachedImageUrl = $state<string | null>(null);
    let chatPreviewUrl = $state<string | null>(null);

    let searchQuery = $state('');
    let showSidebar = $state(true);

    onMount(() => {
        const urlParams = new URLSearchParams(window.location.search);
        searchQuery = urlParams.get('search') || '';
        scrollToBottom();
        startPolling();
    });

    onDestroy(() => {
        stopPolling();
    });

    let lastChatId = $state<string | number | null>(null);

    // Sync messages if chat ID changes (navigating between threads on desktop)
    $effect(() => {
        if (chat.id && chat.id !== lastChatId) {
            lastChatId = chat.id;
            messages = initialMessages;
            attachedImage = null;
            attachedImageUrl = null;
            setTimeout(scrollToBottom, 50);
            startPolling();
        }
    });

    function scrollToBottom() {
        const el = document.querySelector('.admin-chat-messages');
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }

    function startPolling() {
        stopPolling();
        pollingInterval = setInterval(async () => {
            if (!chat.id) return;
            const lastMsgId =
                messages.length > 0 ? messages[messages.length - 1].id : 0;
            try {
                const response = await fetch(
                    `/admin/chats/${chat.id}/poll?after_id=${lastMsgId}`,
                    {
                        headers: { Accept: 'application/json' },
                    },
                );
                if (response.ok) {
                    const data = await response.json();
                    const newMsgs = Array.isArray(data)
                        ? data
                        : data.messages || [];
                    const readIds = Array.isArray(data)
                        ? []
                        : data.read_ids || [];

                    if (newMsgs.length > 0) {
                        messages = [...messages, ...newMsgs];
                        setTimeout(scrollToBottom, 50);
                    }

                    if (readIds.length > 0) {
                        messages = messages.map((m: any) => {
                            if (readIds.includes(m.id) && !m.is_read) {
                                return { ...m, is_read: true };
                            }
                            return m;
                        });
                    }

                    // Reload Inertia props to update thread list & total unread count on sidebar
                    router.reload({
                        only: ['chats', 'totalUnread', 'adminChatUnreadCount'],
                        preserveScroll: true,
                    });
                }
            } catch (err) {
                console.error('Error polling messages:', err);
            }
        }, 2000);
    }

    function stopPolling() {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }

    async function sendReply() {
        const text = replyInput.trim();
        if (!text && !attachedImage) return;

        try {
            const formData = new FormData();
            if (text) formData.append('body', text);
            if (attachedImage) {
                formData.append('image', attachedImage);
            }

            replyInput = '';
            attachedImage = null;
            attachedImageUrl = null;

            const response = await fetch(`/admin/chats/${chat.id}/reply`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    Accept: 'application/json',
                },
                body: formData,
            });

            if (response.ok) {
                const newMsg = await response.json();
                if (!messages.some((m: any) => m.id === newMsg.id)) {
                    messages = [...messages, newMsg];
                    setTimeout(scrollToBottom, 50);
                }
            }
        } catch (err) {
            console.error('Error sending reply:', err);
        }
    }

    function triggerImageUpload() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = (e: any) => {
            const file = e.target.files?.[0];
            if (file) {
                attachedImage = file;
                attachedImageUrl = URL.createObjectURL(file);
            }
        };
        input.click();
    }

    function handleSearch(e: Event) {
        e.preventDefault();
        router.get(
            `/admin/chats/${chat.id}`,
            { search: searchQuery },
            { preserveState: true },
        );
    }

    function resetSearch() {
        searchQuery = '';
        router.get(`/admin/chats/${chat.id}`, {}, { preserveState: true });
    }

    function formatImagePath(path: any): string {
        if (!path || typeof path !== 'string') return '/noimage/image.png';
        if (path.startsWith('http://') || path.startsWith('https://'))
            return path;
        return path.startsWith('/') ? path : '/' + path;
    }

    let deleteModalOpen = $state(false);
    let deleteType = $state<'chat' | 'message'>('chat');
    let itemToDeleteId = $state<number | null>(null);

    function confirmDeleteChat() {
        deleteType = 'chat';
        itemToDeleteId = chat.id;
        deleteModalOpen = true;
    }

    function confirmDeleteMessage(messageId: number) {
        deleteType = 'message';
        itemToDeleteId = messageId;
        deleteModalOpen = true;
    }

    async function executeDelete() {
        deleteModalOpen = false;
        if (!itemToDeleteId) return;

        if (deleteType === 'chat') {
            router.delete(`/admin/chats/${itemToDeleteId}`, {
                onSuccess: () => {
                    showToast('Percakapan berhasil dihapus', 'success');
                },
                onError: () => {
                    showToast('Gagal menghapus percakapan', 'error');
                },
            });
        } else {
            try {
                const response = await fetch(
                    `/admin/chats/${chat.id}/messages/${itemToDeleteId}`,
                    {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN':
                                (
                                    document.querySelector(
                                        'meta[name="csrf-token"]',
                                    ) as HTMLMetaElement
                                )?.content || '',
                            Accept: 'application/json',
                        },
                    },
                );
                if (response.ok) {
                    messages = messages.filter(
                        (m: any) => m.id !== itemToDeleteId,
                    );
                    showToast('Pesan berhasil dihapus', 'success');
                } else {
                    showToast('Gagal menghapus pesan', 'error');
                }
            } catch (err) {
                console.error('Error deleting message:', err);
                showToast('Gagal menghapus pesan', 'error');
            }
        }
        itemToDeleteId = null;
    }

    function formatAvatarPath(path: any): string {
        if (!path || typeof path !== 'string') return '';
        if (path.startsWith('http://') || path.startsWith('https://'))
            return path;
        if (path.startsWith('/storage/')) return path;
        if (path.startsWith('storage/')) return '/' + path;
        return path.startsWith('/') ? '/storage' + path : '/storage/' + path;
    }

    function parseTransactionCard(body: string) {
        try {
            return JSON.parse(body.replace('[TRANSACTION_CARD]', ''));
        } catch (e) {
            return null;
        }
    }

    function getStatusColor(status: string) {
        const colors: Record<string, string> = {
            belum_bayar: '#f59e0b',
            menunggu: '#3b82f6',
            diproses: '#8b5cf6',
            dikemas: '#06b6d4',
            out_for_pickup: '#d97706',
            dikirim: '#f97316',
            selesai: '#22c55e',
            batal: '#ef4444',
        };
        return colors[status] || '#64748b';
    }

    function getStatusLabel(status: string) {
        const labels: Record<string, string> = {
            belum_bayar: 'Belum Bayar',
            menunggu: 'Menunggu Konfirmasi',
            diproses: 'Diproses',
            dikemas: 'Dikemas',
            out_for_pickup: 'Pick Up',
            dikirim: 'Dikirim',
            selesai: 'Selesai',
            batal: 'Batal',
        };
        return labels[status] || status;
    }

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }
</script>

<svelte:head>
    <title>Chat dengan {chat.user.name} - {storeName}</title>
</svelte:head>

<AdminLayout>
    <div
        class="flex-grow p-4 sm:p-6 flex flex-col h-[calc(100vh-5rem)] min-h-0 overflow-hidden w-full max-w-[1600px] mx-auto"
    >
        <!-- Header -->
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-4 shrink-0"
        >
            <div>
                <h1
                    class="font-outfit font-black text-xl text-slate-800 flex items-center gap-2"
                >
                    <i class="ti ti-message-dots" style="color: {primaryColor};"
                    ></i>
                    Pusat Obrolan (Inbox)
                </h1>
                <p class="text-[10px] text-slate-400 font-semibold mt-0.5">
                    Kelola dan balas pertanyaan pelanggan secara realtime.
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black bg-orange-50 text-orange-600 rounded-xl"
                >
                    <i class="ti ti-mail-unread text-xs"></i>
                    {totalUnread} Obrolan Belum Dibaca
                </span>
            </div>
        </div>

        <!-- Unified Workspace (Split Screen Layout) -->
        <div
            class="flex-grow flex-1 min-h-0 flex bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm w-full"
        >
            <!-- LEFT PANEL: Conversation List -->
            <div
                class="w-full md:w-85 border-r border-slate-200 flex flex-col shrink-0 min-h-0 {chat.id
                    ? 'hidden md:flex'
                    : 'flex'}"
            >
                <!-- Search bar -->
                <div
                    class="px-4 py-3 border-b border-slate-200 bg-white shrink-0 h-[65px] flex items-center w-full"
                >
                    <form onsubmit={handleSearch} class="relative w-full">
                        <i
                            class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                        ></i>
                        <input
                            type="text"
                            bind:value={searchQuery}
                            placeholder="Cari nama / email..."
                            class="w-full pl-10 pr-12 py-2 text-xs bg-slate-50 border border-slate-200 rounded-full focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                        />
                        {#if searchQuery}
                            <button
                                type="button"
                                onclick={resetSearch}
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold"
                            >
                                Batal
                            </button>
                        {/if}
                    </form>
                </div>

                <!-- Scrollable thread list -->
                <div
                    class="flex-grow overflow-y-auto p-2.5 space-y-1 bg-white min-h-0 custom-scrollbar"
                >
                    {#if chats.data.length === 0}
                        <div class="py-16 text-center text-slate-400 px-4">
                            <i
                                class="ti ti-message-off text-3xl mb-2 text-slate-300 block"
                            ></i>
                            <span class="text-xs font-bold"
                                >Tidak ada percakapan</span
                            >
                        </div>
                    {:else}
                        {#each chats.data as c (c.id)}
                            <Link
                                href={`/admin/chats/${c.id}`}
                                class="w-full text-left p-3 flex items-start gap-3 hover:bg-slate-50 rounded-2xl transition duration-150 relative block border-b-0 cursor-pointer
                                       {chat.id === c.id
                                    ? 'bg-slate-50/80 font-semibold'
                                    : ''}"
                            >
                                {#if chat.id === c.id}
                                    <div
                                        class="absolute left-0 top-3 bottom-3 w-1 rounded-r-md"
                                        style="background-color: {primaryColor};"
                                    ></div>
                                {/if}
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-black shrink-0 shadow-sm overflow-hidden"
                                    style={!c.user.avatar
                                        ? `background-color: ${c.unread_count > 0 ? secondaryColor : primaryColor};`
                                        : ''}
                                >
                                    {#if c.user.avatar}
                                        <img
                                            src={formatAvatarPath(
                                                c.user.avatar,
                                            )}
                                            alt={c.user.name}
                                            class="w-full h-full object-cover"
                                        />
                                    {:else}
                                        {c.user.name.charAt(0).toUpperCase()}
                                    {/if}
                                </div>

                                <div class="flex-grow min-w-0">
                                    <div
                                        class="flex items-center justify-between gap-1 mb-1"
                                    >
                                        <p
                                            class="font-outfit font-black text-xs text-slate-800 truncate"
                                        >
                                            {c.user.name}
                                        </p>
                                        {#if c.last_message_at}
                                            <span
                                                class="text-[9px] font-medium text-slate-400 whitespace-nowrap"
                                            >
                                                {new Date(
                                                    c.last_message_at,
                                                ).toLocaleTimeString('id-ID', {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </span>
                                        {/if}
                                    </div>
                                    <p
                                        class="text-xs text-slate-500 truncate leading-normal"
                                    >
                                        {#if c.last_message}
                                            {#if c.last_message.sender_type === 'admin'}
                                                Anda:
                                            {/if}
                                            {#if c.last_message.attachment_type === 'image'}
                                                📷 Gambar
                                            {:else if c.last_message.attachment_type === 'product'}
                                                📦 Produk
                                            {:else if c.last_message.body && c.last_message.body.startsWith('[TRANSACTION_CARD]')}
                                                📄 Invoice Pesanan
                                            {:else}
                                                {c.last_message.body || ''}
                                            {/if}
                                        {:else}
                                            Belum ada pesan
                                        {/if}
                                    </p>
                                </div>

                                <!-- Unread counts badge / dot -->
                                {#if c.unread_count > 0}
                                    {#if c.unread_count === 1}
                                        <span
                                            class="absolute right-5 bottom-5 w-2.5 h-2.5 rounded-full shadow-sm animate-pulse"
                                            style="background-color: {secondaryColor};"
                                        ></span>
                                    {:else}
                                        <span
                                            class="absolute right-4 bottom-4 text-[9px] font-black text-white w-5 h-5 rounded-full flex items-center justify-center shadow-sm"
                                            style="background-color: {secondaryColor};"
                                        >
                                            {c.unread_count}
                                        </span>
                                    {/if}
                                {/if}
                            </Link>
                        {/each}
                    {/if}
                </div>

                <!-- Paginated navigation at the bottom of thread list -->
                {#if chats.links && chats.links.length > 3}
                    <div
                        class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-center gap-1 shrink-0"
                    >
                        {#each chats.links.filter((l: any) => l.label.includes('Prev') || l.label.includes('Next') || l.active) as link}
                            {#if link.url}
                                <Link
                                    href={link.url}
                                    class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold transition duration-150 active:scale-95 cursor-pointer
                                           {link.active
                                        ? 'text-white'
                                        : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'}"
                                    style={link.active
                                        ? `background-color: ${primaryColor};`
                                        : ''}
                                >
                                    {@html link.label.replace(
                                        'Previous',
                                        'Prev',
                                    )}
                                </Link>
                            {/if}
                        {/each}
                    </div>
                {/if}
            </div>

            <!-- RIGHT PANEL: Active conversation workspace -->
            <div
                class="flex-grow flex flex-col bg-slate-50 min-h-0 w-full md:w-auto min-w-0 {chat.id
                    ? 'flex'
                    : 'hidden md:flex'}"
            >
                <!-- Top Toolbar Header inside chat thread -->
                <div
                    class="bg-white flex items-center justify-between border-b border-slate-200 px-4 py-3 shrink-0 h-[65px] w-full"
                >
                    <div class="flex items-center gap-3">
                        <button
                            onclick={() => router.visit('/admin/chats')}
                            class="md:hidden w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-50 border border-slate-200 transition shrink-0 cursor-pointer"
                            aria-label="Kembali"
                        >
                            <i class="ti ti-arrow-left text-lg text-slate-700"
                            ></i>
                        </button>
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-black shrink-0 shadow-sm overflow-hidden"
                            style={!chat.user.avatar
                                ? `background-color: ${primaryColor};`
                                : ''}
                        >
                            {#if chat.user.avatar}
                                <img
                                    src={formatAvatarPath(chat.user.avatar)}
                                    alt={chat.user.name}
                                    class="w-full h-full object-cover"
                                />
                            {:else}
                                {chat.user.name.charAt(0).toUpperCase()}
                            {/if}
                        </div>
                        <div>
                            <h2
                                class="font-outfit font-black text-xs sm:text-sm text-slate-800 flex items-center gap-2"
                            >
                                {chat.user.name}
                            </h2>
                            <p class="text-[10px] text-slate-400 font-semibold">
                                {chat.user.email}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        {#if chat.status === 'open'}
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[9px] font-black bg-emerald-50 text-emerald-600 rounded-full leading-none"
                            >
                                <span
                                    class="w-1.5 h-1.5 bg-emerald-500 rounded-full"
                                ></span>
                                Aktif
                            </span>
                        {:else}
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[9px] font-black bg-slate-100 text-slate-500 rounded-full leading-none"
                            >
                                <span
                                    class="w-1.5 h-1.5 bg-slate-400 rounded-full"
                                ></span>
                                Closed
                            </span>
                        {/if}

                        <!-- Delete chat thread button -->
                        <button
                            onclick={confirmDeleteChat}
                            class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-rose-50 border border-slate-200 text-slate-400 hover:text-rose-600 transition duration-150 cursor-pointer shrink-0"
                            title="Hapus Percakapan"
                            aria-label="Hapus Percakapan"
                        >
                            <i class="ti ti-trash text-lg"></i>
                        </button>

                        <button
                            onclick={() => (showSidebar = !showSidebar)}
                            class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-50 border border-slate-200 transition shrink-0 cursor-pointer text-slate-500 hover:text-slate-700"
                            title={showSidebar
                                ? 'Sembunyikan Detail'
                                : 'Tampilkan Detail'}
                            aria-label="Toggle Sidebar"
                        >
                            <i
                                class="ti {showSidebar
                                    ? 'ti-layout-sidebar-right-collapse'
                                    : 'ti-layout-sidebar-right-expand'} text-lg"
                            ></i>
                        </button>
                    </div>
                </div>

                <!-- Chat layout: Conversation on left, sidebars on right -->
                <div
                    class="flex-grow flex flex-col lg:flex-row min-h-0 overflow-hidden"
                >
                    <!-- Middle pane: Messages and Input box -->
                    <div class="flex-grow flex flex-col min-h-0 bg-white">
                        <!-- Message logs history -->
                        <div
                            class="admin-chat-messages flex-grow overflow-y-auto overflow-x-hidden p-5 bg-slate-50/40 space-y-4 min-h-0 custom-scrollbar"
                        >
                            {#each messages as msg (msg.id)}
                                <div
                                    class="group relative flex flex-col {msg.sender_type ===
                                    'admin'
                                        ? 'items-end'
                                        : 'items-start'} gap-1 w-full"
                                >
                                    <div
                                        class="flex items-center gap-2 max-w-[85%] {msg.sender_type ===
                                        'admin'
                                            ? 'flex-row-reverse'
                                            : 'flex-row'}"
                                    >
                                        <div
                                            class="flex flex-col {msg.sender_type ===
                                            'admin'
                                                ? 'items-end'
                                                : 'items-start'} gap-1"
                                        >
                                            <!-- Product Reference Attachment -->
                                            <!-- svelte-ignore a11y_no_static_element_interactions -->
                                            <!-- svelte-ignore a11y_click_events_have_key_events -->
                                            {#if msg.attachment_type === 'product' && msg.attachment_data}
                                                <div
                                                    class="max-w-[100%] rounded-2xl overflow-hidden border border-slate-100 shadow-sm cursor-pointer {msg.sender_type ===
                                                    'admin'
                                                        ? 'rounded-tr-sm'
                                                        : 'rounded-tl-sm'}"
                                                    style="background-color: {msg.sender_type ===
                                                    'admin'
                                                        ? primaryColor
                                                        : 'white'};"
                                                    onclick={() =>
                                                        msg.attachment_data
                                                            .id &&
                                                        window.open(
                                                            `/admin/products/${msg.attachment_data.id}/edit`,
                                                            '_blank',
                                                        )}
                                                >
                                                    <div
                                                        class="flex items-center gap-2.5 p-3"
                                                    >
                                                        <img
                                                            src={formatImagePath(
                                                                msg
                                                                    .attachment_data
                                                                    .image,
                                                            )}
                                                            alt={msg
                                                                .attachment_data
                                                                .name}
                                                            class="w-12 h-12 rounded-xl object-cover shrink-0 bg-slate-100"
                                                            onerror={(
                                                                e: any,
                                                            ) => {
                                                                e.target.src =
                                                                    '/noimage/image.png';
                                                            }}
                                                        />
                                                        <div class="min-w-0">
                                                            <p
                                                                class="text-xs font-bold truncate {msg.sender_type ===
                                                                'admin'
                                                                    ? 'text-white'
                                                                    : 'text-slate-800'}"
                                                            >
                                                                {msg
                                                                    .attachment_data
                                                                    .name}
                                                            </p>
                                                            <p
                                                                class="text-xs mt-0.5 font-black {msg.sender_type ===
                                                                'admin'
                                                                    ? 'text-white/90'
                                                                    : 'text-orange-500'}"
                                                            >
                                                                Rp{Number(
                                                                    msg
                                                                        .attachment_data
                                                                        .price ??
                                                                        0,
                                                                ).toLocaleString(
                                                                    'id-ID',
                                                                )}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            {/if}

                                            <!-- Image Attachments -->
                                            {#if msg.attachment_type === 'image' && msg.attachment_data?.url}
                                                <div
                                                    class="max-w-[100%] rounded-2xl overflow-hidden border border-slate-100 shadow-sm"
                                                >
                                                    <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                                                    <!-- svelte-ignore a11y_click_events_have_key_events -->
                                                    <img
                                                        src={msg.attachment_data
                                                            .url}
                                                        alt="Attachment"
                                                        class="max-w-full max-h-60 object-contain bg-slate-100 cursor-pointer"
                                                        onclick={() =>
                                                            (chatPreviewUrl =
                                                                msg
                                                                    .attachment_data
                                                                    .url)}
                                                    />
                                                </div>
                                            {/if}

                                            <!-- Text body -->
                                            {#if msg.body}
                                                {#if msg.body.startsWith('[TRANSACTION_CARD]')}
                                                    {@const card = parseTransactionCard(msg.body)}
                                                    {#if card}
                                                        <div
                                                            class="p-4 rounded-2xl text-xs sm:text-sm leading-relaxed shadow-sm bg-white border border-slate-200 w-full max-w-[280px] sm:max-w-[320px] text-slate-800 text-left"
                                                        >
                                                            <div class="flex items-center gap-1.5 font-black text-[11px] uppercase tracking-wider text-slate-400 mb-2">
                                                                <i class="ti ti-file-invoice text-sm text-emerald-500"></i>
                                                                <span>Invoice Pesanan</span>
                                                            </div>
                                                            <div class="space-y-1.5">
                                                                <p class="font-bold text-xs text-slate-800">#{card.transaction_number}</p>
                                                                <div class="h-px bg-slate-100 my-1.5"></div>
                                                                <div class="flex justify-between text-[11px] font-bold text-slate-500">
                                                                    <span>Total Belanja:</span>
                                                                    <span style="color: {primaryColor}">{fmt(card.grand_total)}</span>
                                                                </div>
                                                                <div class="flex justify-between text-[11px] font-bold text-slate-500">
                                                                    <span>Pembayaran:</span>
                                                                    <span class="text-slate-700">{card.payment_method}</span>
                                                                </div>
                                                                <div class="flex justify-between text-[11px] font-bold text-slate-500">
                                                                    <span>Status:</span>
                                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase" style="background-color: {getStatusColor(card.status)}20; color: {getStatusColor(card.status)};">
                                                                        {getStatusLabel(card.status)}
                                                                    </span>
                                                                </div>
                                                                {#if card.items_summary}
                                                                    <p class="text-[10px] text-slate-400 font-medium italic truncate mt-1">
                                                                        {card.items_summary}
                                                                    </p>
                                                                {/if}
                                                            </div>
                                                            <button
                                                                onclick={() => router.visit(`/admin/transactions/${card.id}`)}
                                                                class="mt-3 w-full py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-bold rounded-xl transition active:scale-95 flex items-center justify-center gap-1 cursor-pointer"
                                                            >
                                                                <i class="ti ti-eye"></i>
                                                                Detail Transaksi
                                                            </button>
                                                        </div>
                                                    {/if}
                                                {:else}
                                                    <div
                                                        class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm leading-relaxed shadow-sm {msg.sender_type ===
                                                        'admin'
                                                            ? 'rounded-tr-sm text-white'
                                                            : 'rounded-tl-sm text-slate-800 bg-white'}"
                                                        style="background-color: {msg.sender_type ===
                                                        'admin'
                                                            ? primaryColor
                                                            : 'white'};"
                                                    >
                                                        {msg.body}
                                                    </div>
                                                {/if}
                                            {/if}
                                        </div>

                                        <!-- Delete Message Button -->
                                        <button
                                            onclick={() =>
                                                confirmDeleteMessage(msg.id)}
                                            class="opacity-40 md:opacity-0 group-hover:opacity-100 transition-opacity duration-150 p-1.5 rounded-full hover:bg-rose-50 text-slate-400 hover:text-rose-600 cursor-pointer shrink-0"
                                            title="Hapus pesan"
                                        >
                                            <i class="ti ti-trash text-xs"></i>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-1 px-1.5">
                                        <span class="text-[9px] text-slate-400"
                                            >{msg.time}</span
                                        >
                                        {#if msg.sender_type === 'admin'}
                                            <i
                                                class="ti ti-checks text-xs leading-none {msg.is_read
                                                    ? ''
                                                    : 'text-slate-400'}"
                                                style={msg.is_read
                                                    ? `color: ${primaryColor};`
                                                    : ''}
                                            ></i>
                                        {/if}
                                    </div>
                                </div>
                            {/each}
                        </div>

                        <!-- Image Preview Thumbnail -->
                        {#if attachedImageUrl}
                            <div
                                class="px-5 pb-2 pt-3 bg-white border-t border-slate-100 shrink-0"
                            >
                                <div
                                    class="relative inline-block bg-white border border-slate-200 rounded-2xl p-2 shadow-sm animate-slide-up"
                                >
                                    <img
                                        src={attachedImageUrl}
                                        alt="Attachment preview"
                                        class="w-24 h-24 rounded-xl object-cover"
                                    />
                                    <button
                                        onclick={() => {
                                            attachedImage = null;
                                            attachedImageUrl = null;
                                        }}
                                        class="absolute -top-1.5 -right-1.5 bg-rose-500 text-white hover:bg-rose-600 rounded-full w-5.5 h-5.5 flex items-center justify-center shadow cursor-pointer"
                                        aria-label="Hapus Lampiran"
                                    >
                                        <i class="ti ti-x text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        {/if}

                        <!-- Text inputs bar -->
                        <div
                            class="bg-white border-t border-slate-200 px-4 py-3.5 shrink-0"
                        >
                            <div class="flex items-center gap-2.5">
                                <button
                                    onclick={triggerImageUpload}
                                    class="text-slate-400 hover:text-slate-600 w-10 h-10 flex items-center justify-center rounded-full hover:bg-slate-50 border border-slate-200 transition cursor-pointer"
                                    aria-label="Upload Foto"
                                    title="Lampirkan Gambar"
                                >
                                    <i class="ti ti-photo text-lg"></i>
                                </button>

                                <input
                                    type="text"
                                    bind:value={replyInput}
                                    onkeydown={(e) => {
                                        if (e.key === 'Enter') sendReply();
                                    }}
                                    placeholder="Tulis balasan Anda di sini..."
                                    class="flex-grow bg-slate-100 rounded-full px-5 py-3 text-xs sm:text-sm focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                                />

                                <button
                                    onclick={sendReply}
                                    disabled={!replyInput.trim() &&
                                        !attachedImage}
                                    class="px-5 py-3 rounded-full flex items-center justify-center text-white font-bold text-xs sm:text-sm shadow transition active:scale-95 disabled:opacity-40 cursor-pointer shrink-0"
                                    style="background-color: {primaryColor};"
                                >
                                    <i
                                        class="ti ti-send text-xs sm:text-sm mr-1"
                                    ></i>
                                    Kirim
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right pane sidebar: Profile & Product topic info -->
                    <div
                        class="w-full lg:w-64 shrink-0 border-l border-slate-200 bg-slate-50/30 p-3.5 space-y-3.5 overflow-y-auto custom-scrollbar
                                {showSidebar ? 'hidden lg:block' : 'hidden'}"
                    >
                        <!-- Profile -->
                        <div
                            class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-sm"
                        >
                            <div
                                class="flex items-center gap-1.5 text-[9px] text-slate-400 font-black tracking-wider uppercase mb-2.5"
                            >
                                <i class="ti ti-user-circle text-xs"></i>
                                PROFIL PELANGGAN
                            </div>
                            <div
                                class="flex items-center gap-2.5 pb-2.5 border-b border-slate-100 mb-2.5"
                            >
                                <div
                                    class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-black shrink-0 shadow-xs overflow-hidden"
                                    style={!chat.user.avatar
                                        ? `background-image: linear-gradient(135deg, ${primaryColor}, ${secondaryColor});`
                                        : ''}
                                >
                                    {#if chat.user.avatar}
                                        <img
                                            src={formatAvatarPath(
                                                chat.user.avatar,
                                            )}
                                            alt={chat.user.name}
                                            class="w-full h-full object-cover"
                                        />
                                    {:else}
                                        {chat.user.name.charAt(0).toUpperCase()}
                                    {/if}
                                </div>
                                <div class="min-w-0">
                                    <h4
                                        class="font-bold text-xs text-slate-800 truncate"
                                    >
                                        {chat.user.name}
                                    </h4>
                                    <span
                                        class="text-[9px] text-slate-400 truncate block"
                                        >{chat.user.email}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-between text-[10px]"
                            >
                                <span class="text-slate-400 font-bold"
                                    >Status Chat</span
                                >
                                <span
                                    class="font-black text-emerald-500 flex items-center gap-1"
                                >
                                    <span
                                        class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"
                                    ></span>
                                    Online
                                </span>
                            </div>
                        </div>

                        <!-- Product info -->
                        {#if chat.product}
                            <div
                                class="bg-white border border-slate-200 rounded-2xl p-3.5 shadow-sm"
                            >
                                <div
                                    class="flex items-center gap-1.5 text-[9px] text-slate-400 font-black tracking-wider uppercase mb-2.5"
                                >
                                    <i class="ti ti-box text-xs"></i>
                                    TOPIK PRODUK
                                </div>
                                <div class="flex items-start gap-2.5 mb-3">
                                    <img
                                        src={formatImagePath(
                                            chat.product.image,
                                        )}
                                        alt={chat.product.name}
                                        class="w-11 h-11 rounded-lg object-cover shrink-0 bg-slate-50 border border-slate-100"
                                        onerror={(e: any) => {
                                            e.target.src = '/noimage/image.png';
                                        }}
                                    />
                                    <div class="min-w-0">
                                        <h4
                                            class="font-bold text-[10px] text-slate-800 line-clamp-2 leading-tight"
                                        >
                                            {chat.product.name}
                                        </h4>
                                        <span
                                            class="font-black text-[11px] text-orange-500 mt-1 block"
                                        >
                                            Rp{Number(
                                                chat.product.price,
                                            ).toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                </div>
                                <a
                                    href={`/admin/products/${chat.product.id}/edit`}
                                    target="_blank"
                                    class="w-full flex items-center justify-center gap-1 py-2 border border-slate-200 rounded-xl text-[10px] font-bold text-slate-600 hover:bg-slate-50 transition active:scale-98"
                                >
                                    <i class="ti ti-edit text-xs"></i>
                                    Edit Produk
                                </a>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    {#if deleteModalOpen}
        <div
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        >
            <div
                class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
                onclick={() => (deleteModalOpen = false)}
                onkeypress={() => (deleteModalOpen = false)}
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
                    {deleteType === 'chat'
                        ? 'Hapus Percakapan?'
                        : 'Hapus Pesan?'}
                </h4>
                <p class="text-sm text-center text-slate-500 font-medium mb-8">
                    {#if deleteType === 'chat'}
                        Seluruh obrolan ini akan terhapus secara <strong
                            >permanen</strong
                        > dan tidak dapat dikembalikan.
                    {:else}
                        Pesan ini akan terhapus secara <strong>permanen</strong> dan
                        tidak dapat dikembalikan.
                    {/if}
                </p>
                <div class="flex items-center gap-3">
                    <button
                        onclick={() => (deleteModalOpen = false)}
                        class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        onclick={executeDelete}
                        class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition cursor-pointer"
                    >
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Image Preview Modal -->
    {#if chatPreviewUrl}
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
            onclick={() => (chatPreviewUrl = null)}
        >
            <div class="absolute inset-0 bg-black/90 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                class="relative z-10 max-w-5xl w-full flex flex-col items-center justify-center"
                onclick={(e) => e.stopPropagation()}
            >
                <button
                    onclick={() => (chatPreviewUrl = null)}
                    class="absolute -top-12 right-0 text-white hover:text-slate-300 transition flex items-center gap-1 text-xs font-bold bg-white/10 hover:bg-white/20 px-3.5 py-1.5 rounded-full"
                >
                    <i class="ti ti-x text-sm"></i> Tutup
                </button>
                <img
                    src={chatPreviewUrl}
                    alt="Preview Attachment"
                    class="max-w-full max-h-[80vh] object-contain rounded-2xl shadow-2xl border border-white/10"
                />
            </div>
        </div>
    {/if}
</AdminLayout>
