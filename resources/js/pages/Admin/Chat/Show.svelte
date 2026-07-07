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

    let stickerModalOpen = $state(false);
    let emojiPickerOpen = $state(false);

    // Stickers from DB via shared props
    const stickersList = $derived((page.props as any).chatStickers || []);

    // Group stickers by category
    const stickersByCategory = $derived(() => {
        const groups: Record<string, any[]> = {};
        for (const s of stickersList) {
            const cat = s.category || 'Umum';
            if (!groups[cat]) {
                groups[cat] = [];
            }
            groups[cat].push(s);
        }
        return groups;
    });

    // Common emoji list
    const emojiList = [
        '😀',
        '😃',
        '😄',
        '😁',
        '😆',
        '😅',
        '🤣',
        '😂',
        '🙂',
        '🙃',
        '😉',
        '😊',
        '😇',
        '🥰',
        '😍',
        '🤩',
        '😘',
        '😗',
        '☺️',
        '😚',
        '😙',
        '😋',
        '😛',
        '😜',
        '🤪',
        '😝',
        '🤑',
        '🤗',
        '🤭',
        '🤫',
        '🤔',
        '🤐',
        '🤨',
        '😐',
        '😑',
        '😶',
        '😏',
        '😒',
        '🙄',
        '😬',
        '🤥',
        '😔',
        '😪',
        '🤤',
        '😴',
        '😷',
        '🤒',
        '🤕',
        '🤢',
        '🤮',
        '🤧',
        '🥵',
        '🥶',
        '🥴',
        '😵',
        '🤯',
        '🤠',
        '🥳',
        '😎',
        '🤓',
        '🧐',
        '😕',
        '😟',
        '🙁',
        '☹️',
        '😣',
        '😖',
        '😫',
        '😩',
        '🥺',
        '😢',
        '😭',
        '😤',
        '😠',
        '😡',
        '🤬',
        '😈',
        '👿',
        '💀',
        '☠️',
        '💩',
        '🤡',
        '👹',
        '👺',
        '👻',
        '👽',
        '👾',
        '🤖',
        '😺',
        '😸',
        '😹',
        '😻',
        '😼',
        '😽',
        '🙀',
        '😿',
        '😾',
        '👋',
        '🤚',
        '🖐️',
        '✋',
        '🖖',
        '👌',
        '🤌',
        '🤏',
        '✌️',
        '🤞',
        '🤟',
        '🤘',
        '🤙',
        '👈',
        '👉',
        '👆',
        '🖕',
        '👇',
        '☝️',
        '👍',
        '👎',
        '✊',
        '👊',
        '🤛',
        '🤜',
        '👏',
        '🙌',
        '👐',
        '🤲',
        '🤝',
        '🙏',
        '💪',
        '🦾',
        '🦿',
        '🦵',
        '🦶',
        '👂',
        '🦻',
        '👃',
        '👀',
        '👁️',
        '❤️',
        '🧡',
        '💛',
        '💚',
        '💙',
        '💜',
        '🖤',
        '🤍',
        '🤎',
        '💔',
        '❣️',
        '💕',
        '💞',
        '💓',
        '💗',
        '💖',
        '💘',
        '💝',
        '💟',
        '☮️',
        '⭐',
        '🌟',
        '✨',
        '💫',
        '🔥',
        '💥',
        '❄️',
        '🌈',
        '☁️',
        '⛅',
        '🌤️',
        '🌥️',
        '🌦️',
        '🌧️',
        '⛈️',
        '🌩️',
        '🌨️',
        '🌊',
        '💧',
        '💦',
        '🍎',
        '🍊',
        '🍋',
        '🍇',
        '🍓',
        '🍒',
        '🍑',
        '🥭',
        '🍍',
        '🥥',
        '🥦',
        '🥕',
        '🌽',
        '🍕',
        '🍔',
        '🍟',
        '🌮',
        '🌯',
        '🍜',
        '🍱',
        '🎁',
        '🎂',
        '🎉',
        '🎊',
        '🎈',
        '🎀',
        '🏆',
        '🥇',
        '🥈',
        '🥉',
        '🎖️',
        '🏅',
        '🎗️',
        '🎟️',
        '🎫',
        '🎪',
        '🎭',
        '🎨',
        '🎬',
        '🎤',
    ];

    function insertEmoji(emoji: string) {
        replyInput = replyInput + emoji;
        emojiPickerOpen = false;
    }

    async function sendSticker(stickerId: string) {
        stickerModalOpen = false;

        const bodyText = '[STICKER]' + stickerId;

        try {
            const formData = new FormData();
            formData.append('body', bodyText);

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
                    setTimeout(scrollToBottom, 55);
                }
            }
        } catch (err) {
            console.error('Error sending sticker:', err);
        }
    }

    let searchQuery = $state('');
    let showSidebar = $state(true);
    let fallbackInterval: any = null;

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

    // Sync messages when initialMessages prop updates from router.reload polling
    $effect(() => {
        if (initialMessages && initialMessages.length !== messages.length) {
            messages = initialMessages;
            setTimeout(scrollToBottom, 50);
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

        if (chat.id && (window as any).Echo) {
            (window as any).Echo.private(`chat.${chat.id}`)
                .listen('.message.sent', (event: any) => {
                    const newMsg = event.messageData;
                    if (newMsg) {
                        const existingIds = new Set(messages.map((m) => m.id));
                        if (!existingIds.has(newMsg.id)) {
                            messages = [...messages, newMsg];
                            setTimeout(scrollToBottom, 50);

                            // Reload Inertia props to update thread list & total unread count on sidebar
                            router.reload({
                                only: [
                                    'chats',
                                    'totalUnread',
                                    'adminChatUnreadCount',
                                ],
                                preserveScroll: true,
                            });
                        }
                    }
                })
                .listen('.messages.read', (event: any) => {
                    const readIds = event.readIds || [];
                    if (readIds.length > 0) {
                        let readChanged = false;
                        messages = messages.map((m: any) => {
                            if (readIds.includes(m.id) && !m.is_read) {
                                readChanged = true;
                                return { ...m, is_read: true };
                            }
                            return m;
                        });

                        if (readChanged) {
                            router.reload({
                                only: [
                                    'chats',
                                    'totalUnread',
                                    'adminChatUnreadCount',
                                ],
                                preserveScroll: true,
                            });
                        }
                    }
                });
        }

        // Fallback polling interval to fetch latest chat list, unread counts, and messages
        fallbackInterval = setInterval(() => {
            router.reload({
                only: ['chats', 'totalUnread', 'adminChatUnreadCount', 'initialMessages'],
                preserveScroll: true,
            });
        }, 2000);
    }

    function stopPolling() {
        if (chat.id && (window as any).Echo) {
            (window as any).Echo.leave(`chat.${chat.id}`);
        }
        if (fallbackInterval) {
            clearInterval(fallbackInterval);
            fallbackInterval = null;
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

    // ── Missing functions added ──────────────────────────────
    let messagesContainer = $state<HTMLElement | null>(null);
    let textareaRef = $state<HTMLTextAreaElement | null>(null);

    function removeAttachment() {
        attachedImage = null;
        attachedImageUrl = null;
    }

    function handleKeyDown(e: KeyboardEvent) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendReply();
        }
    }

    async function closeChat() {
        // Route not available in backend - feature not implemented
    }

    async function reopenChat() {
        // Route not available in backend - feature not implemented
    }
</script>

<svelte:head>
    <title>Chat #{chat.id} — {storeName}</title>
</svelte:head>

<AdminLayout>
    <div class="flex h-[calc(100dvh-3.5rem)] w-full overflow-hidden">

        <!-- Chat list sidebar -->
        <div class="hidden w-80 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
            <!-- Header -->
            <div class="flex h-14 items-center justify-between border-b border-slate-100 px-4">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-slate-800">Pesan</h2>
                    {#if totalUnread > 0}
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold text-white" style="background-color: {secondaryColor};">
                            {totalUnread}
                        </span>
                    {/if}
                </div>
                <Link href="/admin/chats" class="text-xs font-medium text-slate-400 hover:text-slate-700 transition-colors">
                    Semua
                </Link>
            </div>

            <!-- Search -->
            <div class="border-b border-slate-100 px-3 py-2.5">
                <form onsubmit={handleSearch} class="relative">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    <input
                        type="search"
                        placeholder="Cari percakapan..."
                        bind:value={searchQuery}
                        class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:border-slate-300 focus:bg-white focus:outline-none transition-colors"
                    />
                </form>
            </div>

            <!-- Chat list -->
            <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-100">
                {#each chats.data as c}
                    <Link
                        href="/admin/chats/{c.id}"
                        class="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-slate-50
                            {c.id === chat.id ? 'bg-slate-50 border-r-2' : ''}
                            {c.has_unread && c.id !== chat.id ? 'bg-blue-50/30' : ''}"
                        style={c.id === chat.id ? `border-color: ${primaryColor};` : ''}
                    >
                        <div class="relative shrink-0">
                            {#if c.user?.avatar}
                                <img src={formatAvatarPath(c.user.avatar)} alt={c.user?.name} class="h-8 w-8 rounded-full object-cover" />
                            {:else}
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full text-[10px] font-bold uppercase text-white"
                                    style="background-color: {c.id === chat.id ? primaryColor : '#94a3b8'};"
                                >
                                    {c.user?.name?.substring(0, 2) || '??'}
                                </div>
                            {/if}
                            {#if c.has_unread && c.id !== chat.id}
                                <span class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full border-2 border-white" style="background-color: {secondaryColor};"></span>
                            {/if}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-1">
                                <p class="truncate text-xs {c.has_unread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'}">
                                    {c.user?.name || 'Pelanggan'}
                                </p>
                                <span class="shrink-0 text-[10px] text-slate-400">{c.last_message_at ? new Date(c.last_message_at).toLocaleTimeString("id-ID", {hour:"2-digit",minute:"2-digit"}) : ""}</span>
                            </div>
                            <p class="mt-0.5 truncate text-[11px] {c.has_unread ? 'font-medium text-slate-600' : 'text-slate-400'}">
                                {typeof c.last_message === "object" ? (c.last_message?.body || c.subject || "Percakapan baru") : (c.last_message || c.subject || "Percakapan baru")}
                            </p>
                        </div>
                    </Link>
                {/each}
            </div>
        </div>

        <!-- Main chat area -->
        <div class="flex flex-1 flex-col overflow-hidden bg-white">

            <!-- Chat header -->
            <div class="flex h-14 shrink-0 items-center justify-between border-b border-slate-100 px-4 bg-white">
                <div class="flex items-center gap-3">
                    <Link href="/admin/chats" class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors lg:hidden">
                        <i class="ti ti-arrow-left text-sm"></i>
                    </Link>
                    {#if chat.user?.avatar}
                        <img src={formatAvatarPath(chat.user.avatar)} alt={chat.user?.name} class="h-8 w-8 rounded-full object-cover shrink-0" />
                    {:else}
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold uppercase text-white"
                            style="background-color: {primaryColor};"
                        >
                            {chat.user?.name?.substring(0, 2) || '??'}
                        </div>
                    {/if}
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-800">{chat.user?.name || 'Pelanggan'}</p>
                        <p class="text-[11px] text-slate-400 truncate">{chat.user?.email || ''}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Status badge only - no close/reopen (not implemented in backend) -->
                    <span class="rounded-md px-2 py-1 text-[10px] font-semibold
                        {chat.status === 'open' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                        {chat.status === 'open' ? 'Aktif' : 'Selesai'}
                    </span>
                </div>
            </div>

            <!-- Product context (if any) -->
            {#if chat.product}
                <div class="shrink-0 border-b border-slate-100 bg-slate-50 px-4 py-2">
                    <div class="flex items-center gap-2.5">
                        {#if chat.product.image}
                            <img src={formatImagePath(chat.product.image)} alt={chat.product.name} class="h-8 w-8 rounded-md object-cover border border-slate-200" />
                        {/if}
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Produk terkait</p>
                            <p class="truncate text-xs font-medium text-slate-700">{chat.product.name}</p>
                        </div>
                        <Link href="/admin/products/{chat.product.id}" class="shrink-0 text-xs font-medium transition-colors hover:underline" style="color: {primaryColor};">
                            Lihat →
                        </Link>
                    </div>
                </div>
            {/if}

            <!-- Messages area -->
            <div
                bind:this={messagesContainer}
                class="flex-1 overflow-y-auto custom-scrollbar px-4 py-4 space-y-4 bg-slate-50/30"
            >
                {#each messages as msg (msg.id)}
                    {@const isAdmin = msg.sender_type === 'admin'}
                    {@const isSticker = msg.body?.startsWith('[STICKER]')}
                    {@const isTransaction = msg.body?.startsWith('[TRANSACTION_CARD]')}
                    {@const txData = isTransaction ? parseTransactionCard(msg.body) : null}

                    <div class="flex {isAdmin ? 'justify-end' : 'justify-start'} gap-2">
                        <!-- Customer avatar -->
                        {#if !isAdmin}
                            <div class="shrink-0 self-end">
                                {#if chat.user?.avatar}
                                    <img src={formatAvatarPath(chat.user.avatar)} alt="" class="h-6 w-6 rounded-full object-cover" />
                                {:else}
                                    <div class="flex h-6 w-6 items-center justify-center rounded-full text-[9px] font-bold text-white bg-slate-400">
                                        {chat.user?.name?.substring(0, 1) || '?'}
                                    </div>
                                {/if}
                            </div>
                        {/if}

                        <!-- Bubble -->
                        <div class="max-w-[70%]">
                            {#if isSticker}
                                {@const stickerId = msg.body.replace('[STICKER]', '')}
                                {@const sticker = stickersList.find((s: any) => s.id == stickerId)}
                                {#if sticker}
                                    <img src="/storage/{sticker.image_path}" alt="sticker" class="h-24 w-24 object-contain" />
                                {:else}
                                    <p class="text-xs text-slate-400 italic">[Stiker]</p>
                                {/if}
                            {:else if isTransaction && txData}
                                <!-- Transaction card -->
                                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs w-64">
                                    <div class="border-b border-slate-100 bg-slate-50 px-3 py-2">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pesanan</p>
                                        <p class="font-mono text-xs font-bold text-slate-800">{txData.transaction_number}</p>
                                    </div>
                                    <div class="px-3 py-2.5 space-y-1">
                                        <div class="flex justify-between text-xs">
                                            <span class="text-slate-500">Total</span>
                                            <span class="font-semibold text-slate-800">{fmt(txData.grand_total)}</span>
                                        </div>
                                        <div class="flex justify-between text-xs">
                                            <span class="text-slate-500">Status</span>
                                            <span class="font-semibold" style="color: {getStatusColor(txData.status)};">{getStatusLabel(txData.status)}</span>
                                        </div>
                                    </div>
                                    <div class="border-t border-slate-100 px-3 py-2">
                                        <Link href="/admin/transactions/{txData.id}" class="text-[11px] font-medium hover:underline" style="color: {primaryColor};">
                                            Lihat detail →
                                        </Link>
                                    </div>
                                </div>
                            {:else if msg.attachment_type === 'image'}
                                <div class="overflow-hidden rounded-xl border border-slate-200 shadow-xs">
                                    <button
                                        type="button"
                                        aria-label="Perbesar gambar"
                                        onclick={() => (chatPreviewUrl = formatImagePath(msg.attachment_path))}
                                        class="block cursor-zoom-in"
                                    >
                                        <img
                                            src={formatImagePath(msg.attachment_path)}
                                            alt="Lampiran"
                                            class="max-h-60 max-w-full object-cover transition-opacity hover:opacity-95"
                                        />
                                    </button>
                                </div>
                            {:else}
                                <div
                                    class="rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-xs
                                        {isAdmin
                                            ? 'rounded-br-md text-white'
                                            : 'rounded-bl-md bg-white text-slate-800 border border-slate-200'}"
                                    style={isAdmin ? `background-color: ${primaryColor};` : ''}
                                >
                                    {msg.body}
                                </div>
                            {/if}

                            <!-- Time -->
                            <p class="mt-1 px-1 text-[10px] text-slate-400 {isAdmin ? 'text-right' : ''}">
                                {msg.time || ''}
                                {#if isAdmin && msg.is_read}
                                    <i class="ti ti-checks text-[10px]" style="color: {primaryColor};"></i>
                                {/if}
                            </p>
                        </div>
                    </div>
                {/each}

                {#if messages.length === 0}
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <i class="ti ti-message-circle text-3xl text-slate-300 mb-2"></i>
                        <p class="text-sm font-medium text-slate-500">Belum ada pesan</p>
                        <p class="mt-1 text-xs text-slate-400">Mulai percakapan dengan pelanggan</p>
                    </div>
                {/if}
            </div>

            <!-- Reply input -->
            {#if chat.status === 'open'}
                <div class="shrink-0 border-t border-slate-200 bg-white p-3">

                    <!-- Image preview -->
                    {#if attachedImageUrl}
                        <div class="mb-2 flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <img src={attachedImageUrl} alt="Preview" class="h-10 w-10 rounded-md object-cover border border-slate-200" />
                            <div class="flex-1 min-w-0">
                                <p class="truncate text-xs font-medium text-slate-700">{attachedImage?.name || 'Gambar'}</p>
                                <p class="text-[10px] text-slate-400">Siap dikirim</p>
                            </div>
                            <button aria-label="Hapus lampiran" type="button" onclick={removeAttachment} class="text-slate-400 hover:text-rose-500 transition-colors">
                                <i class="ti ti-x text-sm"></i>
                            </button>
                        </div>
                    {/if}

                    <form onsubmit={sendReply} class="flex items-end gap-2">
                        <!-- Attachment button -->
                        <button
                            type="button"
                            onclick={triggerImageUpload}
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                            title="Lampirkan gambar"
                        >
                            <i class="ti ti-photo text-base"></i>
                        </button>

                        <!-- Emoji button -->
                        <div class="relative">
                            <button
                                type="button"
                                onclick={() => { emojiPickerOpen = !emojiPickerOpen; stickerModalOpen = false; }}
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                title="Emoji"
                            >
                                <i class="ti ti-mood-smile text-base"></i>
                            </button>

                            {#if emojiPickerOpen}
                                <div class="absolute bottom-11 left-0 z-50 w-64 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                                    <div class="grid grid-cols-8 gap-1 max-h-48 overflow-y-auto custom-scrollbar">
                                        {#each emojiList as emoji}
                                            <button
                                                type="button"
                                                onclick={() => insertEmoji(emoji)}
                                                class="flex h-8 w-8 items-center justify-center rounded-md text-lg transition-colors hover:bg-slate-100"
                                            >
                                                {emoji}
                                            </button>
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <!-- Sticker button -->
                        <button
                            type="button"
                            onclick={() => { stickerModalOpen = !stickerModalOpen; emojiPickerOpen = false; }}
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                            title="Stiker"
                        >
                            <i class="ti ti-sticker text-base"></i>
                        </button>

                        <!-- Text input -->
                        <div class="flex-1 relative">
                            <textarea
                                bind:value={replyInput}
                                bind:this={textareaRef}
                                placeholder="Ketik pesan..."
                                rows={1}
                                onkeydown={handleKeyDown}
                                class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:border-slate-300 focus:bg-white focus:outline-none transition-colors max-h-32 overflow-y-auto custom-scrollbar"
                            ></textarea>
                        </div>

                        <!-- Send button -->
                        <button
                            aria-label="Kirim pesan"
                            type="submit"
                            disabled={!replyInput.trim() && !attachedImage}
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white transition-all disabled:opacity-40 disabled:cursor-not-allowed hover:opacity-90"
                            style="background-color: {primaryColor};"
                        >
                            <i class="ti ti-send text-sm"></i>
                        </button>
                    </form>
                </div>
            {:else}
                <div class="shrink-0 border-t border-slate-100 bg-slate-50 px-4 py-3 text-center">
                    <p class="text-xs text-slate-400">
                        Chat ini sudah ditutup.
                        <button type="button" onclick={reopenChat} class="font-medium transition-colors hover:underline ml-1" style="color: {primaryColor};">
                            Buka kembali
                        </button>
                    </p>
                </div>
            {/if}
        </div>

    </div>

    <!-- Sticker modal -->
    {#if stickerModalOpen}
        <div class="fixed inset-0 z-50 flex items-end justify-end pb-20 pr-4">
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <div class="fixed inset-0" onclick={() => (stickerModalOpen = false)}></div>
            <div class="relative z-10 w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="border-b border-slate-100 px-4 py-3">
                    <p class="text-sm font-semibold text-slate-800">Stiker</p>
                </div>
                <div class="max-h-64 overflow-y-auto p-3 custom-scrollbar">
                    {#if stickersList.length === 0}
                        <div class="py-8 text-center">
                            <p class="text-xs text-slate-400">Belum ada stiker</p>
                        </div>
                    {:else}
                        {@const groups = stickersByCategory()}
                        {#each Object.entries(groups) as [cat, stickers]}
                            <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{cat}</p>
                            <div class="mb-3 grid grid-cols-4 gap-2">
                                {#each stickers as sticker}
                                    <button
                                        type="button"
                                        onclick={() => sendSticker(sticker.id)}
                                        class="overflow-hidden rounded-lg border border-slate-200 p-1 transition-all hover:border-slate-300 hover:shadow-sm"
                                    >
                                        <img src="/storage/{sticker.image_path}" alt={sticker.name} class="h-14 w-full object-contain" />
                                    </button>
                                {/each}
                            </div>
                        {/each}
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    <!-- Image preview modal -->
    {#if chatPreviewUrl}
        <!-- svelte-ignore a11y_interactive_supports_focus -->
        <div
            role="dialog"
            aria-label="Preview gambar"
            aria-modal="true"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            onclick={() => (chatPreviewUrl = null)}
            onkeydown={(e) => e.key === 'Escape' && (chatPreviewUrl = null)}
        >
            <button aria-label="Tutup preview" type="button" onclick={() => (chatPreviewUrl = null)} class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors">
                <i class="ti ti-x"></i>
            </button>
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
            <img
                src={chatPreviewUrl}
                alt="Preview gambar"
                class="max-h-[85vh] max-w-[90vw] rounded-xl object-contain shadow-2xl cursor-zoom-in"
                onclick={(e) => e.stopPropagation()}
            />
        </div>
    {/if}

</AdminLayout>
