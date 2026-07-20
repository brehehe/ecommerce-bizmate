<script lang="ts">
    import { usePage, router, Link } from '@inertiajs/svelte';
    import { slide, fade } from 'svelte/transition';
    import { onMount } from 'svelte';
    import { showToast } from '@/utils/toast';
    import OfflineDetector from '@/components/OfflineDetector.svelte';

    // ── DARK MODE ──────────────────────────────────
    let isDark = $state(false);

    function applyDarkMode(dark: boolean) {
        localStorage.setItem('sf_theme', dark ? 'dark' : 'light');
        if (typeof document !== 'undefined') {
            if (dark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    }

    function toggleDarkMode() {
        isDark = !isDark;
        applyDarkMode(isDark);
    }

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
    const isMembershipEnabled = $derived(((page.props as any).app_config?.membership_enabled ?? (page.props as any).settings?.membership_enabled) ?? true);

    const flash = $derived((page.props as any).flash);

    $effect(() => {
        const currentFlash = flash;
        if (!currentFlash || !currentFlash.id || shownFlashIds.has(currentFlash.id)) return;

        let showed = false;
        if (currentFlash.success) {
            showToast(currentFlash.success, 'success');
            showed = true;
        }
        if (currentFlash.error) {
            showToast(currentFlash.error, 'error');
            showed = true;
        }
        if (currentFlash.warning) {
            showToast(currentFlash.warning, 'error');
            showed = true;
        }

        if (showed) {
            shownFlashIds.add(currentFlash.id);
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
    const isSuperAdmin = $derived(
        page.props.auth?.user?.roles?.some((r) => r.name === 'Super Admin') ||
            false,
    );
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

    // PWA Install Prompt state
    let deferredPrompt = $state<any>(null);
    let showInstallBanner = $state(false);
    let showInstallGuideModal = $state(false);
    let isIOS = $state(false);

    const isPwaInstallEnabled = $derived(
        (page.props as any).settings?.pwa_install_enabled !== false,
    );

    let localCartCount = $state(0);
    $effect(() => {
        localCartCount = (page.props as any).cartCount || 0;
    });
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
    let chatListLoading = $state(false);
    let chatMessagesLoading = $state(false);

    let chatListPollInterval: any = null;
    let chatMessagesPollInterval: any = null;

    $effect(() => {
        if (auth && (window as any).Echo) {
            const channel = (window as any).Echo.private(
                `user.${auth.id}`,
            ).listen('.notification.updated', (event: any) => {
                const data = event.data || {};
                let unreadChanged = false;
                if (data.chatUnreadCount !== undefined) {
                    if (
                        (page.props as any).chatUnreadCount !==
                        data.chatUnreadCount
                    ) {
                        unreadChanged = true;
                    }
                    (page.props as any).chatUnreadCount = data.chatUnreadCount;
                }
                if (data.cartCount !== undefined) {
                    (page.props as any).cartCount = data.cartCount;
                    localCartCount = data.cartCount;
                }
                if (data.customerNotifications !== undefined) {
                    (page.props as any).customerNotifications =
                        data.customerNotifications;
                }

                if (unreadChanged && desktopChatOpen) {
                    fetchChatList(true);
                }
            })
            .listen('.transaction.updated', (event: any) => {
                const pathname = window.location.pathname;
                if (pathname.startsWith('/transactions') || pathname === '/') {
                    router.reload();
                }
            })
            .listen('.refund.updated', (event: any) => {
                const pathname = window.location.pathname;
                if (pathname.startsWith('/refunds')) {
                    router.reload();
                }
            })
            .listen('.return.updated', (event: any) => {
                const pathname = window.location.pathname;
                if (pathname.startsWith('/returns')) {
                    router.reload();
                }
            });

            return () => {
                (window as any).Echo.leave(`user.${auth.id}`);
            };
        }
    });


    async function toggleDesktopChat() {
        if (!auth) {
            openLogin();
            return;
        }
        desktopChatOpen = !desktopChatOpen;
        if (desktopChatOpen) {
            await fetchChatList();
            if (activeChatId) {
                await fetchChatMessages();
                startChatPolling();
            }
        } else {
            stopChatPolling();
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

    function startChatListPolling() {
        stopChatListPolling();
        chatListPollInterval = setInterval(() => {
            fetchChatList(true);
        }, 4000);
    }

    function stopChatListPolling() {
        if (chatListPollInterval) {
            clearInterval(chatListPollInterval);
            chatListPollInterval = null;
        }
    }

    function startChatMessagesPolling() {
        stopChatMessagesPolling();
        if (!activeChatId) return;
        chatMessagesPollInterval = setInterval(() => {
            fetchChatMessages();
        }, 3000);
    }

    function stopChatMessagesPolling() {
        if (chatMessagesPollInterval) {
            clearInterval(chatMessagesPollInterval);
            chatMessagesPollInterval = null;
        }
    }

    function startChatPolling() {
        stopChatPolling();

        if (activeChatId && (window as any).Echo) {
            (window as any).Echo.private(`chat.${activeChatId}`)
                .listen('.message.sent', (event: any) => {
                    const newMsg = event.messageData;
                    if (newMsg) {
                        const existingIds = new Set(chatMessages.map((m) => m.id));
                        if (existingIds.has(newMsg.id)) {
                            return;
                        }

                        const optimisticIndex = chatMessages.findIndex(
                            (m) =>
                                m.id < 0 &&
                                m.sender_type === newMsg.sender_type &&
                                m.sender_id === newMsg.sender_id &&
                                (m.body === newMsg.body ||
                                    m.attachment_type === newMsg.attachment_type),
                        );

                        if (optimisticIndex !== -1) {
                            chatMessages = chatMessages.map((m, idx) =>
                                idx === optimisticIndex ? newMsg : m,
                            );
                        } else {
                            chatMessages = [...chatMessages, newMsg];
                        }
                        setTimeout(scrollMiniChatToBottom, 50);
                        fetchChatList(true);
                    }
                })
                .listen('.messages.read', (event: any) => {
                    const readIds = event.readIds || [];
                    if (readIds.length > 0) {
                        chatMessages = chatMessages.map((m: any) => {
                            if (readIds.includes(m.id) && !m.is_read) {
                                return { ...m, is_read: true };
                            }
                            return m;
                        });
                    }
                });
        }

        // Fallback polling for messages and chat list
        startChatMessagesPolling();
        startChatListPolling();
    }

    function stopChatPolling() {
        if (activeChatId && (window as any).Echo) {
            (window as any).Echo.leave(`chat.${activeChatId}`);
        }
        stopChatMessagesPolling();
        stopChatListPolling();
    }

    let stickerModalOpen = $state(false);
    let emojiPickerOpen = $state(false);

    // Stickers from DB via shared props
    const stickersList = $derived((page.props as any).chatStickers || []);

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
        chatInput = chatInput + emoji;
        emojiPickerOpen = false;
    }

    async function sendSticker(stickerId: string) {
        stickerModalOpen = false;
        if (!activeChatId) return;

        const bodyText = '[STICKER]' + stickerId;

        // Optimistic update
        const tempId = -Date.now();
        const optimisticMsg = {
            id: tempId,
            body: bodyText,
            sender_type: 'user',
            sender_id: auth?.id,
            time: new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            }),
            created_at: new Date().toISOString(),
            is_read: false,
        };
        chatMessages = [...chatMessages, optimisticMsg];
        setTimeout(scrollMiniChatToBottom, 55);

        try {
            const response = await fetch(`/chats/${activeChatId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ body: bodyText }),
            });

            if (response.ok) {
                const newMsg = await response.json();
                // Check if the message was already added by the WebSocket listener
                const alreadyExists = chatMessages.some(
                    (m) => m.id === newMsg.id,
                );
                if (alreadyExists) {
                    chatMessages = chatMessages.filter((m) => m.id !== tempId);
                } else {
                    chatMessages = chatMessages.map((m) =>
                        m.id === tempId ? newMsg : m,
                    );
                }
            }
        } catch (err) {
            console.error('Error sending sticker:', err);
        }
    }

    let attachedImage = $state<File | null>(null);
    let attachedImageUrl = $state<string | null>(null);
    let invoiceModalOpen = $state(false);
    let transactionsList = $state<any[]>([]);

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

    async function openInvoiceSelection() {
        invoiceModalOpen = true;
        try {
            const response = await fetch('/chats/transactions', {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                transactionsList = await response.json();
            }
        } catch (err) {
            console.error('Error fetching transactions for mini-chat:', err);
        }
    }

    async function sendTransactionInvoice(trx: any) {
        invoiceModalOpen = false;
        if (!activeChatId) return;

        const cardData = {
            transaction_number: trx.transaction_number,
            grand_total: trx.grand_total,
            payment_method: trx.payment_method,
            status: trx.status,
            id: trx.id,
            items_summary: trx.items_summary,
        };

        const bodyText = '[TRANSACTION_CARD]' + JSON.stringify(cardData);

        // Optimistic update
        const tempId = -Date.now();
        const optimisticMsg = {
            id: tempId,
            body: bodyText,
            sender_type: 'user',
            sender_id: auth?.id,
            time: new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            }),
            created_at: new Date().toISOString(),
            is_read: false,
        };
        chatMessages = [...chatMessages, optimisticMsg];
        setTimeout(scrollMiniChatToBottom, 55);

        try {
            const response = await fetch(`/chats/${activeChatId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ body: bodyText }),
            });

            if (response.ok) {
                const newMsg = await response.json();
                // Check if the message was already added by the WebSocket listener
                const alreadyExists = chatMessages.some(
                    (m) => m.id === newMsg.id,
                );
                if (alreadyExists) {
                    chatMessages = chatMessages.filter((m) => m.id !== tempId);
                } else {
                    chatMessages = chatMessages.map((m) =>
                        m.id === tempId ? newMsg : m,
                    );
                }
                fetchChatList(true);
            } else {
                chatMessages = chatMessages.filter((m) => m.id !== tempId);
            }
        } catch (err) {
            console.error('Error sending transaction card:', err);
            chatMessages = chatMessages.filter((m) => m.id !== tempId);
        }
    }

    async function sendChatMessage() {
        const text = chatInput.trim();
        if ((!text && !attachedImage) || !activeChatId) return;

        // Optimistic update
        const tempId = -Date.now();
        const optimisticMsg = {
            id: tempId,
            body: text || null,
            attachment_type: attachedImage ? 'image' : null,
            attachment_data: attachedImage ? { url: attachedImageUrl } : null,
            sender_type: 'user',
            sender_id: auth?.id,
            time: new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            }),
            created_at: new Date().toISOString(),
            is_read: false,
        };
        chatMessages = [...chatMessages, optimisticMsg];
        chatInput = '';
        const savedAttachedImage = attachedImage;
        attachedImage = null;
        attachedImageUrl = null;
        setTimeout(scrollMiniChatToBottom, 50);

        try {
            const formData = new FormData();
            if (text) formData.append('body', text);
            if (
                optimisticMsg.attachment_type === 'image' &&
                savedAttachedImage
            ) {
                formData.append('image', savedAttachedImage);
            }

            const response = await fetch(`/chats/${activeChatId}/messages`, {
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
                const realMsg = await response.json();
                // Check if the message was already added by the WebSocket listener
                const alreadyExists = chatMessages.some(
                    (m) => m.id === realMsg.id,
                );
                if (alreadyExists) {
                    chatMessages = chatMessages.filter((m) => m.id !== tempId);
                } else {
                    chatMessages = chatMessages.map((m) =>
                        m.id === tempId ? realMsg : m,
                    );
                }
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
        // Initialize dark mode from localStorage or system preference
        const stored = localStorage.getItem('sf_theme');
        if (stored === 'dark') {
            isDark = true;
        } else if (stored === 'light') {
            isDark = false;
        } else {
            // Follow system preference if no stored value
            isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        applyDarkMode(isDark);

        const handleOpenLogin = () => openLogin();
        const handleToggleDropdown = () => (profileDropOpen = !profileDropOpen);
        
        const unsubscribe = router.on('navigate', (event: any) => {
            profileDropOpen = false;
            isNotifOpen = false;
            // Sync cartCount on every page navigation
            const navProps = event?.detail?.page?.props;
            if (navProps && navProps.cartCount !== undefined) {
                localCartCount = navProps.cartCount;
            }
        });

        const unsubscribeSuccess = router.on('success', (event: any) => {
            const pageProps = event.detail.page.props;
            if (pageProps && pageProps.cartCount !== undefined) {
                localCartCount = pageProps.cartCount;
            }
        });

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

        const handleCartUpdated = (e: any) => {
            if (e.detail?.cartCount !== undefined) {
                localCartCount = e.detail.cartCount;
            } else {
                localCartCount = localCartCount + (e.detail?.delta ?? 1);
            }
        };

        window.addEventListener('open-login-modal', handleOpenLogin);
        window.addEventListener(
            'toggle-profile-dropdown',
            handleToggleDropdown,
        );
        window.addEventListener('open-desktop-chat', handleOpenDesktopChat);
        window.addEventListener('cart-updated', handleCartUpdated);

        return () => {
            unsubscribe();
            unsubscribeSuccess();
            window.removeEventListener('open-login-modal', handleOpenLogin);
            window.removeEventListener(
                'toggle-profile-dropdown',
                handleToggleDropdown,
            );
            window.removeEventListener(
                'open-desktop-chat',
                handleOpenDesktopChat,
            );
            window.removeEventListener('cart-updated', handleCartUpdated);
        };
    });

    onMount(() => {
        isIOS =
            /iPad|iPhone|iPod/.test(navigator.userAgent) &&
            !(window as any).MSStream;

        if (!isPwaInstallEnabled) return;

        const isDismissed =
            localStorage.getItem('pwa_install_prompt_dismissed') === 'true';

        const handleBeforeInstallPrompt = (e: Event) => {
            e.preventDefault();
            deferredPrompt = e;
            if (!isDismissed) {
                showInstallBanner = true;
            }
        };

        const handleAppInstalled = () => {
            deferredPrompt = null;
            showInstallBanner = false;
            localStorage.setItem('pwa_install_prompt_dismissed', 'true');
        };

        window.addEventListener(
            'beforeinstallprompt',
            handleBeforeInstallPrompt,
        );
        window.addEventListener('appinstalled', handleAppInstalled);

        // Fallback display after a small delay if not dismissed and not already triggered
        if (!isDismissed && !showInstallBanner) {
            const timer = setTimeout(() => {
                showInstallBanner = true;
            }, 3000);
            return () => {
                clearTimeout(timer);
                window.removeEventListener(
                    'beforeinstallprompt',
                    handleBeforeInstallPrompt,
                );
                window.removeEventListener('appinstalled', handleAppInstalled);
            };
        }

        return () => {
            window.removeEventListener(
                'beforeinstallprompt',
                handleBeforeInstallPrompt,
            );
            window.removeEventListener('appinstalled', handleAppInstalled);
        };
    });

    async function installApp() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            deferredPrompt = null;
            showInstallBanner = false;
            localStorage.setItem('pwa_install_prompt_dismissed', 'true');
        } else {
            showInstallGuideModal = true;
        }
    }

    function dismissInstallBanner() {
        showInstallBanner = false;
        localStorage.setItem('pwa_install_prompt_dismissed', 'true');
    }

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
    class="min-h-screen flex flex-col {isDark ? 'sf-dark bg-slate-900' : 'bg-slate-50'} font-sans transition-colors duration-300"
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
                <form
                    onsubmit={handleSearch}
                    class="flex-grow max-w-3xl lg:max-w-4xl mx-6"
                >
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
                <div class="flex items-center gap-2.5 lg:gap-3.5 shrink-0">

                    <!-- Dark Mode Toggle (Desktop) - Only show for guests in header -->
                    {#if !auth}
                        <button
                            onclick={toggleDarkMode}
                            class="relative p-2 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center shrink-0"
                            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                            title={isDark ? 'Mode Terang' : 'Mode Gelap'}
                        >
                            {#if isDark}
                                <i class="ti ti-sun text-xl light-toggle-icon-enter"></i>
                                <span class="text-[9px] font-bold text-white/80 mt-0.5">Terang</span>
                            {:else}
                                <i class="ti ti-moon text-xl dark-toggle-icon-enter"></i>
                                <span class="text-[9px] font-bold text-white/80 mt-0.5">Gelap</span>
                            {/if}
                        </button>
                        <!-- Poin Saya (Desktop) - Only show for guests in header -->
                        {#if (page.props as any).settings?.coins_enabled}
                            <button
                                onclick={openCoinsModal}
                                class="relative p-2 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center shrink-0"
                                aria-label="Poin Saya"
                            >
                                <i class="ti ti-coins text-xl"></i>
                                <span
                                    class="text-[9px] font-bold text-white/80 mt-0.5"
                                >
                                    Poin
                                </span>
                            </button>
                        {/if}
                    {/if}

                    <!-- Cart -->
                    <button
                        onclick={goToCart}
                        class="relative p-2 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center shrink-0"
                        aria-label="Keranjang"
                    >
                        <div class="relative">
                            <i class="ti ti-shopping-cart text-xl"></i>
                            {#if localCartCount > 0}
                                <span
                                    class="absolute -top-1.5 -right-2.5 min-w-[16px] h-4 px-1 rounded-full text-[8px] font-black flex items-center justify-center text-white border border-white/20 shadow-sm"
                                    style="background-color: {secondary}; font-family: sans-serif;"
                                >
                                    {localCartCount}
                                </span>
                            {/if}
                        </div>
                        <span class="text-[9px] font-bold text-white/80 mt-0.5"
                            >Keranjang</span
                        >
                    </button>

                    <!-- Notifications (Desktop) -->
                    {#if auth}
                        <div class="relative">
                            <button
                                onclick={() => (isNotifOpen = !isNotifOpen)}
                                class="relative p-2 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center shrink-0"
                                aria-label="Notifikasi"
                            >
                                <div class="relative">
                                    <i class="ti ti-bell text-xl"></i>
                                    {#if unreadNotifCount > 0}
                                        <span
                                            class="absolute -top-1.5 -right-2.5 min-w-[16px] h-4 px-1 rounded-full text-[8px] font-black flex items-center justify-center text-white border border-white/20 shadow-sm"
                                            style="background-color: {secondary}; font-family: sans-serif;"
                                        >
                                            {unreadNotifCount}
                                        </span>
                                    {/if}
                                </div>
                                <span class="text-[9px] font-bold text-white/80 mt-0.5">Notifikasi</span>
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
                                        {#if isMembershipEnabled}
                                            <Link
                                                href="/membership"
                                                prefetch
                                                class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                            >
                                                <i class="ti ti-id text-base"></i> Membership
                                                Saya
                                            </Link>
                                        {/if}
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
                                            <i class="ti ti-message text-base"></i>
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
                                        {#if (page.props as any).settings?.coins_enabled}
                                            <button
                                                onclick={() => {
                                                    profileDropOpen = false;
                                                    openCoinsModal();
                                                }}
                                                class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition text-left font-medium"
                                            >
                                                <i class="ti ti-coins text-base text-amber-500"></i> Poin Saya: <span class="font-bold text-slate-900 ml-1">{formatNumber(auth.coins_balance || 0)}</span>
                                            </button>
                                        {/if}
                                        <button
                                            onclick={toggleDarkMode}
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition text-left font-medium"
                                        >
                                            {#if isDark}
                                                <i class="ti ti-sun text-base text-amber-500 animate-pulse"></i> Mode Terang
                                            {:else}
                                                <i class="ti ti-moon text-base text-indigo-500"></i> Mode Gelap
                                            {/if}
                                        </button>
                                    </div>
                                    <div class="p-1 border-t border-slate-100">
                                        {#if isSuperAdmin}
                                            <a
                                                href="/admin/dashboard"
                                                class="flex items-center gap-2.5 px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50 rounded-xl transition font-semibold"
                                            >
                                                <i class="ti ti-shield-check text-base"></i> Panel Admin
                                            </a>
                                        {/if}
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

                <!-- Home Button (Mobile) - Only show if not on homepage -->
                {#if page.url.split('?')[0] !== '/'}
                    <Link
                        href="/"
                        class="text-white p-1.5 shrink-0 flex items-center justify-center"
                        aria-label="Kembali ke Home"
                    >
                        <i class="ti ti-home text-2xl"></i>
                    </Link>
                {/if}

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

                    <!-- Dark Mode Toggle (Mobile) - Only show for guests in header -->
                    {#if !auth}
                        <button
                            onclick={toggleDarkMode}
                            class="relative text-white p-1.5 shrink-0"
                            aria-label={isDark ? 'Mode Terang' : 'Mode Gelap'}
                        >
                            {#if isDark}
                                <i class="ti ti-sun text-2xl light-toggle-icon-enter"></i>
                            {:else}
                                <i class="ti ti-moon text-2xl dark-toggle-icon-enter"></i>
                            {/if}
                        </button>
                        <!-- Coin Saya (mobile) - Only show for guests in header -->
                        {#if (page.props as any).settings?.coins_enabled}
                            <button
                                onclick={openCoinsModal}
                                class="relative text-white p-1.5 shrink-0"
                                aria-label="Poin Saya"
                            >
                                <i class="ti ti-coins text-2xl"></i>
                            </button>
                        {/if}
                    {/if}

                    <!-- Cart -->
                    <button
                        onclick={goToCart}
                        class="text-white p-1.5 flex items-center justify-center"
                        aria-label="Keranjang"
                    >
                        <div class="relative">
                            <i class="ti ti-shopping-cart text-2xl"></i>
                            {#if localCartCount > 0}
                                <span
                                    class="absolute -top-1.5 -right-2 w-4 h-4 rounded-full text-[8px] font-black flex items-center justify-center text-white border border-white/20 shadow-sm"
                                    style="background-color: {secondary}; font-family: sans-serif;"
                                >
                                    {localCartCount}
                                </span>
                            {/if}
                        </div>
                    </button>

                    <!-- Notifications Bell (Mobile) -->
                    {#if auth}
                        <button
                            onclick={() => {
                                isNotifOpen = !isNotifOpen;
                                profileDropOpen = false;
                            }}
                            class="text-white p-1.5 flex items-center justify-center"
                            aria-label="Notifikasi"
                        >
                            <div class="relative">
                                <i class="ti ti-bell text-2xl"></i>
                                {#if unreadNotifCount > 0}
                                    <span
                                        class="absolute -top-1.5 -right-2 w-4 h-4 rounded-full text-[8px] font-black flex items-center justify-center text-white border border-white/20 shadow-sm"
                                        style="background-color: {secondary}; font-family: sans-serif;"
                                    >
                                        {unreadNotifCount}
                                    </span>
                                {/if}
                            </div>
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
                {#if isMembershipEnabled}
                    <Link
                        href="/membership"
                        prefetch
                        class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                    >
                        <i class="ti ti-id text-lg"></i> Membership Saya
                    </Link>
                {/if}
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
                <Link
                    href="/about"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                    onclick={() => profileDropOpen = false}
                >
                    <i class="ti ti-info-circle text-lg"></i> Tentang Kami
                </Link>
                {#if (page.props as any).settings?.coins_enabled}
                    <button
                        onclick={() => {
                            profileDropOpen = false;
                            openCoinsModal();
                        }}
                        class="w-full flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition text-left font-medium"
                    >
                        <i class="ti ti-coins text-lg text-amber-500"></i> Poin Saya: <span class="font-bold text-slate-900 ml-1">{formatNumber(auth.coins_balance || 0)}</span>
                    </button>
                {/if}
                <button
                    onclick={() => {
                        profileDropOpen = false;
                        toggleDarkMode();
                    }}
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition text-left font-medium"
                >
                    {#if isDark}
                        <i class="ti ti-sun text-lg text-amber-500"></i> Mode Terang
                    {:else}
                        <i class="ti ti-moon text-lg text-indigo-500"></i> Mode Gelap
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
    <main class="flex-grow flex flex-col transition-all duration-300">
        {@render children()}
    </main>

    <!-- ====== FOOTER ====== -->
    <footer
        class="{hideMobileFooter
            ? 'hidden md:block'
            : ''} text-white/95 mt-4 py-4 shadow-[0_-4px_24px_rgba(0,0,0,0.03)] border-t border-white/10"
        style="background: linear-gradient(135deg, {primary}, {secondary});"
    >
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="flex flex-col sm:flex-row items-center justify-between gap-4"
            >
                <!-- Brand -->
                <div class="flex items-center gap-3">
                    {#if storeIcon}
                        <img
                            src={storeIcon}
                            alt={storeName}
                            class="w-8 h-8 object-contain rounded-xl bg-white p-0.5 shadow-sm"
                        />
                    {:else}
                        <div
                            class="w-8 h-8 rounded-xl flex items-center justify-center bg-white shadow-sm"
                            style="color: {primary};"
                        >
                            <i class="ti ti-shopping-bag text-lg animate-pulse"></i>
                        </div>
                    {/if}
                    <span class="font-outfit font-black text-xl text-white tracking-tight"
                        >{storeName}</span
                    >
                </div>

                <!-- Copyright -->
                <p class="text-xs text-white/80 text-center sm:text-left leading-relaxed">
                    © {new Date().getFullYear()}
                    <a
                        href="https://aplikasitokoonline.id/"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="hover:text-white text-white font-bold transition-colors underline decoration-white/35 underline-offset-4 decoration-2"
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
                                class="w-8 h-8 bg-white/15 hover:bg-white/30 rounded-xl flex items-center justify-center transition text-white shadow-sm"
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
                            class="w-8 h-8 bg-white/15 hover:bg-white/30 rounded-xl flex items-center justify-center transition text-white shadow-sm"
                        >
                            <i class="ti ti-brand-instagram text-base"></i>
                        </a>
                        <!-- svelte-ignore a11y_invalid_attribute -->
                        <a
                            href="#"
                            aria-label="WhatsApp"
                            class="w-8 h-8 bg-white/15 hover:bg-white/30 rounded-xl flex items-center justify-center transition text-white shadow-sm"
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
                    <div transition:fade={{ duration: 150 }}>
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
                    <div transition:fade={{ duration: 150 }}>
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
                                        {:else if chat.last_message.body && chat.last_message.body.startsWith('[STICKER]')}
                                            ✨ Stiker
                                        {:else if chat.last_message.body && chat.last_message.body.startsWith('[TRANSACTION_CARD]')}
                                            📄 Invoice Pesanan
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
                            class="group relative flex flex-col w-full {msg.sender_type ===
                            'user'
                                ? 'items-end'
                                : 'items-start'} gap-0.5"
                        >
                            <div
                                class="flex items-center gap-1.5 max-w-[85%] {msg.sender_type ===
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
                                            class="max-w-full w-full rounded-2xl overflow-hidden border shadow-xs {msg.sender_type ===
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
                                            class="max-w-full w-full rounded-2xl overflow-hidden border shadow-xs {msg.sender_type ===
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
                                        {#if msg.body.startsWith('[STICKER]')}
                                            {@const stickerId =
                                                msg.body.replace(
                                                    '[STICKER]',
                                                    '',
                                                )}
                                            {@const stickerData =
                                                stickersList.find(
                                                    (s: any) =>
                                                        s.id === stickerId,
                                                )}
                                            <div
                                                class="relative py-0.5 select-none"
                                            >
                                                {#if stickerData}
                                                    <img
                                                        src={stickerData.url}
                                                        alt={stickerData.name}
                                                        class="w-16 h-16 object-contain transition-transform hover:scale-105 duration-200"
                                                    />
                                                {:else}
                                                    <span class="text-xl"
                                                        >✨</span
                                                    >
                                                {/if}
                                            </div>
                                        {:else if msg.body.startsWith('[TRANSACTION_CARD]')}
                                            {@const card = parseTransactionCard(
                                                msg.body,
                                            )}
                                            {#if card}
                                                <div
                                                    class="p-3.5 rounded-2xl text-[10.5px] leading-relaxed shadow-xs bg-white border border-slate-200 w-full max-w-[240px] text-slate-800 text-left"
                                                >
                                                    <div
                                                        class="flex items-center gap-1 font-black text-[10px] uppercase tracking-wider text-slate-400 mb-1.5"
                                                    >
                                                        <i
                                                            class="ti ti-file-invoice text-xs text-emerald-500"
                                                        ></i>
                                                        <span
                                                            >Invoice Pesanan</span
                                                        >
                                                    </div>
                                                    <div class="space-y-1">
                                                        <p
                                                            class="font-bold text-[11px] text-slate-800 truncate"
                                                        >
                                                            #{card.transaction_number}
                                                        </p>
                                                        <div
                                                            class="h-px bg-slate-100 my-1"
                                                        ></div>
                                                        <div
                                                            class="flex justify-between font-bold text-slate-500"
                                                        >
                                                            <span>Total:</span>
                                                            <span
                                                                style="color: {primary}"
                                                                >{fmt(
                                                                    card.grand_total,
                                                                )}</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="flex justify-between font-bold text-slate-500"
                                                        >
                                                            <span>Status:</span>
                                                            <span
                                                                class="px-1.5 py-0.5 rounded-full text-[8.5px] font-black uppercase"
                                                                style="background-color: {getStatusColor(
                                                                    card.status,
                                                                )}20; color: {getStatusColor(
                                                                    card.status,
                                                                )};"
                                                            >
                                                                {getStatusLabel(
                                                                    card.status,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <Link
                                                        href={`/transactions/${card.id}`}
                                                        class="mt-2.5 w-full py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-250/60 text-slate-700 text-[10px] font-bold rounded-xl transition active:scale-95 flex items-center justify-center gap-1"
                                                    >
                                                        <i class="ti ti-eye"
                                                        ></i>
                                                        Detail Pesanan
                                                    </Link>
                                                </div>
                                            {/if}
                                        {:else}
                                            {@const isEmojiOnly =
                                                /^[\p{Emoji}\s]+$/u.test(
                                                    msg.body,
                                                ) &&
                                                msg.body.trim().length <= 12}
                                            {#if isEmojiOnly}
                                                <div
                                                    class="py-0.5 text-2xl leading-none flex flex-row flex-wrap gap-0.5"
                                                >
                                                    {msg.body}
                                                </div>
                                            {:else}
                                                <div
                                                    class="max-w-full break-words px-3.5 py-2 rounded-2xl text-xs leading-relaxed shadow-3xs {msg.sender_type ===
                                                    'user'
                                                        ? 'rounded-tr-sm text-white'
                                                        : 'rounded-tl-sm text-slate-800 bg-white'}"
                                                    style="background-color: {msg.sender_type ===
                                                    'user'
                                                        ? primary
                                                        : 'white'}; overflow-wrap: anywhere;"
                                                >
                                                    {msg.body}
                                                </div>
                                            {/if}
                                        {/if}
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
                <div class="bg-white border-t border-slate-100 shrink-0">
                    <!-- Attached Image Preview -->
                    {#if attachedImageUrl}
                        <div
                            class="px-4 pb-2 pt-2 border-b border-slate-100 flex justify-start"
                        >
                            <div
                                class="relative inline-block bg-white border border-slate-200 rounded-xl p-1.5 shadow-xs"
                            >
                                <img
                                    src={attachedImageUrl}
                                    alt="Preview"
                                    class="w-14 h-14 rounded-lg object-cover"
                                />
                                <button
                                    onclick={() => {
                                        attachedImage = null;
                                        attachedImageUrl = null;
                                    }}
                                    class="absolute -top-1 -right-1 bg-rose-500 text-white rounded-full w-4 h-4 flex items-center justify-center shadow-xs cursor-pointer"
                                    ><i class="ti ti-x text-[10px]"></i></button
                                >
                            </div>
                        </div>
                    {/if}
                    <div class="px-3.5 py-3 flex items-center gap-2">
                        <button
                            onclick={triggerImageUpload}
                            class="text-slate-400 hover:text-slate-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-50 border border-slate-150 transition cursor-pointer shrink-0"
                            aria-label="Kirim Gambar"
                            title="Kirim Gambar"
                        >
                            <i class="ti ti-photo text-base"></i>
                        </button>
                        <button
                            onclick={openInvoiceSelection}
                            class="text-slate-400 hover:text-slate-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-50 border border-slate-150 transition cursor-pointer shrink-0"
                            aria-label="Kirim Invoice"
                            title="Kirim Invoice"
                        >
                            <i class="ti ti-file-invoice text-base"></i>
                        </button>
                        <button
                            onclick={() => (stickerModalOpen = true)}
                            class="text-slate-400 hover:text-slate-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-50 border border-slate-150 transition cursor-pointer shrink-0"
                            aria-label="Kirim Stiker"
                            title="Kirim Stiker"
                        >
                            <i class="ti ti-sticker text-base"></i>
                        </button>
                        <button
                            onclick={() => (emojiPickerOpen = true)}
                            class="text-slate-400 hover:text-slate-600 w-8 h-8 flex items-center justify-center rounded-full hover:bg-slate-50 border border-slate-150 transition cursor-pointer shrink-0"
                            aria-label="Emoji"
                            title="Sisipkan Emoji"
                        >
                            <i class="ti ti-mood-smile text-base"></i>
                        </button>
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
                            disabled={!chatInput.trim() && !attachedImage}
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

<!-- Sticker Selection Modal -->
{#if stickerModalOpen}
    <div
        class="fixed inset-0 z-[99999] flex items-end sm:items-center justify-center p-0 sm:p-4 animate-fade-in"
    >
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (stickerModalOpen = false)}
            onkeypress={() => (stickerModalOpen = false)}
            role="button"
            tabindex="0"
        ></div>

        <div
            class="bg-white rounded-t-[2.25rem] sm:rounded-[2rem] p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in slide-in-from-bottom sm:zoom-in duration-200 flex flex-col max-h-[80vh]"
        >
            <!-- Pull Indicator for Mobile Bottom Sheet -->
            <div
                class="w-12 h-1 bg-slate-200 rounded-full mx-auto mb-4 sm:hidden shrink-0"
            ></div>

            <div
                class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4 shrink-0"
            >
                <div class="flex flex-col">
                    <h4
                        class="font-outfit font-black text-lg text-slate-800 flex items-center gap-2"
                    >
                        <i class="ti ti-sticker text-violet-500"></i>
                        Pilih Stiker
                    </h4>
                    <p
                        class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5"
                    >
                        Kirim reaksi stiker lucu ke chat
                    </p>
                </div>
                <button
                    onclick={() => (stickerModalOpen = false)}
                    class="p-1.5 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition cursor-pointer"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <div
                class="grid grid-cols-2 gap-4 py-2 flex-grow overflow-y-auto custom-scrollbar pb-6 sm:pb-0"
            >
                {#if stickersList.length === 0}
                    <div class="col-span-2 py-10 text-center text-slate-400">
                        <i
                            class="ti ti-sticker text-4xl block mb-2 text-slate-200"
                        ></i>
                        <p class="text-xs font-bold">Belum ada stiker.</p>
                        <p class="text-[10px] text-slate-300 mt-1">
                            Tambahkan di Master Data → Stiker Chat
                        </p>
                    </div>
                {:else}
                    {#each stickersList as sticker}
                        <button
                            onclick={() => sendSticker(sticker.id)}
                            class="group relative bg-slate-50/50 hover:bg-violet-50/30 border border-slate-100 hover:border-violet-200 rounded-2xl p-4 flex flex-col items-center justify-center gap-2 transition-all duration-200 cursor-pointer active:scale-95 hover:shadow-2xs"
                        >
                            <img
                                src={sticker.url}
                                alt={sticker.name}
                                class="w-20 h-20 object-contain transition-transform group-hover:scale-110 duration-200 select-none"
                            />
                            <span
                                class="text-[10px] font-bold text-slate-400 group-hover:text-violet-600 transition-colors uppercase tracking-wider"
                            >
                                {sticker.name}
                            </span>
                        </button>
                    {/each}
                {/if}
            </div>
        </div>
    </div>
{/if}

<!-- Emoji Picker Panel -->
{#if emojiPickerOpen}
    <div
        class="fixed inset-0 z-[99998] flex items-end sm:items-center justify-center p-0 sm:p-4"
    >
        <div
            class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm"
            onclick={() => (emojiPickerOpen = false)}
            onkeypress={() => (emojiPickerOpen = false)}
            role="button"
            tabindex="0"
        ></div>

        <div
            class="bg-white rounded-t-[2.25rem] sm:rounded-[2rem] p-5 max-w-sm w-full relative z-10 shadow-2xl animate-in fade-in slide-in-from-bottom sm:zoom-in duration-200 flex flex-col max-h-[60vh]"
        >
            <div
                class="w-12 h-1 bg-slate-200 rounded-full mx-auto mb-4 sm:hidden shrink-0"
            ></div>

            <div class="flex items-center justify-between mb-3 shrink-0">
                <h4
                    class="font-outfit font-black text-base text-slate-800 flex items-center gap-2"
                >
                    <i class="ti ti-mood-smile text-amber-500"></i>
                    Emoji
                </h4>
                <button
                    onclick={() => (emojiPickerOpen = false)}
                    class="p-1.5 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <div class="flex-grow overflow-y-auto custom-scrollbar">
                <div class="grid grid-cols-8 gap-0.5">
                    {#each emojiList as emoji}
                        <button
                            onclick={() => insertEmoji(emoji)}
                            class="w-9 h-9 text-xl flex items-center justify-center rounded-xl hover:bg-slate-100 transition active:scale-90 cursor-pointer"
                        >
                            {emoji}
                        </button>
                    {/each}
                </div>
            </div>
        </div>
    </div>
{/if}

<!-- Invoice Selection Modal -->
{#if invoiceModalOpen}
    <div
        class="fixed inset-0 z-[99999] flex items-end sm:items-center justify-center p-0 sm:p-4 animate-fade-in"
    >
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (invoiceModalOpen = false)}
            onkeypress={() => (invoiceModalOpen = false)}
            role="button"
            tabindex="0"
        ></div>

        <div
            class="bg-white rounded-t-[2.25rem] sm:rounded-[2rem] p-6 sm:p-8 max-w-lg w-full relative z-10 shadow-2xl animate-in fade-in slide-in-from-bottom sm:zoom-in duration-200 flex flex-col max-h-[85vh] sm:max-h-[80vh]"
        >
            <!-- Pull Indicator for Mobile Bottom Sheet -->
            <div
                class="w-12 h-1 bg-slate-200 rounded-full mx-auto mb-4 sm:hidden shrink-0"
            ></div>

            <div
                class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4 shrink-0"
            >
                <div class="flex flex-col">
                    <h4
                        class="font-outfit font-black text-lg text-slate-800 flex items-center gap-2"
                    >
                        <i class="ti ti-file-invoice text-emerald-500"></i>
                        Pilih Invoice Pesanan
                    </h4>
                    <p
                        class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5"
                    >
                        Kirim detail transaksi ke chat
                    </p>
                </div>
                <button
                    onclick={() => (invoiceModalOpen = false)}
                    class="p-1.5 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition cursor-pointer"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <div
                class="flex-grow overflow-y-auto space-y-3 pr-1 custom-scrollbar pb-6 sm:pb-0"
            >
                {#if transactionsList.length === 0}
                    <div
                        class="py-16 text-center text-slate-400 flex flex-col items-center justify-center"
                    >
                        <div
                            class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-3"
                        >
                            <i class="ti ti-file-off text-2xl text-slate-300"
                            ></i>
                        </div>
                        <span class="text-xs font-bold"
                            >Belum ada riwayat pesanan</span
                        >
                    </div>
                {:else}
                    {#each transactionsList as trx (trx.id)}
                        <button
                            onclick={() => sendTransactionInvoice(trx)}
                            class="group w-full text-left p-4 border border-slate-100 hover:border-slate-200/80 rounded-2xl hover:bg-slate-50/50 transition-all duration-200 flex items-center justify-between gap-4 cursor-pointer hover:shadow-2xs active:scale-[0.99] focus:outline-none focus:ring-2 focus:ring-slate-100 bg-white"
                        >
                            <div class="min-w-0 flex-grow">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="font-bold text-xs text-slate-800 tracking-tight"
                                        >#{trx.transaction_number}</span
                                    >
                                </div>
                                <p
                                    class="text-[10.5px] text-slate-500 font-medium truncate mt-1.5 flex items-center gap-1.5"
                                >
                                    <i
                                        class="ti ti-shopping-bag text-xs text-slate-400"
                                    ></i>
                                    {trx.items_summary}
                                </p>
                                <div
                                    class="flex items-center gap-2 mt-2.5 flex-wrap"
                                >
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider"
                                        style="background-color: {getStatusColor(
                                            trx.status,
                                        )}12; color: {getStatusColor(
                                            trx.status,
                                        )}; border: 1px solid {getStatusColor(
                                            trx.status,
                                        )}20;"
                                    >
                                        {getStatusLabel(trx.status)}
                                    </span>
                                    <span
                                        class="text-[9.5px] text-slate-400 font-bold flex items-center gap-1"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full bg-slate-250 inline-block"
                                        ></span>
                                        {trx.payment_method}
                                    </span>
                                </div>
                            </div>
                            <div
                                class="text-right shrink-0 flex flex-col items-end justify-between self-stretch"
                            >
                                <span
                                    class="font-black text-xs sm:text-sm"
                                    style="color: {primary}"
                                    >{fmt(trx.grand_total)}</span
                                >
                                <div
                                    class="w-6 h-6 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-slate-100 group-hover:text-slate-600 transition-colors mt-2"
                                >
                                    <i
                                        class="ti ti-chevron-right text-xs transition-transform group-hover:translate-x-0.5"
                                    ></i>
                                </div>
                            </div>
                        </button>
                    {/each}
                {/if}
            </div>
        </div>
    </div>
{/if}

<!-- PWA Install Floating Banner -->
{#if showInstallBanner && isPwaInstallEnabled}
    <div
        class="fixed bottom-20 sm:bottom-6 left-4 right-4 sm:left-auto sm:right-6 max-w-sm bg-white/95 backdrop-blur-md border border-slate-100 rounded-2xl shadow-xl p-4 z-[99999] flex items-center gap-3 animate-in slide-in-from-bottom duration-300"
    >
        <!-- Store Icon -->
        <div
            class="w-12 h-12 rounded-xl overflow-hidden bg-slate-50 flex items-center justify-center shrink-0 border border-slate-100 shadow-2xs"
        >
            {#if storeIcon}
                <img
                    src={formatMiniChatImagePath(storeIcon)}
                    alt={storeAppName}
                    class="w-full h-full object-cover"
                />
            {:else}
                <div
                    class="w-full h-full flex items-center justify-center text-white font-black text-lg"
                    style="background-color: {primary};"
                >
                    {storeAppName.charAt(0).toUpperCase()}
                </div>
            {/if}
        </div>

        <!-- Content -->
        <div class="flex-grow min-w-0">
            <h4
                class="font-outfit font-black text-xs text-slate-800 leading-tight"
            >
                Instal Aplikasi {storeAppName}
            </h4>
            <p
                class="text-[10px] text-slate-500 font-medium leading-normal mt-0.5"
            >
                Tambahkan ke layar utama untuk akses cepat dan nyaman!
            </p>
        </div>

        <!-- Actions -->
        <div class="flex flex-col gap-1.5 shrink-0">
            <button
                onclick={installApp}
                class="px-3 py-1.5 rounded-lg text-[10px] font-black text-white transition active:scale-95 cursor-pointer shadow-xs"
                style="background-color: {primary};"
            >
                Instal
            </button>
            <button
                onclick={dismissInstallBanner}
                class="px-3 py-1.5 rounded-lg text-[10px] font-bold text-slate-400 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 transition active:scale-95 cursor-pointer"
            >
                Nanti
            </button>
        </div>
    </div>
{/if}

<!-- PWA Install Manual Guide Modal -->
{#if showInstallGuideModal}
    <div
        class="fixed inset-0 z-[99999] flex items-end sm:items-center justify-center p-0 sm:p-4 animate-fade-in"
    >
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (showInstallGuideModal = false)}
            onkeypress={() => (showInstallGuideModal = false)}
            role="button"
            tabindex="0"
        ></div>

        <div
            class="bg-white rounded-t-[2.25rem] sm:rounded-[2rem] p-6 sm:p-8 max-w-sm w-full relative z-10 shadow-2xl animate-in fade-in slide-in-from-bottom sm:zoom-in duration-200 flex flex-col"
        >
            <div
                class="w-12 h-1 bg-slate-200 rounded-full mx-auto mb-4 sm:hidden shrink-0"
            ></div>

            <div
                class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4 shrink-0"
            >
                <h4
                    class="font-outfit font-black text-base text-slate-800 flex items-center gap-2"
                >
                    <i class="ti ti-download text-amber-500 text-lg"></i>
                    Petunjuk Instalasi
                </h4>
                <button
                    onclick={() => (showInstallGuideModal = false)}
                    class="p-1.5 hover:bg-slate-100 rounded-xl text-slate-400 hover:text-slate-600 transition cursor-pointer"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <div class="space-y-4 text-xs font-bold text-slate-600">
                {#if isIOS}
                    <div class="flex gap-3 items-start">
                        <span
                            class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] shrink-0"
                            style="background-color: {withOpacity(
                                primary,
                                0.1,
                            )}; color: {primary};">1</span
                        >
                        <p class="leading-relaxed">
                            Buka browser <span class="text-slate-800"
                                >Safari</span
                            > pada iPhone/iPad Anda.
                        </p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span
                            class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] shrink-0"
                            style="background-color: {withOpacity(
                                primary,
                                0.1,
                            )}; color: {primary};">2</span
                        >
                        <p class="leading-relaxed">
                            Ketuk tombol <span class="text-slate-800"
                                >Bagikan</span
                            >
                            (icon kotak dengan panah atas
                            <i
                                class="ti ti-share text-base inline-block align-middle"
                            ></i>).
                        </p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span
                            class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] shrink-0"
                            style="background-color: {withOpacity(
                                primary,
                                0.1,
                            )}; color: {primary};">3</span
                        >
                        <p class="leading-relaxed">
                            Geser ke bawah dan pilih opsi <span
                                class="text-slate-800"
                                >"Tambahkan ke Layar Utama"</span
                            > (Add to Home Screen).
                        </p>
                    </div>
                {:else}
                    <div class="flex gap-3 items-start">
                        <span
                            class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] shrink-0"
                            style="background-color: {withOpacity(
                                primary,
                                0.1,
                            )}; color: {primary};">1</span
                        >
                        <p class="leading-relaxed">
                            Ketuk icon menu <span class="text-slate-800"
                                >titik tiga (<i
                                    class="ti ti-dots-vertical text-base inline-block align-middle"
                                ></i>)</span
                            > di pojok kanan atas browser.
                        </p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <span
                            class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] shrink-0"
                            style="background-color: {withOpacity(
                                primary,
                                0.1,
                            )}; color: {primary};">2</span
                        >
                        <p class="leading-relaxed">
                            Pilih opsi <span class="text-slate-800"
                                >"Instal aplikasi"</span
                            >
                            atau
                            <span class="text-slate-800"
                                >"Tambahkan ke Layar Utama"</span
                            >.
                        </p>
                    </div>
                {/if}
            </div>

            <button
                onclick={() => {
                    showInstallGuideModal = false;
                    showInstallBanner = false;
                    localStorage.setItem(
                        'pwa_install_prompt_dismissed',
                        'true',
                    );
                }}
                class="mt-6 w-full py-3 rounded-2xl text-xs font-black text-white transition active:scale-95 cursor-pointer shadow-xs text-center"
                style="background-color: {primary};"
            >
                Saya Mengerti
            </button>
        </div>
    </div>
{/if}


<OfflineDetector />
