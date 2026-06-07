<script lang="ts">
    import { usePage, router, Link } from '@inertiajs/svelte';
    import { slide, fade } from 'svelte/transition';
    import { onMount } from 'svelte';
    import { showToast } from '@/utils/toast';
    import OfflineDetector from '@/components/OfflineDetector.svelte';

    const shownFlashIds = new Set();

    let {
        children,
        hideMobileHeader = false,
        hideMobileFooter = false,
    } = $props();

    const page = usePage();

    // Theme from settings
    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    $effect(() => {
        const flash = (page.props as any).flash;
        if (!flash || !flash.id || shownFlashIds.has(flash.id)) return;

        let showed = false;
        if (flash.success) {
            showToast(flash.success, 'success');
            showed = true;
        }
        if (flash.error) {
            showToast(flash.error, 'error');
            showed = true;
        }
        if (flash.warning) {
            showToast(flash.warning, 'error');
            showed = true;
        }

        if (showed) {
            shownFlashIds.add(flash.id);
        }
    });

    function withOpacity(hex: string, opacity: number): string {
        if (!hex) return '';
        const trimmed = hex.trim();
        if (!trimmed.startsWith('#')) return trimmed;
        let cleanHex = trimmed.slice(1);
        if (cleanHex.length === 8) {
            cleanHex = cleanHex.slice(0, 6);
        } else if (cleanHex.length === 4) {
            cleanHex = cleanHex.slice(0, 3);
        }
        const alphaHex = Math.round(opacity * 255)
            .toString(16)
            .padStart(2, '0');
        return `#${cleanHex}${alphaHex}`;
    }
    const auth = $derived(page.props.auth?.user);
    const storeName = $derived(
        (page.props as any).storeName ||
            (page.props as any).settings?.store_name ||
            page.props.name ||
            'Toko Kami',
    );
    const storeAppName = $derived(
        (page.props as any).settings?.store_app_name || storeName,
    );
    const storeLogo = $derived(
        (page.props as any).storeLogo ||
            (page.props as any).settings?.store_logo,
    );
    const storeIcon = $derived((page.props as any).settings?.store_icon);
    const socialMediaLinks = $derived(
        (page.props as any).socialMediaLinks || [],
    );

    // Modal state
    let authModalOpen = $state(false);
    let authTab = $state<'login' | 'register'>('login');

    // Search
    let searchQuery = $state('');

    // Mobile menu
    let mobileMenuOpen = $state(false);

    // Profile dropdown
    let profileDropOpen = $state(false);
    let isNotifOpen = $state(false);

    const cartCount = $derived((page.props as any).cartCount || 0);
    const chatUnreadCount = $derived((page.props as any).chatUnreadCount || 0);
    const customerNotifications = $derived(
        (page.props as any).customerNotifications || [],
    );
    const unreadNotifCount = $derived(
        customerNotifications.filter((n: any) => !n.is_read).length,
    );

    // Desktop floating mini-chat state
    let desktopChatOpen = $state(false);
    let chatList = $state<any[]>([]);
    let activeChatId = $state<number | null>(null);
    let activeChat = $derived(
        chatList.find((c) => c.id === activeChatId) || null,
    );
    let chatMessages = $state<any[]>([]);
    let chatInput = $state('');
    let chatInterval: any = null;
    let chatListInterval: any = null;
    let chatListLoading = $state(false);
    let chatMessagesLoading = $state(false);

    async function toggleDesktopChat() {
        if (!auth) {
            openLogin();
            return;
        }
        desktopChatOpen = !desktopChatOpen;
        if (desktopChatOpen) {
            await fetchChatList();
            startChatListPolling();
            if (activeChatId) {
                await fetchChatMessages();
                startChatPolling();
            }
        } else {
            stopChatPolling();
            stopChatListPolling();
        }
    }

    async function fetchChatList(silent = false) {
        if (!auth) return;
        if (!silent) chatListLoading = true;
        try {
            const response = await fetch('/chats', {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                chatList = await response.json();
                if (chatList.length > 0 && !activeChatId) {
                    selectChat(chatList[0].id);
                }
            }
        } catch (err) {
            console.error('Error fetching chat list:', err);
        } finally {
            if (!silent) chatListLoading = false;
        }
    }

    async function selectChat(id: number) {
        activeChatId = id;
        chatMessages = [];
        await fetchChatMessages();
        startChatPolling();
    }

    async function fetchChatMessages() {
        if (!activeChatId) return;
        chatMessagesLoading = true;
        try {
            const response = await fetch(`/chats/${activeChatId}/messages`, {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                const data = await response.json();
                chatMessages = Array.isArray(data) ? data : data.messages || [];
                setTimeout(scrollMiniChatToBottom, 50);
            }
        } catch (err) {
            console.error('Error fetching chat messages:', err);
        } finally {
            chatMessagesLoading = false;
        }
    }

    function startChatPolling() {
        stopChatPolling();
        chatInterval = setInterval(async () => {
            if (!activeChatId) return;
            const lastMsgId =
                chatMessages.length > 0
                    ? chatMessages[chatMessages.length - 1].id
                    : 0;
            try {
                const response = await fetch(
                    `/chats/${activeChatId}/messages?after_id=${lastMsgId}`,
                    {
                        headers: { Accept: 'application/json' },
                    },
                );
                if (response.ok) {
                    const data = await response.json();
                    const newMsgs = Array.isArray(data)
                        ? data
                        : data.messages || [];
                    if (newMsgs.length > 0) {
                        chatMessages = [...chatMessages, ...newMsgs];
                        setTimeout(scrollMiniChatToBottom, 50);
                        fetchChatList(true);
                    }
                }
            } catch (err) {
                console.error('Error polling chat messages:', err);
            }
        }, 5000);
    }

    function stopChatPolling() {
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
    }

    function startChatListPolling() {
        stopChatListPolling();
        chatListInterval = setInterval(async () => {
            await fetchChatList(true);
        }, 10000);
    }

    function stopChatListPolling() {
        if (chatListInterval) {
            clearInterval(chatListInterval);
            chatListInterval = null;
        }
    }

    async function sendChatMessage() {
        const text = chatInput.trim();
        if (!text || !activeChatId) return;

        // Optimistic update
        const tempId = -Date.now();
        const optimisticMsg = {
            id: tempId,
            body: text,
            sender_type: 'user',
            sender_id: auth.id,
            time: new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            }),
            created_at: new Date().toISOString(),
            is_read: false,
        };
        chatMessages = [...chatMessages, optimisticMsg];
        chatInput = '';
        setTimeout(scrollMiniChatToBottom, 50);

        try {
            const response = await fetch(`/chats/${activeChatId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({ body: text }),
            });

            if (response.ok) {
                const realMsg = await response.json();
                chatMessages = chatMessages.map((m) =>
                    m.id === tempId ? realMsg : m,
                );
                fetchChatList(true);
            } else {
                chatMessages = chatMessages.filter((m) => m.id !== tempId);
                chatInput = text;
            }
        } catch (err) {
            console.error('Error sending message:', err);
            chatMessages = chatMessages.filter((m) => m.id !== tempId);
            chatInput = text;
        }
    }

    function scrollMiniChatToBottom() {
        const el = document.querySelector('.mini-chat-messages-container');
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }

    function formatMiniChatImagePath(path: any): string {
        if (!path || typeof path !== 'string') return '/noimage/image.png';
        if (path.startsWith('http://') || path.startsWith('https://'))
            return path;
        return path.startsWith('/') ? path : '/' + path;
    }

    async function createGeneralChat() {
        if (!auth) {
            openLogin();
            return;
        }
        try {
            const response = await fetch('/chats', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({
                    subject: 'Tanya Penjual',
                }),
            });
            if (response.ok) {
                const chat = await response.json();
                await fetchChatList(true);
                await selectChat(chat.id);
            }
        } catch (err) {
            console.error('Error creating general chat:', err);
        }
    }

    function goToChat() {
        if (auth) {
            if (window.innerWidth >= 768) {
                toggleDesktopChat();
            } else {
                router.visit('/chats');
            }
        } else {
            openLogin();
        }
    }

    function goToCart() {
        if (auth) {
            router.visit('/cart');
        } else {
            openLogin();
        }
    }

    // Login form
    let loginEmail = $state('');
    let loginPassword = $state('');
    let showLoginPassword = $state(false);
    let loginError = $state('');
    let loginLoading = $state(false);

    // Register form
    let registerName = $state('');
    let registerEmail = $state('');
    let registerPassword = $state('');
    let showRegisterPassword = $state(false);
    let registerPasswordConfirmation = $state('');
    let showRegisterPasswordConfirmation = $state(false);
    let registerError = $state('');
    let registerLoading = $state(false);

    function openLogin() {
        authTab = 'login';
        authModalOpen = true;
        loginError = '';
    }

    onMount(() => {
        const handleOpenLogin = () => openLogin();
        const handleToggleDropdown = () => (profileDropOpen = !profileDropOpen);
        const handleOpenDesktopChat = async (e: any) => {
            const { productId, productName } = e.detail;
            desktopChatOpen = true;
            try {
                const response = await fetch('/chats', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                ) as HTMLMetaElement
                            )?.content || '',
                    },
                    body: JSON.stringify({
                        subject: productName,
                        product_id: productId,
                    }),
                });
                if (response.ok) {
                    const chat = await response.json();
                    await fetchChatList(true);
                    await selectChat(chat.id);
                }
            } catch (err) {
                console.error('Error starting product chat:', err);
            }
        };

        window.addEventListener('open-login-modal', handleOpenLogin);
        window.addEventListener(
            'toggle-profile-dropdown',
            handleToggleDropdown,
        );
        window.addEventListener('open-desktop-chat', handleOpenDesktopChat);

        return () => {
            window.removeEventListener('open-login-modal', handleOpenLogin);
            window.removeEventListener(
                'toggle-profile-dropdown',
                handleToggleDropdown,
            );
            window.removeEventListener(
                'open-desktop-chat',
                handleOpenDesktopChat,
            );
        };
    });

    function openRegister() {
        authTab = 'register';
        authModalOpen = true;
        registerError = '';
    }

    function closeAuthModal() {
        authModalOpen = false;
        loginEmail = '';
        loginPassword = '';
        showLoginPassword = false;
        loginError = '';
        registerName = '';
        registerEmail = '';
        registerPassword = '';
        showRegisterPassword = false;
        registerPasswordConfirmation = '';
        showRegisterPasswordConfirmation = false;
        registerError = '';
    }

    async function submitLogin(e: Event) {
        e.preventDefault();
        loginLoading = true;
        loginError = '';

        router.post(
            '/login',
            {
                email: loginEmail,
                password: loginPassword,
                remember: false,
            },
            {
                onError: (errors) => {
                    loginError =
                        errors.email ||
                        errors.password ||
                        'Login gagal, coba lagi.';
                    loginLoading = false;
                },
                onSuccess: () => {
                    closeAuthModal();
                    loginLoading = false;
                },
            },
        );
    }

    async function submitRegister(e: Event) {
        e.preventDefault();
        registerLoading = true;
        registerError = '';

        router.post(
            '/register',
            {
                name: registerName,
                email: registerEmail,
                password: registerPassword,
                password_confirmation: registerPasswordConfirmation,
            },
            {
                onError: (errors) => {
                    registerError =
                        errors.email ||
                        errors.password ||
                        errors.name ||
                        'Pendaftaran gagal.';
                    registerLoading = false;
                },
                onSuccess: () => {
                    closeAuthModal();
                    registerLoading = false;
                },
            },
        );
    }

    // Sync search input state with current URL query params
    $effect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const q = urlParams.get('q') || urlParams.get('search') || '';
        if (q) {
            searchQuery = q;
        }
    });

    function handleSearch(e: Event) {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get('/search', { q: searchQuery });
        } else {
            router.get('/search');
        }
    }

    function logout() {
        profileDropOpen = false;
        router.post('/logout');
    }

    function handleNotificationClick(notif: any) {
        isNotifOpen = false;
        if (!notif.is_read) {
            router.post(
                `/notifications/${notif.id}/read`,
                {},
                {
                    preserveScroll: true,
                    onFinish: () => {
                        if (notif.url) {
                            router.visit(notif.url);
                        }
                    },
                },
            );
        } else if (notif.url) {
            router.visit(notif.url);
        }
    }

    function markAllAsRead() {
        router.post(
            '/notifications/read-all',
            { type: 'customer' },
            { preserveScroll: true },
        );
    }

    function getInitials(name: string) {
        if (!name) return 'U';
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    }

    // Coin states & actions
    let coinsModalOpen = $state(false);
    let coinsTab = $state<'history' | 'terms'>('history');
    let coinsHistoryType = $state<'semua' | 'masuk' | 'keluar'>('semua');
    let coinsSearchQuery = $state('');
    let coinsHistoryData = $state<any>({
        data: [],
        current_page: 1,
        last_page: 1,
    });
    let coinsHistoryLoading = $state(false);
    let coinsHistoryPage = $state(1);

    function formatNumber(num: number): string {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    async function fetchCoinsHistory(pageNumber = 1) {
        if (!auth) return;
        coinsHistoryLoading = true;
        coinsHistoryPage = pageNumber;
        try {
            let url = `/profile/coin-history?page=${pageNumber}`;
            if (coinsHistoryType !== 'semua') {
                url += `&type=${coinsHistoryType}`;
            }
            if (coinsSearchQuery.trim()) {
                url += `&search=${encodeURIComponent(coinsSearchQuery.trim())}`;
            }
            const res = await fetch(url);
            if (res.ok) {
                coinsHistoryData = await res.json();
            }
        } catch (err) {
            console.error('Error fetching coin history:', err);
        } finally {
            coinsHistoryLoading = false;
        }
    }

    function openCoinsModal() {
        if (!auth) {
            openLogin();
            return;
        }
        coinsModalOpen = true;
        coinsTab = 'history';
        coinsHistoryType = 'semua';
        coinsSearchQuery = '';
        fetchCoinsHistory(1);
    }

    $effect(() => {
        if (coinsModalOpen && auth && coinsTab === 'history') {
            const _type = coinsHistoryType;
            const _search = coinsSearchQuery;
            fetchCoinsHistory(1);
        }
    });

    let deleteModalOpen = $state(false);
    let deleteType = $state<'chat' | 'message'>('chat');
    let itemToDeleteId = $state<number | null>(null);

    function confirmDeleteChat(chatId: number) {
        deleteType = 'chat';
        itemToDeleteId = chatId;
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
            try {
                const response = await fetch(`/chats/${itemToDeleteId}`, {
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
                });
                if (response.ok) {
                    if (activeChatId === itemToDeleteId) {
                        activeChatId = null;
                        chatMessages = [];
                    }
                    await fetchChatList(true);
                    showToast('Percakapan berhasil dihapus', 'success');
                } else {
                    showToast('Gagal menghapus percakapan', 'error');
                }
            } catch (err) {
                console.error('Error deleting chat:', err);
                showToast('Gagal menghapus percakapan', 'error');
            }
        } else {
            try {
                const response = await fetch(
                    `/chats/${activeChatId}/messages/${itemToDeleteId}`,
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
                    chatMessages = chatMessages.filter(
                        (m: any) => m.id !== itemToDeleteId,
                    );
                    await fetchChatList(true);
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
</script>

<div
    class="min-h-screen flex flex-col bg-slate-50 font-sans"
    style="--primary: {primary}; --secondary: {secondary};"
>
    <!-- ====== NAVBAR ====== -->
    <header
        class="{hideMobileHeader
            ? 'hidden md:block'
            : ''} sticky top-0 z-40 bg-white shadow-sm"
    >
        <!-- Top bar (desktop) -->
        <div
            class="hidden md:block border-b border-slate-100"
            style="background: linear-gradient(135deg, {primary}, {withOpacity(
                primary,
                0.85,
            )});"
        >
            <div
                class="max-w-6xl mx-auto px-6 lg:px-8 py-3 flex items-center justify-between w-full"
            >
                <div class="flex items-center gap-3">
                    <!-- Logo -->
                    <Link
                        href="/"
                        prefetch
                        class="flex items-center gap-2.5 shrink-0"
                    >
                        {#if storeLogo}
                            <img
                                src={storeLogo}
                                alt={storeName}
                                class="h-10 w-auto object-contain"
                            />
                        {:else}
                            <div
                                class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-lg"
                                style="background: rgba(255,255,255,0.2);"
                            >
                                <i class="ti ti-shopping-bag"></i>
                            </div>
                        {/if}
                        <!-- <span class="font-outfit font-black text-xl text-white tracking-tight">{storeName}</span> -->
                    </Link>
                </div>

                <!-- Search bar (desktop) -->
                <form onsubmit={handleSearch} class="flex-grow max-w-2xl mx-8">
                    <div class="relative">
                        <input
                            type="text"
                            bind:value={searchQuery}
                            placeholder="Cari produk, merek, kategori..."
                            class="w-full pl-4 pr-12 py-2.5 text-sm bg-white rounded-2xl border-2 border-transparent focus:border-white focus:outline-none focus:bg-white shadow-md"
                        />
                        <button
                            type="submit"
                            aria-label="Search"
                            class="absolute right-0 top-0 bottom-0 px-4 rounded-r-2xl text-white font-bold text-sm flex items-center gap-1.5 transition"
                            style="background-color: {secondary};"
                        >
                            <i class="ti ti-search text-lg"></i>
                        </button>
                    </div>
                </form>

                <!-- Right actions (desktop) -->
                <div class="flex items-center gap-4 shrink-0">
                    <!-- Poin Saya -->
                    {#if (page.props as any).settings?.coins_enabled}
                        <button
                            onclick={openCoinsModal}
                            class="relative p-2.5 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center shrink-0"
                            aria-label="Poin Saya"
                        >
                            <i class="ti ti-coins text-2xl"></i>
                            {#if auth}
                                <span
                                    class="text-[10px] font-bold text-white/80 mt-0.5"
                                >
                                    {formatNumber(auth.coins_balance || 0)} Poin
                                </span>
                            {:else}
                                <span
                                    class="text-[10px] font-bold text-white/80 mt-0.5"
                                >
                                    0 Poin
                                </span>
                            {/if}
                        </button>
                    {/if}

                    <!-- Cart -->
                    <button
                        onclick={goToCart}
                        class="relative p-2.5 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center"
                        aria-label="Keranjang"
                    >
                        <i class="ti ti-shopping-cart text-2xl"></i>
                        {#if cartCount > 0}
                            <span
                                class="absolute top-1 right-2.5 w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center text-white border border-white/20 shadow-sm font-sans"
                                style="background-color: {secondary}; font-family: sans-serif;"
                            >
                                {cartCount}
                            </span>
                        {/if}
                        <span class="text-[10px] font-bold text-white/80 mt-0.5"
                            >Keranjang</span
                        >
                    </button>

                    <!-- Notifications (Desktop) -->
                    {#if auth}
                        <div class="relative">
                            <button
                                onclick={() => (isNotifOpen = !isNotifOpen)}
                                class="relative p-2.5 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center shrink-0"
                                aria-label="Notifikasi"
                            >
                                <i class="ti ti-bell text-2xl"></i>
                                {#if unreadNotifCount > 0}
                                    <span
                                        class="absolute top-1 right-2.5 w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center text-white bg-brand-orange border border-white/20 shadow-sm font-sans"
                                        style="background-color: {secondary}; font-family: sans-serif;"
                                    >
                                        {unreadNotifCount}
                                    </span>
                                {/if}
                                <span
                                    class="text-[10px] font-bold text-white/80 mt-0.5"
                                    >Notifikasi</span
                                >
                            </button>

                            {#if isNotifOpen}
                                <!-- Backdrop to close dropdown on click outside -->
                                <div
                                    class="fixed inset-0 z-40"
                                    onclick={() => (isNotifOpen = false)}
                                    role="presentation"
                                ></div>

                                <!-- Dropdown Panel -->
                                <div
                                    class="absolute right-0 top-full mt-2 w-96 bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden z-50 animate-in fade-in slide-in-from-top-2 duration-150 font-sans"
                                >
                                    <!-- Header -->
                                    <div
                                        class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
                                    >
                                        <span
                                            class="text-sm font-black text-slate-800 tracking-tight"
                                            >Notifikasi Anda</span
                                        >
                                        {#if unreadNotifCount > 0}
                                            <button
                                                onclick={markAllAsRead}
                                                class="text-xs font-bold text-slate-500 hover:text-slate-800 transition"
                                            >
                                                Tandai Semua Dibaca
                                            </button>
                                        {/if}
                                    </div>

                                    <!-- Notifications List -->
                                    <div
                                        class="max-h-[350px] overflow-y-auto divide-y divide-slate-50 custom-scrollbar"
                                    >
                                        {#if customerNotifications.length > 0}
                                            {#each customerNotifications as notif}
                                                <button
                                                    onclick={() =>
                                                        handleNotificationClick(
                                                            notif,
                                                        )}
                                                    class="w-full text-left p-4 hover:bg-slate-50/70 transition flex gap-3 items-start {!notif.is_read
                                                        ? 'bg-slate-50/40'
                                                        : ''}"
                                                >
                                                    <!-- Icon -->
                                                    <div
                                                        class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 shrink-0"
                                                    >
                                                        <i
                                                            class="ti ti-package text-lg"
                                                        ></i>
                                                    </div>

                                                    <!-- Content -->
                                                    <div
                                                        class="flex-grow min-w-0"
                                                    >
                                                        <div
                                                            class="flex items-center justify-between gap-2 mb-0.5"
                                                        >
                                                            <span
                                                                class="text-xs font-black text-slate-800 truncate"
                                                                >{notif.title}</span
                                                            >
                                                            <span
                                                                class="text-[10px] text-slate-400 shrink-0"
                                                                >{notif.created_at}</span
                                                            >
                                                        </div>
                                                        <p
                                                            class="text-xs text-slate-600 leading-normal line-clamp-2"
                                                        >
                                                            {notif.message}
                                                        </p>
                                                    </div>

                                                    <!-- Unread Dot Indicator -->
                                                    {#if !notif.is_read}
                                                        <div
                                                            class="w-2.5 h-2.5 rounded-full shrink-0 mt-1.5"
                                                            style="background-color: {secondary};"
                                                        ></div>
                                                    {/if}
                                                </button>
                                            {/each}
                                        {:else}
                                            <div class="py-12 text-center">
                                                <div
                                                    class="w-12 h-12 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-3"
                                                >
                                                    <i
                                                        class="ti ti-bell-off text-2xl"
                                                    ></i>
                                                </div>
                                                <p
                                                    class="text-xs font-bold text-slate-800"
                                                >
                                                    Belum Ada Notifikasi
                                                </p>
                                                <p
                                                    class="text-[10px] text-slate-400 mt-1"
                                                >
                                                    Pembaruan pesanan Anda akan
                                                    muncul di sini.
                                                </p>
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Footer -->
                                    <div
                                        class="p-3 border-t border-slate-100 bg-slate-50/30 text-center"
                                    >
                                        <Link
                                            href="/transactions"
                                            onclick={() =>
                                                (isNotifOpen = false)}
                                            class="text-xs font-black"
                                            style="color: {primary};"
                                        >
                                            Lihat Semua Pesanan Saya
                                        </Link>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Profile / Auth -->
                    {#if auth}
                        <div class="relative">
                            <button
                                onclick={() =>
                                    (profileDropOpen = !profileDropOpen)}
                                class="flex items-center gap-2 text-white hover:bg-white/20 px-3 py-2 rounded-xl transition"
                            >
                                <div
                                    class="w-8 h-8 rounded-full overflow-hidden flex items-center justify-center font-black text-xs border border-white/40 shrink-0"
                                >
                                    {#if auth.avatar}
                                        <img
                                            src="/storage/{auth.avatar}"
                                            alt={auth.name}
                                            class="w-full h-full object-cover"
                                        />
                                    {:else}
                                        <div
                                            class="w-full h-full bg-white/20 flex items-center justify-center"
                                        >
                                            {getInitials(auth.name)}
                                        </div>
                                    {/if}
                                </div>
                                <div class="text-left">
                                    <p
                                        class="text-xs font-bold text-white leading-tight"
                                    >
                                        {auth.name.split(' ')[0]}
                                    </p>
                                    <p class="text-[10px] text-white/70">
                                        Akun Saya
                                    </p>
                                </div>
                                <i
                                    class="ti ti-chevron-down text-sm text-white/80 transition {profileDropOpen
                                        ? 'rotate-180'
                                        : ''}"
                                ></i>
                            </button>

                            {#if profileDropOpen}
                                <div
                                    transition:fade={{ duration: 150 }}
                                    class="absolute right-0 top-full mt-2 w-52 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-50"
                                >
                                    <div class="p-3 border-b border-slate-100">
                                        <p
                                            class="text-sm font-bold text-slate-800"
                                        >
                                            {auth.name}
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            {auth.email}
                                        </p>
                                    </div>
                                    <div class="p-1">
                                        <Link
                                            href="/profile"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-user text-base"></i> Profil
                                            Saya
                                        </Link>
                                        <Link
                                            href="/profile/addresses"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-map-pin text-base"
                                            ></i> Alamat Pengiriman
                                        </Link>
                                        <Link
                                            href="/transactions"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-package text-base"
                                            ></i> Pesanan
                                        </Link>
                                        <Link
                                            href="/profile/bank-accounts"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i
                                                class="ti ti-building-bank text-base"
                                            ></i> Rekening Saya
                                        </Link>
                                        <Link
                                            href="/returns"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i
                                                class="ti ti-arrow-back-up text-base"
                                            ></i> Retur Saya
                                        </Link>
                                        <Link
                                            href="/refunds"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i
                                                class="ti ti-receipt-refund text-base"
                                            ></i> Refund Saya
                                        </Link>
                                        <button
                                            onclick={() => {
                                                profileDropOpen = false;
                                                goToChat();
                                            }}
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition text-left"
                                        >
                                            <i class="ti ti-message text-base"
                                            ></i>
                                            Chat Saya
                                            {#if chatUnreadCount > 0}
                                                <span
                                                    class="ml-auto w-4 h-4 rounded-full text-[9px] font-black flex items-center justify-center text-white shrink-0"
                                                    style="background-color: {secondary};"
                                                >
                                                    {chatUnreadCount > 99
                                                        ? '99+'
                                                        : chatUnreadCount}
                                                </span>
                                            {/if}
                                        </button>
                                    </div>
                                    <div class="p-1 border-t border-slate-100">
                                        <button
                                            onclick={logout}
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-logout text-base"
                                            ></i> Keluar
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {:else}
                        <div class="flex items-center gap-2">
                            <button
                                onclick={openLogin}
                                class="px-4 py-2 text-sm font-bold text-white border border-white/40 rounded-xl hover:bg-white/20 transition"
                            >
                                Masuk
                            </button>
                            <button
                                onclick={openRegister}
                                class="px-4 py-2 text-sm font-bold rounded-xl transition shadow-md"
                                style="background-color: {secondary}; color: white;"
                            >
                                Daftar
                            </button>
                        </div>
                    {/if}
                </div>
            </div>
        </div>

        {#if !hideMobileHeader}
            <div
                class="flex md:hidden items-center gap-3 px-4 py-3"
                style="background: linear-gradient(135deg, {primary}, {withOpacity(
                    primary,
                    0.85,
                )});"
            >
                <!-- Logo / Store name (Ubah 'hidden' menjadi 'flex' jika ingin menampilkannya kembali) -->
                <Link
                    href="/"
                    prefetch
                    class="hidden items-center gap-2 shrink-0"
                >
                    {#if storeLogo}
                        <img
                            src={storeLogo}
                            alt={storeName}
                            class="h-7 w-auto"
                        />
                    {:else}
                        <i class="ti ti-shopping-bag text-white text-2xl"></i>
                    {/if}
                    <span class="font-outfit font-black text-base text-white"
                        >{storeName}</span
                    >
                </Link>

                <!-- Mobile search -->
                <form onsubmit={handleSearch} class="flex-grow">
                    <div class="relative">
                        <input
                            type="text"
                            bind:value={searchQuery}
                            placeholder="Cari produk..."
                            class="w-full pl-3 pr-10 py-2 text-sm bg-white/20 rounded-xl border border-white/30 text-white placeholder-white/60 focus:outline-none focus:bg-white focus:text-slate-800 focus:placeholder-slate-400 transition"
                        />
                        <button
                            type="submit"
                            aria-label="Search"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-white"
                        >
                            <i class="ti ti-search text-lg"></i>
                        </button>
                    </div>
                </form>

                <!-- Mobile right icons -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Coin Saya (mobile) -->
                    {#if (page.props as any).settings?.coins_enabled}
                        <button
                            onclick={openCoinsModal}
                            class="relative text-white p-1.5 shrink-0"
                            aria-label="Poin Saya"
                        >
                            <i class="ti ti-coins text-2xl"></i>
                        </button>
                    {/if}

                    <!-- Cart -->
                    <button
                        onclick={goToCart}
                        class="relative text-white p-1.5"
                        aria-label="Keranjang"
                    >
                        <i class="ti ti-shopping-cart text-2xl"></i>
                        {#if cartCount > 0}
                            <span
                                class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] font-black flex items-center justify-center text-white"
                                style="background-color: {secondary};"
                            >
                                {cartCount}
                            </span>
                        {/if}
                    </button>

                    <!-- Notifications Bell (Mobile) -->
                    {#if auth}
                        <button
                            onclick={() => {
                                isNotifOpen = !isNotifOpen;
                                profileDropOpen = false;
                            }}
                            class="relative text-white p-1.5"
                            aria-label="Notifikasi"
                        >
                            <i class="ti ti-bell text-2xl"></i>
                            {#if unreadNotifCount > 0}
                                <span
                                    class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] font-black flex items-center justify-center text-white bg-brand-orange border border-white/20 shadow-sm font-sans"
                                    style="background-color: {secondary};"
                                >
                                    {unreadNotifCount}
                                </span>
                            {/if}
                        </button>
                    {/if}

                    <!-- Profile / Login -->
                    {#if auth}
                        <button
                            onclick={() => {
                                profileDropOpen = !profileDropOpen;
                                isNotifOpen = false;
                            }}
                            class="w-8 h-8 rounded-full overflow-hidden border border-white/40 flex items-center justify-center font-black text-xs text-white shrink-0"
                        >
                            {#if auth.avatar}
                                <img
                                    src="/storage/{auth.avatar}"
                                    alt={auth.name}
                                    class="w-full h-full object-cover"
                                />
                            {:else}
                                <div
                                    class="w-full h-full bg-white/20 flex items-center justify-center"
                                >
                                    {getInitials(auth.name)}
                                </div>
                            {/if}
                        </button>
                    {:else}
                        <button
                            onclick={openLogin}
                            class="text-white p-1.5"
                            aria-label="Masuk"
                        >
                            <i class="ti ti-user-circle text-2xl"></i>
                        </button>
                    {/if}
                </div>
            </div>
        {/if}
    </header>

    <!-- Mobile profile dropdown -->
    {#if profileDropOpen && auth}
        <div
            class="md:hidden fixed top-[56px] left-0 right-0 z-[999] bg-white border-b border-slate-100 shadow-2xl"
        >
            <div class="p-4 border-b border-slate-100 flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center font-black text-sm text-white shrink-0"
                    style="background-color: {primary};"
                >
                    {#if auth.avatar}
                        <img
                            src="/storage/{auth.avatar}"
                            alt={auth.name}
                            class="w-full h-full object-cover"
                        />
                    {:else}
                        {getInitials(auth.name)}
                    {/if}
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800">
                        {auth.name}
                    </p>
                    <p class="text-xs text-slate-400">{auth.email}</p>
                </div>
            </div>
            <div class="p-2">
                <Link
                    href="/profile"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-user text-lg"></i> Profil Saya
                </Link>
                <Link
                    href="/profile/addresses"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-map-pin text-lg"></i> Alamat Pengiriman
                </Link>
                <Link
                    href="/transactions"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-package text-lg"></i> Pesanan Saya
                </Link>
                <Link
                    href="/profile/bank-accounts"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-building-bank text-lg"></i> Rekening Saya
                </Link>
                <Link
                    href="/returns"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-arrow-back-up text-lg"></i> Retur Saya
                </Link>
                <Link
                    href="/refunds"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-receipt-refund text-lg"></i> Refund Saya
                </Link>

                <button
                    onclick={() => {
                        profileDropOpen = false;
                        goToChat();
                    }}
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition text-left"
                >
                    <i class="ti ti-message text-lg"></i> Chat Saya
                    {#if chatUnreadCount > 0}
                        <span
                            class="ml-auto w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center text-white shrink-0"
                            style="background-color: {secondary};"
                        >
                            {chatUnreadCount > 99 ? '99+' : chatUnreadCount}
                        </span>
                    {/if}
                </button>

                <button
                    onclick={logout}
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm text-rose-600 hover:bg-rose-50 rounded-xl transition"
                >
                    <i class="ti ti-logout text-lg"></i> Keluar
                </button>
            </div>
        </div>
    {/if}

    <!-- Mobile notification dropdown -->
    {#if isNotifOpen && auth}
        <div
            class="md:hidden fixed top-[56px] left-0 right-0 z-[999] bg-white border-b border-slate-100 shadow-2xl max-h-[calc(100vh-56px)] overflow-y-auto"
        >
            <!-- Header -->
            <div
                class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
            >
                <span
                    class="text-sm font-black text-slate-800 tracking-tight font-sans"
                    >Notifikasi Anda</span
                >
                {#if unreadNotifCount > 0}
                    <button
                        onclick={markAllAsRead}
                        class="text-xs font-bold text-slate-500 hover:text-slate-800 transition font-sans"
                    >
                        Tandai Semua Dibaca
                    </button>
                {/if}
            </div>

            <!-- Notifications List -->
            <div
                class="divide-y divide-slate-50 max-h-[350px] overflow-y-auto custom-scrollbar font-sans"
            >
                {#if customerNotifications.length > 0}
                    {#each customerNotifications as notif}
                        <button
                            onclick={() => handleNotificationClick(notif)}
                            class="w-full text-left p-4 hover:bg-slate-50/70 transition flex gap-3 items-start {!notif.is_read
                                ? 'bg-slate-50/40'
                                : ''}"
                        >
                            <!-- Icon -->
                            <div
                                class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 shrink-0"
                            >
                                <i class="ti ti-package text-lg"></i>
                            </div>

                            <!-- Content -->
                            <div class="flex-grow min-w-0">
                                <div
                                    class="flex items-center justify-between gap-2 mb-0.5"
                                >
                                    <span
                                        class="text-xs font-black text-slate-800 truncate"
                                        >{notif.title}</span
                                    >
                                    <span
                                        class="text-[10px] text-slate-400 shrink-0"
                                        >{notif.created_at}</span
                                    >
                                </div>
                                <p
                                    class="text-xs text-slate-600 leading-normal line-clamp-2"
                                >
                                    {notif.message}
                                </p>
                            </div>

                            <!-- Unread Dot Indicator -->
                            {#if !notif.is_read}
                                <div
                                    class="w-2.5 h-2.5 rounded-full shrink-0 mt-1.5"
                                    style="background-color: {secondary};"
                                ></div>
                            {/if}
                        </button>
                    {/each}
                {:else}
                    <div class="py-12 text-center">
                        <div
                            class="w-12 h-12 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-3"
                        >
                            <i class="ti ti-bell-off text-2xl"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-800">
                            Belum Ada Notifikasi
                        </p>
                        <p class="text-[10px] text-slate-400 mt-1">
                            Pembaruan pesanan Anda akan muncul di sini.
                        </p>
                    </div>
                {/if}
            </div>

            <!-- Footer -->
            <div
                class="p-3 border-t border-slate-100 bg-slate-50/30 text-center font-sans"
            >
                <Link
                    href="/transactions"
                    onclick={() => (isNotifOpen = false)}
                    class="text-xs font-black"
                    style="color: {primary};"
                >
                    Lihat Semua Pesanan Saya
                </Link>
            </div>
        </div>
    {/if}

    <!-- ====== MAIN CONTENT ====== -->
    <main class="flex-grow flex flex-col">
        {@render children()}
    </main>

    <!-- ====== FOOTER ====== -->
    <footer
        class="{hideMobileFooter
            ? 'hidden md:block'
            : ''} bg-slate-900 text-slate-400 mt-4 py-6 border-t border-slate-800"
    >
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="flex flex-col sm:flex-row items-center justify-between gap-4"
            >
                <!-- Brand -->
                <div class="flex items-center gap-2">
                    {#if storeIcon}
                        <img
                            src={storeIcon}
                            alt={storeName}
                            class="w-8 h-8 object-contain rounded-lg"
                        />
                    {:else}
                        <div
                            class="w-8 h-8 rounded-xl flex items-center justify-center text-white"
                            style="background: linear-gradient(135deg, {primary}, {secondary});"
                        >
                            <i class="ti ti-shopping-bag text-lg"></i>
                        </div>
                    {/if}
                    <span class="font-outfit font-black text-lg text-white"
                        >{storeName}</span
                    >
                </div>

                <!-- Copyright -->
                <p class="text-xs text-slate-500">
                    © {new Date().getFullYear()}
                    <a
                        href="https://aplikasitokoonline.id/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="hover:text-slate-300 transition-colors"
                    >
                        {storeAppName}
                    </a>
                    . Hak cipta dilindungi.
                </p>

                <!-- Socials -->
                <div class="flex items-center gap-2">
                    {#if socialMediaLinks.length > 0}
                        {#each socialMediaLinks as sm}
                            {@const url = (() => {
                                const u = sm.url?.trim() || '';
                                if (
                                    u.startsWith('http://') ||
                                    u.startsWith('https://')
                                )
                                    return u;
                                if (sm.platform === 'whatsapp')
                                    return `https://wa.me/${u.replace(/\D/g, '')}`;
                                if (sm.platform === 'instagram')
                                    return `https://instagram.com/${u.replace(/^@/, '')}`;
                                if (sm.platform === 'tiktok')
                                    return `https://tiktok.com/@${u.replace(/^@/, '')}`;
                                if (sm.platform === 'facebook')
                                    return `https://facebook.com/${u}`;
                                if (sm.platform === 'twitter')
                                    return `https://x.com/${u.replace(/^@/, '')}`;
                                if (sm.platform === 'youtube')
                                    return `https://youtube.com/@${u.replace(/^@/, '')}`;
                                if (sm.platform === 'telegram')
                                    return `https://t.me/${u.replace(/^@/, '')}`;
                                if (sm.platform === 'linkedin')
                                    return `https://linkedin.com/in/${u}`;
                                return u || '#';
                            })()}
                            <a
                                href={url}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={sm.label}
                                title={sm.label}
                                class="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded-lg flex items-center justify-center transition text-slate-300 hover:text-white"
                            >
                                <i class="ti {sm.icon} text-base"></i>
                            </a>
                        {/each}
                    {:else}
                        <!-- Fallback static icons when no social media configured -->
                        <!-- svelte-ignore a11y_invalid_attribute -->
                        <a
                            href="#"
                            aria-label="Instagram"
                            class="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded-lg flex items-center justify-center transition text-slate-300"
                        >
                            <i class="ti ti-brand-instagram text-base"></i>
                        </a>
                        <!-- svelte-ignore a11y_invalid_attribute -->
                        <a
                            href="#"
                            aria-label="WhatsApp"
                            class="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded-lg flex items-center justify-center transition text-slate-300"
                        >
                            <i class="ti ti-brand-whatsapp text-base"></i>
                        </a>
                    {/if}
                </div>
            </div>
        </div>
    </footer>
</div>

<!-- ====== AUTH MODAL ====== -->
{#if authModalOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        transition:fade={{ duration: 150 }}
    >
        <!-- Backdrop -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            role="presentation"
            class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"
            onclick={closeAuthModal}
        ></div>

        <!-- Modal Box -->
        <div
            class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden z-10 animate-in zoom-in-95 duration-200"
        >
            <!-- Colored top bar -->
            <div
                class="h-2 w-full"
                style="background: linear-gradient(90deg, {primary}, {secondary});"
            ></div>

            <!-- Modal Header with Tabs -->
            <div class="p-6 pb-0">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center text-white"
                            style="background: linear-gradient(135deg, {primary}, {secondary});"
                        >
                            <i class="ti ti-shopping-bag text-lg"></i>
                        </div>
                        <span
                            class="font-outfit font-black text-lg text-slate-800"
                            >{storeName}</span
                        >
                    </div>
                    <button
                        aria-label="Close"
                        onclick={closeAuthModal}
                        class="text-slate-400 hover:text-slate-700 transition p-1"
                    >
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <!-- Tab switcher -->
                <div class="flex bg-slate-100 rounded-2xl p-1 gap-1">
                    <button
                        onclick={() => {
                            authTab = 'login';
                            loginError = '';
                        }}
                        class="flex-1 py-2.5 text-sm font-bold rounded-xl transition {authTab ===
                        'login'
                            ? 'bg-white text-slate-800 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700'}"
                    >
                        Masuk
                    </button>
                    <button
                        onclick={() => {
                            authTab = 'register';
                            registerError = '';
                        }}
                        class="flex-1 py-2.5 text-sm font-bold rounded-xl transition {authTab ===
                        'register'
                            ? 'bg-white text-slate-800 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700'}"
                    >
                        Daftar Akun
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- LOGIN TAB -->
                {#if authTab === 'login'}
                    <div transition:slide={{ duration: 150 }}>
                        <h2
                            class="text-xl font-outfit font-black text-slate-800 mb-1"
                        >
                            Selamat Datang Kembali!
                        </h2>
                        <p class="text-sm text-slate-400 mb-5">
                            Masuk untuk melanjutkan belanja Anda.
                        </p>

                        {#if loginError}
                            <div
                                class="mb-4 p-3 bg-rose-50 border border-rose-100 rounded-xl text-sm text-rose-600 font-medium flex items-center gap-2"
                            >
                                <i class="ti ti-alert-circle text-base"></i>
                                {loginError}
                            </div>
                        {/if}

                        <form onsubmit={submitLogin} class="space-y-4">
                            <div>
                                <p
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                >
                                    Email
                                </p>
                                <div class="relative">
                                    <i
                                        class="ti ti-mail absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="email"
                                        bind:value={loginEmail}
                                        required
                                        placeholder="email@contoh.com"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>
                            <div>
                                <p
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                >
                                    Kata Sandi
                                </p>
                                <div class="relative">
                                    <i
                                        class="ti ti-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type={showLoginPassword
                                            ? 'text'
                                            : 'password'}
                                        bind:value={loginPassword}
                                        required
                                        placeholder="••••••••"
                                        class="w-full pl-10 pr-10 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (showLoginPassword =
                                                !showLoginPassword)}
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                                        aria-label={showLoginPassword
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'}
                                    >
                                        <i
                                            class="ti {showLoginPassword
                                                ? 'ti-eye-off'
                                                : 'ti-eye'} text-lg"
                                        ></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-end">
                                <Link
                                    href="/forgot-password"
                                    onclick={() => (authModalOpen = false)}
                                    class="text-xs font-bold hover:underline"
                                    style="color: {primary};"
                                    >Lupa kata sandi?</Link
                                >
                            </div>

                            <button
                                type="submit"
                                disabled={loginLoading}
                                class="w-full py-3 text-sm font-bold text-white rounded-xl transition shadow-lg disabled:opacity-70 flex items-center justify-center gap-2"
                                style="background: linear-gradient(135deg, {primary}, {withOpacity(
                                    primary,
                                    0.8,
                                )});"
                            >
                                {#if loginLoading}
                                    <i
                                        class="ti ti-loader animate-spin text-base"
                                    ></i> Memproses...
                                {:else}
                                    Masuk <i class="ti ti-arrow-right text-base"
                                    ></i>
                                {/if}
                            </button>
                        </form>

                        <p class="text-center text-sm text-slate-400 mt-5">
                            Belum punya akun?
                            <button
                                onclick={() => {
                                    authTab = 'register';
                                    loginError = '';
                                }}
                                class="font-bold hover:underline"
                                style="color: {secondary};"
                            >
                                Daftar sekarang
                            </button>
                        </p>
                    </div>

                    <!-- REGISTER TAB -->
                {:else}
                    <div transition:slide={{ duration: 150 }}>
                        <h2
                            class="text-xl font-outfit font-black text-slate-800 mb-1"
                        >
                            Buat Akun Baru
                        </h2>
                        <p class="text-sm text-slate-400 mb-5">
                            Gratis! Nikmati belanja yang mudah & hemat.
                        </p>

                        {#if registerError}
                            <div
                                class="mb-4 p-3 bg-rose-50 border border-rose-100 rounded-xl text-sm text-rose-600 font-medium flex items-center gap-2"
                            >
                                <i class="ti ti-alert-circle text-base"></i>
                                {registerError}
                            </div>
                        {/if}

                        <form onsubmit={submitRegister} class="space-y-4">
                            <div>
                                <p
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                >
                                    Nama Lengkap
                                </p>
                                <div class="relative">
                                    <i
                                        class="ti ti-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="text"
                                        bind:value={registerName}
                                        required
                                        placeholder="Nama Lengkap Anda"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>
                            <div>
                                <p
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                >
                                    Email
                                </p>
                                <div class="relative">
                                    <i
                                        class="ti ti-mail absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="email"
                                        bind:value={registerEmail}
                                        required
                                        placeholder="email@contoh.com"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>
                            <div>
                                <p
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                >
                                    Kata Sandi
                                </p>
                                <div class="relative">
                                    <i
                                        class="ti ti-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type={showRegisterPassword
                                            ? 'text'
                                            : 'password'}
                                        bind:value={registerPassword}
                                        required
                                        placeholder="Min. 8 karakter"
                                        class="w-full pl-10 pr-10 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (showRegisterPassword =
                                                !showRegisterPassword)}
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                                        aria-label={showRegisterPassword
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'}
                                    >
                                        <i
                                            class="ti {showRegisterPassword
                                                ? 'ti-eye-off'
                                                : 'ti-eye'} text-lg"
                                        ></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <p
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                >
                                    Konfirmasi Kata Sandi
                                </p>
                                <div class="relative">
                                    <i
                                        class="ti ti-lock-check absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type={showRegisterPasswordConfirmation
                                            ? 'text'
                                            : 'password'}
                                        bind:value={
                                            registerPasswordConfirmation
                                        }
                                        required
                                        placeholder="Ulangi kata sandi"
                                        class="w-full pl-10 pr-10 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (showRegisterPasswordConfirmation =
                                                !showRegisterPasswordConfirmation)}
                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                                        aria-label={showRegisterPasswordConfirmation
                                            ? 'Sembunyikan kata sandi'
                                            : 'Tampilkan kata sandi'}
                                    >
                                        <i
                                            class="ti {showRegisterPasswordConfirmation
                                                ? 'ti-eye-off'
                                                : 'ti-eye'} text-lg"
                                        ></i>
                                    </button>
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={registerLoading}
                                class="w-full py-3 text-sm font-bold text-white rounded-xl transition shadow-lg disabled:opacity-70 flex items-center justify-center gap-2"
                                style="background: linear-gradient(135deg, {secondary}, {withOpacity(
                                    secondary,
                                    0.8,
                                )});"
                            >
                                {#if registerLoading}
                                    <i
                                        class="ti ti-loader animate-spin text-base"
                                    ></i> Mendaftarkan...
                                {:else}
                                    Daftar Sekarang <i
                                        class="ti ti-user-plus text-base"
                                    ></i>
                                {/if}
                            </button>
                        </form>

                        <p class="text-center text-sm text-slate-400 mt-5">
                            Sudah punya akun?
                            <button
                                onclick={() => {
                                    authTab = 'login';
                                    registerError = '';
                                }}
                                class="font-bold hover:underline"
                                style="color: {primary};"
                            >
                                Masuk di sini
                            </button>
                        </p>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}

<!-- Desktop Floating Mini Chat Window -->
{#if desktopChatOpen}
    <div
        transition:fade={{ duration: 150 }}
        class="hidden md:flex fixed bottom-6 right-[88px] z-[9999] w-[600px] h-[480px] bg-white rounded-3xl shadow-2xl border border-slate-200/80 overflow-hidden flex-row"
    >
        <!-- LEFT PANEL: Thread List -->
        <div
            class="w-[200px] bg-slate-50/50 border-r border-slate-100 flex flex-col shrink-0"
        >
            <div
                class="px-3.5 py-3 border-b border-slate-100 bg-white flex items-center justify-between shrink-0"
            >
                <span class="font-outfit font-black text-sm text-slate-800"
                    >Obrolan</span
                >
                <div class="flex items-center gap-1.5">
                    <button
                        onclick={createGeneralChat}
                        class="text-[9px] font-bold text-white px-2 py-1 rounded-lg transition flex items-center gap-0.5 cursor-pointer shadow-3xs"
                        style="background-color: {secondary};"
                        title="Tanya Penjual"
                    >
                        <i class="ti ti-plus text-[8px]"></i>
                        <span>Tanya</span>
                    </button>
                    {#if chatListLoading}
                        <i
                            class="ti ti-loader animate-spin text-slate-400 text-xs"
                        ></i>
                    {/if}
                </div>
            </div>

            <!-- List -->
            <div
                class="flex-grow overflow-y-auto divide-y divide-slate-100/60 bg-white"
            >
                {#if chatList.length === 0}
                    <div class="py-12 text-center text-slate-400 px-3">
                        <i
                            class="ti ti-message-2 text-2xl mb-1 text-slate-300 block"
                        ></i>
                        <span class="text-[10px] font-bold"
                            >Belum ada obrolan</span
                        >
                    </div>
                {:else}
                    {#each chatList as chat (chat.id)}
                        <button
                            onclick={() => selectChat(chat.id)}
                            class="w-full text-left px-3 py-3 flex items-start gap-2.5 hover:bg-slate-50/70 transition duration-150 relative cursor-pointer
                                   {activeChatId === chat.id
                                ? 'bg-slate-50'
                                : ''}"
                        >
                            <!-- Avatar -->
                            <div
                                class="w-8 h-8 rounded-full flex items-center justify-center text-white text-[10px] font-black shrink-0 shadow-xs overflow-hidden"
                                style={!(page.props as any).settings?.store_icon
                                    ? `background-color: ${activeChatId === chat.id ? secondary : primary};`
                                    : ''}
                            >
                                {#if (page.props as any).settings?.store_icon || (page.props as any).settings?.store_icon}
                                    <img
                                        src={formatMiniChatImagePath(
                                            (page.props as any).settings
                                                ?.store_icon ||
                                                (page.props as any).settings
                                                    ?.store_icon,
                                        )}
                                        alt={storeName}
                                        class="w-full h-full object-cover"
                                    />
                                {:else}
                                    {storeName.charAt(0).toUpperCase()}
                                {/if}
                            </div>

                            <!-- Info -->
                            <div class="flex-grow min-w-0">
                                <p
                                    class="font-outfit font-bold text-[11px] text-slate-800 truncate mb-0.5"
                                >
                                    {chat.subject || storeName}
                                </p>
                                <p
                                    class="text-[10px] text-slate-500 truncate leading-normal"
                                >
                                    {#if chat.last_message}
                                        {#if chat.last_message.sender_type === 'user'}
                                            Anda:
                                        {/if}
                                        {#if chat.last_message.attachment_type === 'image'}
                                            📷 Gambar
                                        {:else if chat.last_message.attachment_type === 'product'}
                                            📦 Produk
                                        {:else}
                                            {chat.last_message.body || ''}
                                        {/if}
                                    {:else}
                                        Mulai obrolan...
                                    {/if}
                                </p>
                            </div>

                            <!-- Badge -->
                            {#if chat.unread_count > 0}
                                <span
                                    class="absolute right-3 bottom-3 w-4 h-4 rounded-full text-[8px] font-black text-white flex items-center justify-center shadow-xs"
                                    style="background-color: {secondary};"
                                >
                                    {chat.unread_count}
                                </span>
                            {/if}
                        </button>
                    {/each}
                {/if}
            </div>
        </div>

        <!-- RIGHT PANEL: Chat Area -->
        <div class="flex-grow flex flex-col bg-slate-50/30">
            <!-- Header Right -->
            <div
                class="px-4 py-3 border-b border-slate-100 bg-white flex items-center gap-3 shrink-0"
            >
                {#if activeChat}
                    <div
                        class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0 overflow-hidden"
                        style={!(page.props as any).settings?.store_icon
                            ? `background-color: ${primary};`
                            : ''}
                    >
                        {#if (page.props as any).settings?.store_icon || (page.props as any).settings?.store_icon}
                            <img
                                src={formatMiniChatImagePath(
                                    (page.props as any).settings?.store_icon ||
                                        (page.props as any).settings
                                            ?.store_icon,
                                )}
                                alt={storeName}
                                class="w-full h-full object-cover"
                            />
                        {:else}
                            {storeName.charAt(0).toUpperCase()}
                        {/if}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p
                            class="font-outfit font-black text-xs text-slate-800 truncate"
                        >
                            {storeName}
                        </p>
                        <p
                            class="text-[9px] text-emerald-500 font-bold flex items-center gap-0.5 mt-0.5"
                        >
                            <span
                                class="w-1.5 h-1.5 bg-emerald-400 rounded-full inline-block"
                            ></span>
                            Online
                        </p>
                    </div>
                {:else}
                    <div class="flex-grow">
                        <span
                            class="font-outfit font-black text-xs text-slate-800"
                            >{storeName} Chat</span
                        >
                    </div>
                {/if}

                {#if activeChat}
                    <button
                        onclick={() => confirmDeleteChat(activeChat.id)}
                        class="text-slate-400 hover:text-rose-600 p-1.5 rounded-full hover:bg-rose-50 transition shrink-0 cursor-pointer mr-1"
                        title="Hapus Percakapan"
                        aria-label="Hapus Percakapan"
                    >
                        <i class="ti ti-trash text-base"></i>
                    </button>
                {/if}

                <!-- Minimize / Close -->
                <button
                    onclick={() => {
                        desktopChatOpen = false;
                        stopChatPolling();
                        stopChatListPolling();
                    }}
                    class="text-slate-400 hover:text-slate-600 p-1 rounded-full hover:bg-slate-50 transition shrink-0 cursor-pointer"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <!-- Messages List -->
            <div
                class="flex-grow overflow-y-auto px-4 py-3 space-y-2.5 relative mini-chat-messages-container bg-slate-50/50"
            >
                {#if !activeChatId}
                    <div
                        class="h-full flex flex-col items-center justify-center text-slate-400 px-6 text-center select-none"
                    >
                        <div
                            class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-3"
                        >
                            <i class="ti ti-message text-3xl text-slate-400"
                            ></i>
                        </div>
                        <p
                            class="font-outfit font-black text-xs text-slate-800 mb-1"
                        >
                            Mari memulai obrolan!
                        </p>
                        <p
                            class="text-[10px] text-slate-400 leading-normal max-w-[200px]"
                        >
                            Pilih salah satu obrolan di samping untuk mulai chat
                            dengan penjual.
                        </p>
                    </div>
                {:else if chatMessagesLoading && chatMessages.length === 0}
                    <div
                        class="h-full flex items-center justify-center text-slate-400"
                    >
                        <i class="ti ti-loader animate-spin text-xl"></i>
                    </div>
                {:else}
                    {#each chatMessages as msg (msg.id)}
                        <div
                            class="group relative flex flex-col {msg.sender_type ===
                            'user'
                                ? 'items-end'
                                : 'items-start'} gap-0.5"
                        >
                            <div
                                class="flex items-center gap-1.5 max-w-full {msg.sender_type ===
                                'user'
                                    ? 'flex-row-reverse'
                                    : 'flex-row'}"
                            >
                                <div
                                    class="flex flex-col {msg.sender_type ===
                                    'user'
                                        ? 'items-end'
                                        : 'items-start'} gap-0.5"
                                >
                                    {#if msg.attachment_type === 'product' && msg.attachment_data}
                                        <!-- Product Card -->
                                        <div
                                            class="max-w-[85%] rounded-2xl overflow-hidden border shadow-xs {msg.sender_type ===
                                            'user'
                                                ? 'rounded-tr-sm'
                                                : 'rounded-tl-sm'}"
                                            style="background-color: {msg.sender_type ===
                                            'user'
                                                ? primary
                                                : 'white'};"
                                        >
                                            <div
                                                class="flex items-center gap-2 p-2"
                                            >
                                                <img
                                                    src={formatMiniChatImagePath(
                                                        msg.attachment_data
                                                            .image,
                                                    )}
                                                    alt={msg.attachment_data
                                                        .name}
                                                    class="w-10 h-10 rounded-lg object-cover shrink-0 bg-slate-100"
                                                    onerror={(e: any) => {
                                                        e.target.src =
                                                            '/noimage/image.png';
                                                    }}
                                                />
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-[10px] font-bold truncate {msg.sender_type ===
                                                        'user'
                                                            ? 'text-white'
                                                            : 'text-slate-800'}"
                                                    >
                                                        {msg.attachment_data
                                                            .name}
                                                    </p>
                                                    <p
                                                        class="text-[10px] mt-0.5 font-black {msg.sender_type ===
                                                        'user'
                                                            ? 'text-white/90'
                                                            : 'text-orange-500'}"
                                                    >
                                                        Rp{Number(
                                                            msg.attachment_data
                                                                .price ?? 0,
                                                        ).toLocaleString(
                                                            'id-ID',
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    {/if}

                                    {#if msg.attachment_type === 'image' && msg.attachment_data?.url}
                                        <!-- Image Card -->
                                        <div
                                            class="max-w-[85%] rounded-2xl overflow-hidden border shadow-xs {msg.sender_type ===
                                            'user'
                                                ? 'rounded-tr-sm'
                                                : 'rounded-tl-sm'}"
                                        >
                                            <!-- svelte-ignore a11y_click_events_have_key_events -->
                                            <img
                                                src={msg.attachment_data.url}
                                                alt="Lampiran chat"
                                                role="presentation"
                                                class="max-w-full max-h-40 object-contain bg-slate-100 cursor-pointer rounded-lg"
                                                onclick={() =>
                                                    window.open(
                                                        msg.attachment_data.url,
                                                        '_blank',
                                                    )}
                                            />
                                        </div>
                                    {/if}

                                    {#if msg.body}
                                        <div
                                            class="max-w-[85%] px-3.5 py-2 rounded-2xl text-[11px] leading-relaxed shadow-3xs {msg.sender_type ===
                                            'user'
                                                ? 'rounded-tr-sm text-white'
                                                : 'rounded-tl-sm text-slate-800 bg-white'}"
                                            style="background-color: {msg.sender_type ===
                                            'user'
                                                ? primary
                                                : 'white'};"
                                        >
                                            {msg.body}
                                        </div>
                                    {/if}
                                </div>

                                {#if msg.id > 0 && msg.sender_type === 'user'}
                                    <button
                                        onclick={() =>
                                            confirmDeleteMessage(msg.id)}
                                        class="opacity-0 group-hover:opacity-100 transition-opacity duration-150 p-1.5 rounded-full hover:bg-rose-50 text-slate-400 hover:text-rose-600 cursor-pointer shrink-0"
                                        title="Hapus pesan"
                                    >
                                        <i class="ti ti-trash text-[10px]"></i>
                                    </button>
                                {/if}
                            </div>
                            <span class="text-[8px] text-slate-400 px-1 mt-0.5"
                                >{msg.time}</span
                            >
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Message Input Area -->
            {#if activeChatId}
                <div
                    class="bg-white border-t border-slate-100 px-3.5 py-3 shrink-0"
                >
                    <div class="flex items-center gap-2">
                        <input
                            type="text"
                            bind:value={chatInput}
                            onkeydown={(e: KeyboardEvent) => {
                                if (e.key === 'Enter') sendChatMessage();
                            }}
                            placeholder="Tulis pesan..."
                            class="flex-grow bg-slate-100 rounded-full px-3.5 py-2 text-xs focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-200 transition"
                        />
                        <button
                            onclick={sendChatMessage}
                            disabled={!chatInput.trim()}
                            class="w-8 h-8 rounded-full flex items-center justify-center text-white shadow-xs transition active:scale-95 disabled:opacity-40 shrink-0 cursor-pointer"
                            style="background-color: {primary};"
                            aria-label="Kirim"
                        >
                            <i class="ti ti-send text-xs"></i>
                        </button>
                    </div>
                </div>
            {/if}

            <!-- Delete Confirmation Modal -->
            {#if deleteModalOpen}
                <div
                    class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
                >
                    <div
                        class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
                        onclick={() => (deleteModalOpen = false)}
                        onkeypress={() => (deleteModalOpen = false)}
                        role="button"
                        tabindex="0"
                    ></div>

                    <div
                        class="bg-white rounded-3xl p-6 sm:p-8 max-w-sm w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-250 border border-slate-100"
                    >
                        <div
                            class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-2xl mb-4 mx-auto"
                        >
                            <i class="ti ti-alert-triangle"></i>
                        </div>
                        <h4
                            class="font-outfit font-black text-lg text-center text-slate-800 mb-1"
                        >
                            {deleteType === 'chat'
                                ? 'Hapus Percakapan?'
                                : 'Hapus Pesan?'}
                        </h4>
                        <p
                            class="text-xs text-center text-slate-500 font-medium mb-6"
                        >
                            {#if deleteType === 'chat'}
                                Seluruh obrolan ini akan terhapus secara <strong
                                    >permanen</strong
                                > dan tidak dapat dikembalikan.
                            {:else}
                                Pesan ini akan terhapus secara <strong
                                    >permanen</strong
                                > dan tidak dapat dikembalikan.
                            {/if}
                        </p>
                        <div class="flex items-center gap-3">
                            <button
                                onclick={() => (deleteModalOpen = false)}
                                class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                            >
                                Batal
                            </button>
                            <button
                                onclick={executeDelete}
                                class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-xs shadow-lg shadow-red-500/30 transition cursor-pointer"
                            >
                                Ya, Hapus
                            </button>
                        </div>
                    </div>
                </div>
            {/if}
        </div>
    </div>
{/if}

<!-- Floating Chat Button (Desktop & Tablet Only) -->
<button
    onclick={goToChat}
    class="hidden md:flex fixed bottom-6 right-6 z-50 w-14 h-14 items-center justify-center rounded-full text-white shadow-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-200 cursor-pointer"
    style="background: linear-gradient(135deg, {primary}, {secondary});"
    aria-label="Chat"
>
    <i class="ti ti-message-dots text-2xl"></i>
    {#if chatUnreadCount > 0}
        <span
            class="absolute -top-1 -right-1 w-6 h-6 rounded-full text-[10px] font-black flex items-center justify-center text-white border-2 border-white"
            style="background-color: {secondary};"
        >
            {chatUnreadCount > 99 ? '99+' : chatUnreadCount}
        </span>
    {/if}
</button>

<!-- Click outside to close profile dropdown -->
{#if profileDropOpen}
    <div
        role="presentation"
        class="fixed inset-0 z-30"
        onclick={() => (profileDropOpen = false)}
    ></div>
{/if}

{#if coinsModalOpen}
    <!-- Desktop Modal Backdrop -->
    <div
        role="presentation"
        class="hidden md:flex fixed inset-0 bg-slate-900/60 backdrop-blur-xs items-center justify-center z-[9999]"
        onclick={() => (coinsModalOpen = false)}
        transition:fade={{ duration: 150 }}
    >
        <!-- Modal Container -->
        <div
            role="presentation"
            class="bg-white rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[85vh] animate-in zoom-in-95 duration-200"
            onclick={(e) => e.stopPropagation()}
        >
            <!-- Modal Header -->
            <div
                class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
            >
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center"
                    >
                        <i class="ti ti-coins text-xl"></i>
                    </div>
                    <span
                        class="font-outfit font-black text-slate-800 tracking-tight"
                        >Poin Saya</span
                    >
                </div>
                <button
                    aria-label="Tutup"
                    onclick={() => (coinsModalOpen = false)}
                    class="w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="flex-grow overflow-y-auto custom-scrollbar p-6">
                <!-- Coin Balance Card -->
                <div
                    class="rounded-2xl p-5 text-white mb-6 relative overflow-hidden shadow-md"
                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                >
                    <!-- Decorative background shapes -->
                    <div
                        class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/10 rounded-full blur-xl"
                    ></div>
                    <div
                        class="absolute -left-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-xl"
                    ></div>

                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div>
                            <span
                                class="text-[10px] uppercase font-black tracking-wider text-white/80 font-sans"
                                >Total Saldo Poin</span
                            >
                            <h3 class="text-3xl font-black font-outfit mt-1">
                                {formatNumber(auth?.coins_balance || 0)}
                                <span class="text-xs font-bold">Poin</span>
                            </h3>
                            <p class="text-xs text-white/90 mt-1 font-sans">
                                Setara dengan <span class="font-black"
                                    >Rp {formatNumber(
                                        (auth?.coins_balance || 0) *
                                            ((page.props as any).settings
                                                ?.coin_conversion_rate || 1),
                                    )}</span
                                >
                            </p>
                        </div>
                        <div
                            class="w-16 h-16 bg-white/15 rounded-2xl flex items-center justify-center border border-white/20"
                        >
                            <i class="ti ti-coins text-4xl animate-pulse"></i>
                        </div>
                    </div>
                </div>

                <!-- Tabs Menu -->
                <div class="flex border-b border-slate-100 mb-4 gap-2">
                    <button
                        onclick={() => (coinsTab = 'history')}
                        class="flex-1 py-2.5 text-xs font-bold text-center border-b-2 transition font-sans"
                        style="color: {coinsTab === 'history'
                            ? primary
                            : '#64748b'}; border-color: {coinsTab === 'history'
                            ? primary
                            : 'transparent'};"
                    >
                        Riwayat Transaksi
                    </button>
                    <button
                        onclick={() => (coinsTab = 'terms')}
                        class="flex-1 py-2.5 text-xs font-bold text-center border-b-2 transition font-sans"
                        style="color: {coinsTab === 'terms'
                            ? primary
                            : '#64748b'}; border-color: {coinsTab === 'terms'
                            ? primary
                            : 'transparent'};"
                    >
                        Syarat & Ketentuan
                    </button>
                </div>

                {#if coinsTab === 'history'}
                    <!-- Filter and Search -->
                    <div class="flex flex-col gap-3 mb-4">
                        <div
                            class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl"
                        >
                            <button
                                onclick={() => (coinsHistoryType = 'semua')}
                                class="flex-1 py-1.5 text-[10px] font-black rounded-lg transition font-sans {coinsHistoryType ===
                                'semua'
                                    ? 'bg-white shadow-xs'
                                    : 'text-slate-500 hover:text-slate-800'}"
                                style="color: {coinsHistoryType === 'semua'
                                    ? primary
                                    : ''}"
                            >
                                Semua
                            </button>
                            <button
                                onclick={() => (coinsHistoryType = 'masuk')}
                                class="flex-1 py-1.5 text-[10px] font-black rounded-lg transition font-sans {coinsHistoryType ===
                                'masuk'
                                    ? 'bg-white shadow-xs text-emerald-600'
                                    : 'text-slate-500 hover:text-slate-800'}"
                            >
                                Masuk
                            </button>
                            <button
                                onclick={() => (coinsHistoryType = 'keluar')}
                                class="flex-1 py-1.5 text-[10px] font-black rounded-lg transition font-sans {coinsHistoryType ===
                                'keluar'
                                    ? 'bg-white shadow-xs text-slate-800'
                                    : 'text-slate-500 hover:text-slate-800'}"
                            >
                                Keluar
                            </button>
                        </div>

                        <div class="relative">
                            <input
                                type="text"
                                bind:value={coinsSearchQuery}
                                placeholder="Cari deskripsi atau no. pesanan..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 pl-9 pr-4 text-xs focus:bg-white focus:outline-none focus:ring-1 focus:ring-slate-200 transition"
                            />
                            <i
                                class="ti ti-search absolute left-3 top-2.5 text-slate-400 text-sm"
                            ></i>
                            {#if coinsSearchQuery}
                                <button
                                    type="button"
                                    aria-label="Hapus pencarian"
                                    onclick={() => (coinsSearchQuery = '')}
                                    class="absolute right-3 top-2 text-slate-400 hover:text-slate-600"
                                >
                                    <i class="ti ti-x text-xs"></i>
                                </button>
                            {/if}
                        </div>
                    </div>

                    <!-- History list -->
                    <div
                        class="space-y-3 max-h-[300px] overflow-y-auto pr-1 custom-scrollbar"
                    >
                        {#if coinsHistoryLoading}
                            {#each Array(3) as _}
                                <div
                                    class="bg-slate-50 rounded-xl p-3.5 animate-pulse flex justify-between items-center"
                                >
                                    <div class="space-y-2">
                                        <div
                                            class="h-3 w-32 bg-slate-200 rounded-sm"
                                        ></div>
                                        <div
                                            class="h-2 w-20 bg-slate-100 rounded-sm"
                                        ></div>
                                    </div>
                                    <div
                                        class="h-4 w-12 bg-slate-200 rounded-sm"
                                    ></div>
                                </div>
                            {/each}
                        {:else if coinsHistoryData.data && coinsHistoryData.data.length > 0}
                            {#each coinsHistoryData.data as log}
                                <div
                                    class="bg-slate-50 hover:bg-slate-100/50 rounded-xl p-3.5 border border-slate-100/50 flex items-center justify-between transition gap-3"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-bold text-slate-800 leading-normal line-clamp-2"
                                        >
                                            {log.description}
                                        </p>
                                        {#if log.transaction}
                                            <p
                                                class="text-[9px] text-slate-400 mt-0.5 tracking-tight font-sans"
                                            >
                                                No. Pesanan: <span
                                                    class="font-bold"
                                                    >{log.transaction
                                                        .transaction_number}</span
                                                >
                                            </p>
                                        {/if}
                                        <p
                                            class="text-[9px] text-slate-400 mt-1 font-sans"
                                        >
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleString('id-ID', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span
                                            class="text-xs font-black font-sans {log.amount >
                                            0
                                                ? 'text-emerald-600'
                                                : 'text-slate-700'}"
                                        >
                                            {log.amount > 0
                                                ? '+'
                                                : ''}{formatNumber(log.amount)} Poin
                                        </span>
                                        <p
                                            class="text-[9px] text-slate-400 mt-0.5 font-sans"
                                        >
                                            {log.amount > 0
                                                ? 'Poin Masuk'
                                                : 'Poin Keluar'}
                                        </p>
                                    </div>
                                </div>
                            {/each}

                            <!-- Pagination -->
                            {#if coinsHistoryData.last_page > 1}
                                <div
                                    class="flex items-center justify-between pt-3 border-t border-slate-100 mt-4"
                                >
                                    <button
                                        disabled={coinsHistoryPage === 1}
                                        onclick={() =>
                                            fetchCoinsHistory(
                                                coinsHistoryPage - 1,
                                            )}
                                        class="px-3 py-1.5 text-[10px] font-bold bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition disabled:opacity-40"
                                    >
                                        Sebelumnya
                                    </button>
                                    <span
                                        class="text-[10px] font-bold text-slate-500 font-sans"
                                    >
                                        Halaman {coinsHistoryPage} dari {coinsHistoryData.last_page}
                                    </span>
                                    <button
                                        disabled={coinsHistoryPage ===
                                            coinsHistoryData.last_page}
                                        onclick={() =>
                                            fetchCoinsHistory(
                                                coinsHistoryPage + 1,
                                            )}
                                        class="px-3 py-1.5 text-[10px] font-bold bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition disabled:opacity-40"
                                    >
                                        Selanjutnya
                                    </button>
                                </div>
                            {/if}
                        {:else}
                            <div
                                class="py-12 text-center bg-slate-50 rounded-2xl"
                            >
                                <div
                                    class="w-12 h-12 bg-white text-slate-300 rounded-2xl flex items-center justify-center mx-auto mb-3 border border-slate-100"
                                >
                                    <i class="ti ti-coins-off text-2xl"></i>
                                </div>
                                <p
                                    class="text-xs font-bold text-slate-800 font-sans"
                                >
                                    Belum ada riwayat Poin
                                </p>
                                <p
                                    class="text-[10px] text-slate-400 mt-1 px-4 font-sans"
                                >
                                    Transaksi belanja Anda yang menghasilkan
                                    atau menggunakan Poin akan tercatat di sini.
                                </p>
                            </div>
                        {/if}
                    </div>
                {:else if coinsTab === 'terms'}
                    <div
                        class="bg-slate-50 rounded-2xl p-5 border border-slate-100"
                    >
                        <p
                            class="text-xs font-bold text-slate-800 mb-3 font-sans"
                        >
                            Syarat & Ketentuan Penggunaan Poin:
                        </p>
                        <p
                            class="whitespace-pre-wrap text-[11px] text-slate-600 leading-relaxed font-sans"
                        >
                            {(page.props as any).settings
                                ?.coin_terms_conditions ||
                                'Belum ada Syarat & Ketentuan.'}
                        </p>
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <!-- Mobile Drawer Bottom Sheet -->
    <div
        role="presentation"
        class="md:hidden fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-[9999]"
        onclick={() => (coinsModalOpen = false)}
        transition:fade={{ duration: 150 }}
    >
        <!-- Bottom Sheet Container -->
        <div
            role="presentation"
            class="absolute bottom-0 left-0 right-0 bg-white rounded-t-3xl overflow-hidden shadow-2xl flex flex-col max-h-[85vh] animate-in slide-in-from-bottom duration-350"
            onclick={(e) => e.stopPropagation()}
        >
            <!-- Drawer Drag Handle Indicator -->
            <div
                class="w-full flex justify-center py-2.5 shrink-0 bg-slate-50/50"
            >
                <div class="w-12 h-1 bg-slate-300 rounded-full"></div>
            </div>

            <!-- Drawer Header -->
            <div
                class="px-5 py-3 border-b border-slate-100 flex items-center justify-between"
            >
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center"
                    >
                        <i class="ti ti-coins text-xl"></i>
                    </div>
                    <span
                        class="font-outfit font-black text-slate-800 tracking-tight"
                        >Poin Saya</span
                    >
                </div>
                <button
                    aria-label="Tutup"
                    onclick={() => (coinsModalOpen = false)}
                    class="w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center text-slate-400 hover:text-slate-600 transition"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Drawer Content (Scrollable) -->
            <div class="flex-grow overflow-y-auto custom-scrollbar p-5 pb-8">
                <!-- Coin Balance Card -->
                <div
                    class="rounded-2xl p-4 text-white mb-5 relative overflow-hidden shadow-sm"
                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                >
                    <div
                        class="relative z-10 flex items-center justify-between"
                    >
                        <div>
                            <span
                                class="text-[9px] uppercase font-black tracking-wider text-white/80 font-sans"
                                >Total Saldo Poin</span
                            >
                            <h3 class="text-2xl font-black font-outfit mt-0.5">
                                {formatNumber(auth?.coins_balance || 0)}
                                <span class="text-xs font-bold">Poin</span>
                            </h3>
                            <p class="text-[11px] text-white/90 mt-1 font-sans">
                                Setara dengan <span class="font-black"
                                    >Rp {formatNumber(
                                        (auth?.coins_balance || 0) *
                                            ((page.props as any).settings
                                                ?.coin_conversion_rate || 1),
                                    )}</span
                                >
                            </p>
                        </div>
                        <div
                            class="w-12 h-12 bg-white/15 rounded-xl flex items-center justify-center border border-white/20"
                        >
                            <i class="ti ti-coins text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Tabs Menu -->
                <div class="flex border-b border-slate-100 mb-4 gap-2">
                    <button
                        onclick={() => (coinsTab = 'history')}
                        class="flex-1 py-2 text-xs font-bold text-center border-b-2 transition font-sans"
                        style="color: {coinsTab === 'history'
                            ? primary
                            : '#64748b'}; border-color: {coinsTab === 'history'
                            ? primary
                            : 'transparent'};"
                    >
                        Riwayat Transaksi
                    </button>
                    <button
                        onclick={() => (coinsTab = 'terms')}
                        class="flex-1 py-2 text-xs font-bold text-center border-b-2 transition font-sans"
                        style="color: {coinsTab === 'terms'
                            ? primary
                            : '#64748b'}; border-color: {coinsTab === 'terms'
                            ? primary
                            : 'transparent'};"
                    >
                        Syarat & Ketentuan
                    </button>
                </div>

                {#if coinsTab === 'history'}
                    <!-- Filter and Search -->
                    <div class="flex flex-col gap-2.5 mb-4">
                        <div
                            class="flex items-center gap-1.5 bg-slate-100 p-1 rounded-xl"
                        >
                            <button
                                onclick={() => (coinsHistoryType = 'semua')}
                                class="flex-1 py-1 text-[10px] font-black rounded-lg transition font-sans {coinsHistoryType ===
                                'semua'
                                    ? 'bg-white shadow-xs'
                                    : 'text-slate-500'}"
                                style="color: {coinsHistoryType === 'semua'
                                    ? primary
                                    : ''}"
                            >
                                Semua
                            </button>
                            <button
                                onclick={() => (coinsHistoryType = 'masuk')}
                                class="flex-1 py-1 text-[10px] font-black rounded-lg transition font-sans {coinsHistoryType ===
                                'masuk'
                                    ? 'bg-white shadow-xs text-emerald-600'
                                    : 'text-slate-500'}"
                            >
                                Masuk
                            </button>
                            <button
                                onclick={() => (coinsHistoryType = 'keluar')}
                                class="flex-1 py-1 text-[10px] font-black rounded-lg transition font-sans {coinsHistoryType ===
                                'keluar'
                                    ? 'bg-white shadow-xs text-slate-800'
                                    : 'text-slate-500'}"
                            >
                                Keluar
                            </button>
                        </div>

                        <div class="relative">
                            <input
                                type="text"
                                bind:value={coinsSearchQuery}
                                placeholder="Cari deskripsi atau no. pesanan..."
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 pl-9 pr-4 text-xs focus:bg-white focus:outline-none focus:ring-1 focus:ring-slate-200 transition"
                            />
                            <i
                                class="ti ti-search absolute left-3 top-2.5 text-slate-400 text-sm"
                            ></i>
                            {#if coinsSearchQuery}
                                <button
                                    type="button"
                                    aria-label="Hapus pencarian"
                                    onclick={() => (coinsSearchQuery = '')}
                                    class="absolute right-3 top-2 text-slate-400 hover:text-slate-600"
                                >
                                    <i class="ti ti-x text-xs"></i>
                                </button>
                            {/if}
                        </div>
                    </div>

                    <!-- History list -->
                    <div
                        class="space-y-2.5 max-h-[250px] overflow-y-auto pr-0.5 custom-scrollbar"
                    >
                        {#if coinsHistoryLoading}
                            {#each Array(3) as _}
                                <div
                                    class="bg-slate-50 rounded-xl p-3 animate-pulse flex justify-between items-center"
                                >
                                    <div class="space-y-1">
                                        <div
                                            class="h-3 w-24 bg-slate-200 rounded-sm"
                                        ></div>
                                        <div
                                            class="h-2.5 w-16 bg-slate-100 rounded-sm"
                                        ></div>
                                    </div>
                                    <div
                                        class="h-3.5 w-10 bg-slate-200 rounded-sm"
                                    ></div>
                                </div>
                            {/each}
                        {:else if coinsHistoryData.data && coinsHistoryData.data.length > 0}
                            {#each coinsHistoryData.data as log}
                                <div
                                    class="bg-slate-50 hover:bg-slate-100/50 rounded-xl p-3 border border-slate-100/50 flex items-center justify-between transition gap-2"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-bold text-slate-800 leading-normal line-clamp-2"
                                        >
                                            {log.description}
                                        </p>
                                        {#if log.transaction}
                                            <p
                                                class="text-[9px] text-slate-400 mt-0.5 tracking-tight font-sans"
                                            >
                                                No. Pesanan: <span
                                                    class="font-bold"
                                                    >{log.transaction
                                                        .transaction_number}</span
                                                >
                                            </p>
                                        {/if}
                                        <p
                                            class="text-[9px] text-slate-400 mt-1 font-sans"
                                        >
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleString('id-ID', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span
                                            class="text-xs font-black font-sans {log.amount >
                                            0
                                                ? 'text-emerald-600'
                                                : 'text-slate-700'}"
                                        >
                                            {log.amount > 0
                                                ? '+'
                                                : ''}{formatNumber(log.amount)}
                                        </span>
                                        <p
                                            class="text-[8px] text-slate-400 mt-0.5 font-sans"
                                        >
                                            {log.amount > 0
                                                ? 'Masuk'
                                                : 'Keluar'}
                                        </p>
                                    </div>
                                </div>
                            {/each}

                            <!-- Pagination -->
                            {#if coinsHistoryData.last_page > 1}
                                <div
                                    class="flex items-center justify-between pt-3 border-t border-slate-100 mt-3"
                                >
                                    <button
                                        disabled={coinsHistoryPage === 1}
                                        onclick={() =>
                                            fetchCoinsHistory(
                                                coinsHistoryPage - 1,
                                            )}
                                        class="px-2.5 py-1 text-[10px] font-bold bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition disabled:opacity-40 font-sans"
                                    >
                                        Sebelum
                                    </button>
                                    <span
                                        class="text-[9px] font-bold text-slate-500 font-sans"
                                    >
                                        Hal {coinsHistoryPage} / {coinsHistoryData.last_page}
                                    </span>
                                    <button
                                        disabled={coinsHistoryPage ===
                                            coinsHistoryData.last_page}
                                        onclick={() =>
                                            fetchCoinsHistory(
                                                coinsHistoryPage + 1,
                                            )}
                                        class="px-2.5 py-1 text-[10px] font-bold bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition disabled:opacity-40 font-sans"
                                    >
                                        Lanjut
                                    </button>
                                </div>
                            {/if}
                        {:else}
                            <div
                                class="py-10 text-center bg-slate-50 rounded-2xl"
                            >
                                <div
                                    class="w-10 h-10 bg-white text-slate-300 rounded-2xl flex items-center justify-center mx-auto mb-2.5 border border-slate-100"
                                >
                                    <i class="ti ti-coins-off text-xl"></i>
                                </div>
                                <p
                                    class="text-xs font-bold text-slate-800 font-sans"
                                >
                                    Belum ada riwayat Poin
                                </p>
                                <p
                                    class="text-[9px] text-slate-400 mt-1 px-4 font-sans"
                                >
                                    Transaksi belanja Anda yang menghasilkan
                                    atau menggunakan Poin akan tercatat di sini.
                                </p>
                            </div>
                        {/if}
                    </div>
                {:else if coinsTab === 'terms'}
                    <div
                        class="bg-slate-50 rounded-2xl p-4 border border-slate-100"
                    >
                        <p
                            class="text-xs font-bold text-slate-800 mb-2 font-sans"
                        >
                            Syarat & Ketentuan Penggunaan Poin:
                        </p>
                        <p
                            class="whitespace-pre-wrap text-[10px] text-slate-600 leading-relaxed font-sans"
                        >
                            {(page.props as any).settings
                                ?.coin_terms_conditions ||
                                'Belum ada Syarat & Ketentuan.'}
                        </p>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}

<OfflineDetector />
