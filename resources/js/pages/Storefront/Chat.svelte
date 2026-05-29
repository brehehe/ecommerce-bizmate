<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { usePage, router } from '@inertiajs/svelte';
    import { onMount, onDestroy } from 'svelte';
    import { fade } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    let { chats = [] } = $props();

    const page = usePage();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');
    const storeName = $derived((page.props as any).settings?.store_name ?? 'bizmate');

    let activeChatId = $state<number | null>(null);
    let activeChat = $derived(chats.find((c: any) => c.id === activeChatId) || null);
    let messages = $state<any[]>([]);
    let chatInput = $state('');
    let chatInterval: any = null;

    let attachedImage = $state<File | null>(null);
    let attachedImageUrl = $state<string | null>(null);
    let attachMenuOpen = $state(false);

    // Filter threads
    let searchQuery = $state('');
    let filteredChats = $derived(
        chats.filter((c: any) => 
            !searchQuery || 
            c.subject?.toLowerCase().includes(searchQuery.toLowerCase()) ||
            c.last_message?.body?.toLowerCase().includes(searchQuery.toLowerCase())
        )
    );

    // Check if opening with a specific chat from query param
    onMount(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const chatIdParam = urlParams.get('chat_id');
        if (chatIdParam) {
            const id = parseInt(chatIdParam, 10);
            if (chats.some((c: any) => c.id === id)) {
                selectChat(id);
            }
        } else if (chats.length > 0 && window.innerWidth >= 768) {
            // Auto select first chat on desktop
            selectChat(chats[0].id);
        }
    });

    onDestroy(() => {
        stopPolling();
    });

    async function selectChat(id: number) {
        activeChatId = id;
        messages = [];
        attachedImage = null;
        attachedImageUrl = null;
        attachMenuOpen = false;
        
        // Fetch initial messages
        await fetchMessages();
        
        // Start polling
        startPolling();
    }

    async function fetchMessages() {
        if (!activeChatId) return;
        try {
            const response = await fetch(`/chats/${activeChatId}/messages`, {
                headers: { 'Accept': 'application/json' }
            });
            if (response.ok) {
                const data = await response.json();
                messages = Array.isArray(data) ? data : (data.messages || []);
                setTimeout(scrollToBottom, 50);
            }
        } catch (err) {
            console.error('Error fetching chat messages:', err);
        }
    }

    function startPolling() {
        stopPolling();
        chatInterval = setInterval(async () => {
            if (!activeChatId) return;
            const lastMsgId = messages.length > 0 ? messages[messages.length - 1].id : 0;
            try {
                const response = await fetch(`/chats/${activeChatId}/messages?after_id=${lastMsgId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (response.ok) {
                    const data = await response.json();
                    const newMsgs = Array.isArray(data) ? data : (data.messages || []);
                    const readIds = Array.isArray(data) ? [] : (data.read_ids || []);

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

                    // Reload Inertia props to update thread list & total unread count on storefront navbar
                    router.reload({ only: ['chats', 'chatUnreadCount'], preserveScroll: true });
                }
            } catch (err) {
                console.error('Error polling chat messages:', err);
            }
        }, 2000);
    }

    function stopPolling() {
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
    }

    function scrollToBottom() {
        const el = document.querySelector('.chat-messages-container');
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }

    async function sendMessage() {
        const text = chatInput.trim();
        if (!text && !attachedImage) return;
        if (!activeChatId) return;

        try {
            const formData = new FormData();
            if (text) formData.append('body', text);
            if (attachedImage) {
                formData.append('image', attachedImage);
            }

            chatInput = '';
            attachedImage = null;
            attachedImageUrl = null;
            attachMenuOpen = false;

            const response = await fetch(`/chats/${activeChatId}/messages`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    'Accept': 'application/json',
                },
                body: formData
            });

            if (response.ok) {
                const msg = await response.json();
                if (!messages.some((m: any) => m.id === msg.id)) {
                    messages = [...messages, msg];
                    setTimeout(scrollToBottom, 50);
                }
            }
        } catch (err) {
            console.error('Error sending message:', err);
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
                attachMenuOpen = false;
            }
        };
        input.click();
    }

    function formatImagePath(path: any): string {
        if (!path || typeof path !== 'string') return '/noimage/image.png';
        if (path.startsWith('http://') || path.startsWith('https://')) return path;
        return path.startsWith('/') ? path : '/' + path;
    }

    async function createGeneralChat() {
        try {
            const response = await fetch('/chats', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
                },
                body: JSON.stringify({
                    subject: 'Tanya Penjual'
                })
            });
            if (response.ok) {
                const chat = await response.json();
                router.reload({
                    only: ['chats'],
                    onSuccess: () => {
                        selectChat(chat.id);
                    }
                });
            }
        } catch (err) {
            console.error('Error creating general chat:', err);
        }
     }

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
            router.delete(`/chats/${itemToDeleteId}`, {
                onSuccess: () => {
                    activeChatId = null;
                    messages = [];
                    showToast('Percakapan berhasil dihapus', 'success');
                },
                onError: () => {
                    showToast('Gagal menghapus percakapan', 'error');
                }
            });
        } else {
            try {
                const response = await fetch(`/chats/${activeChatId}/messages/${itemToDeleteId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                        'Accept': 'application/json',
                    }
                });
                if (response.ok) {
                    messages = messages.filter((m: any) => m.id !== itemToDeleteId);
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

<svelte:head>
    <title>Pusat Chat - {storeName}</title>
</svelte:head>

<StorefrontLayout hideMobileHeader={true} hideMobileFooter={true}>
    <div class="max-w-6xl mx-auto px-0 md:px-6 lg:px-8 py-0 md:py-8 w-full flex flex-col">
        
        <!-- Desktop Header Title -->
        <h1 class="hidden md:flex font-outfit font-black text-2xl sm:text-3xl text-slate-800 items-center gap-2.5 mb-6">
            <i class="ti ti-message-dots" style="color: {primary};"></i>
            Pusat Pesan
        </h1>

        <!-- Chat Workspace Area -->
        <div class="flex-grow flex bg-white border-0 md:border border-slate-200/80 rounded-none md:rounded-3xl shadow-none md:shadow-xl overflow-hidden min-h-[calc(100vh-12rem)] md:min-h-[600px] h-[calc(100vh-80px)] md:h-[650px] w-full">
            
            <!-- LEFT PANEL: Thread list -->
            <div 
                class="w-full md:w-80 flex flex-col border-r border-slate-100 shrink-0 bg-slate-50/50
                       {activeChatId ? 'hidden md:flex' : 'flex'}"
            >
                <!-- Mobile Header for thread list -->
                <div class="md:hidden flex items-center justify-between px-4 py-3 border-b border-slate-100 bg-white">
                    <div class="flex items-center gap-3">
                        <button
                            onclick={() => router.visit('/')}
                            class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition shrink-0 cursor-pointer"
                            aria-label="Kembali ke Beranda"
                        >
                            <i class="ti ti-arrow-left text-lg text-slate-700"></i>
                        </button>
                        <span class="font-outfit font-black text-sm text-slate-800">Pusat Pesan</span>
                    </div>
                    <button
                        onclick={createGeneralChat}
                        class="text-xs font-bold text-white px-2.5 py-1.5 rounded-xl transition flex items-center gap-1 cursor-pointer shadow-3xs"
                        style="background-color: {secondary};"
                    >
                        <i class="ti ti-plus text-xs"></i>
                        <span>Tanya</span>
                    </button>
                </div>

                <!-- Search thread -->
                <div class="p-4 bg-white border-b border-slate-100">
                    <div class="relative">
                        <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input
                            type="text"
                            bind:value={searchQuery}
                            placeholder="Cari percakapan..."
                            class="w-full pl-10 pr-4 py-2.5 text-xs bg-slate-100 border-0 rounded-2xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                        />
                    </div>
                </div>

                <!-- Scrollable thread list -->
                <div class="flex-grow overflow-y-auto divide-y divide-slate-100/60 bg-white">
                    {#if filteredChats.length === 0}
                        <div class="py-16 text-center text-slate-400 px-4">
                            <i class="ti ti-message-2 text-3xl mb-2 text-slate-300 block"></i>
                            <span class="text-xs font-bold">Belum ada obrolan</span>
                        </div>
                    {:else}
                        {#each filteredChats as chat (chat.id)}
                            <button
                                onclick={() => selectChat(chat.id)}
                                class="w-full text-left p-4 flex items-start gap-3 hover:bg-slate-50/70 transition duration-150 relative cursor-pointer
                                       {activeChatId === chat.id ? 'bg-slate-50' : ''}"
                            >
                                <!-- Store avatar or Subject symbol -->
                                <div 
                                    class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-black shrink-0 shadow-sm"
                                    style="background-color: {activeChatId === chat.id ? secondary : primary};"
                                >
                                    {storeName.charAt(0).toUpperCase()}
                                </div>

                                <!-- Thread Info info -->
                                <div class="flex-grow min-w-0">
                                    <div class="flex items-center justify-between gap-1 mb-1">
                                        <p class="font-outfit font-black text-xs text-slate-800 truncate">
                                            {chat.subject || storeName}
                                        </p>
                                        {#if chat.last_message_at}
                                            <span class="text-[9px] font-medium text-slate-400 whitespace-nowrap">
                                                {new Date(chat.last_message_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        {/if}
                                    </div>
                                    <p class="text-xs text-slate-500 truncate leading-normal">
                                        {#if chat.last_message}
                                            {#if chat.last_message.sender_type === 'user'}
                                                Anda: 
                                            {/if}
                                            {#if chat.last_message.attachment_type === 'image'}
                                                📷 Kirim gambar
                                            {:else if chat.last_message.attachment_type === 'product'}
                                                📦 Kirim produk
                                            {:else}
                                                {chat.last_message.body || ''}
                                            {/if}
                                        {:else}
                                            Mulai obrolan baru
                                        {/if}
                                    </p>
                                </div>

                                <!-- Unread counts badge / dot -->
                                {#if chat.unread_count > 0}
                                    {#if chat.unread_count === 1}
                                        <span 
                                            class="absolute right-5 bottom-5 w-2.5 h-2.5 rounded-full shadow-sm animate-pulse"
                                            style="background-color: {secondary};"
                                        ></span>
                                    {:else}
                                        <span 
                                            class="absolute right-4 bottom-4 text-[9px] font-black text-white w-5 h-5 rounded-full flex items-center justify-center shadow-sm"
                                            style="background-color: {secondary};"
                                        >
                                            {chat.unread_count}
                                        </span>
                                    {/if}
                                {/if}
                            </button>
                        {/each}
                    {/if}
                </div>
            </div>

            <!-- RIGHT PANEL: Message thread -->
            <div 
                class="flex-grow flex flex-col bg-slate-50 min-w-0 w-full md:w-auto
                       {activeChatId ? 'flex' : 'hidden md:flex'}"
            >
                {#if activeChat}
                    <!-- Header -->
                    <div class="bg-white flex items-center gap-3 px-4 py-3 border-b border-slate-100 shadow-sm shrink-0">
                        <!-- Mobile back button -->
                        <button
                            onclick={() => { activeChatId = null; stopPolling(); }}
                            class="md:hidden w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition shrink-0 cursor-pointer"
                            aria-label="Kembali"
                        >
                            <i class="ti ti-arrow-left text-lg text-slate-700"></i>
                        </button>

                        <div 
                            class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-black shrink-0 shadow-sm"
                            style="background-color: {primary};"
                        >
                            {storeName.charAt(0).toUpperCase()}
                        </div>

                        <div class="flex-grow min-w-0">
                            <h2 class="font-outfit font-black text-sm text-slate-800 truncate">
                                {activeChat.subject || storeName}
                            </h2>
                            <p class="text-[10px] text-emerald-500 font-bold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full inline-block"></span>
                                Toko Online
                            </p>
                        </div>

                        <!-- Shortcut link to product if this chat is about a product -->
                        {#if activeChat.product_id}
                            <button
                                onclick={() => router.visit(`/products/${activeChat.product_id}`)}
                                class="px-3.5 py-1.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 bg-white hover:bg-slate-50 hover:shadow-2xs transition active:scale-95 cursor-pointer shrink-0"
                            >
                                Lihat Barang
                            </button>
                        {/if}

                        <!-- Delete conversation button -->
                        <button
                            onclick={() => confirmDeleteChat(activeChat.id)}
                            class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-rose-50 text-slate-400 hover:text-rose-600 transition duration-150 cursor-pointer shrink-0 border border-slate-200"
                            title="Hapus Percakapan"
                            aria-label="Hapus Percakapan"
                        >
                            <i class="ti ti-trash text-base"></i>
                        </button>
                    </div>

                    <!-- Messages list -->
                    <div class="chat-messages-container flex-grow overflow-y-auto overflow-x-hidden px-4 py-6 pb-20 md:pb-6 space-y-4 relative">
                        <!-- Fraud Warning -->
                        <div class="bg-amber-50 border border-amber-100 rounded-2xl px-4 py-3 text-xs text-amber-800 leading-relaxed text-center max-w-xl mx-auto">
                            ⚠️ Hati-hati penipuan! Selalu lakukan pembayaran resmi lewat platform. Jangan bertransaksi secara personal/direct transfer.
                        </div>

                        {#each messages as msg (msg.id)}
                            <div class="group relative flex flex-col {msg.sender_type === 'user' ? 'items-end' : 'items-start'} gap-1 w-full animate-fade-in">
                                <div class="flex items-center gap-2 max-w-[85%] {msg.sender_type === 'user' ? 'flex-row-reverse' : 'flex-row'}">
                                    <div class="flex flex-col {msg.sender_type === 'user' ? 'items-end' : 'items-start'} gap-1">
                                        <!-- Product Attachment -->
                                        {#if msg.attachment_type === 'product' && msg.attachment_data}
                                            <div 
                                                class="max-w-[100%] rounded-2xl overflow-hidden border shadow-sm cursor-pointer {msg.sender_type === 'user' ? 'rounded-tr-sm' : 'rounded-tl-sm'}"
                                                style="background-color: {msg.sender_type === 'user' ? primary : 'white'};"
                                                onclick={() => msg.attachment_data.id && router.visit(`/products/${msg.attachment_data.id}`)}
                                            >
                                                <div class="flex items-center gap-2.5 p-3">
                                                    <img
                                                        src={formatImagePath(msg.attachment_data.image)}
                                                        alt={msg.attachment_data.name}
                                                        class="w-12 h-12 rounded-xl object-cover shrink-0 bg-slate-100"
                                                        onerror={(e: any) => { e.target.src = '/noimage/image.png'; }}
                                                    />
                                                    <div class="min-w-0">
                                                        <p class="text-xs font-bold truncate {msg.sender_type === 'user' ? 'text-white' : 'text-slate-800'}">{msg.attachment_data.name}</p>
                                                        <p class="text-xs mt-0.5 font-black {msg.sender_type === 'user' ? 'text-white/90' : 'text-orange-500'}">
                                                            Rp{Number(msg.attachment_data.price ?? 0).toLocaleString('id-ID')}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        {/if}

                                        <!-- Image Attachment -->
                                        {#if msg.attachment_type === 'image' && msg.attachment_data?.url}
                                            <div class="max-w-[100%] rounded-2xl overflow-hidden border shadow-sm">
                                                <img
                                                    src={msg.attachment_data.url}
                                                    alt="Sent attachment"
                                                    class="max-w-full max-h-72 object-contain bg-slate-100 cursor-pointer"
                                                    onclick={() => window.open(msg.attachment_data.url, '_blank')}
                                                />
                                            </div>
                                        {/if}

                                        <!-- Body text -->
                                        {#if msg.body}
                                            <div 
                                                class="px-4 py-2.5 rounded-2xl text-xs sm:text-sm leading-relaxed shadow-sm {msg.sender_type === 'user' ? 'rounded-tr-sm text-white' : 'rounded-tl-sm text-slate-800 bg-white'}"
                                                style="background-color: {msg.sender_type === 'user' ? primary : 'white'};"
                                            >
                                                {msg.body}
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Delete Message Button (User only deletes their own message, or any in their thread) -->
                                    <button 
                                        onclick={() => confirmDeleteMessage(msg.id)}
                                        class="opacity-40 md:opacity-0 group-hover:opacity-100 transition-opacity duration-150 p-1.5 rounded-full hover:bg-rose-50 text-slate-400 hover:text-rose-600 cursor-pointer shrink-0"
                                        title="Hapus pesan"
                                    >
                                        <i class="ti ti-trash text-xs"></i>
                                    </button>
                                </div>

                                <div class="flex items-center gap-1 px-1.5">
                                    <span class="text-[9px] text-slate-400">{msg.time}</span>
                                    {#if msg.sender_type === 'user'}
                                        <i class="ti ti-checks text-xs leading-none {msg.is_read ? '' : 'text-slate-400'}" style={msg.is_read ? `color: ${primary};` : ''}></i>
                                    {/if}
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Input section wrapper for mobile fixed bottom layout -->
                    <div class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-100 pb-safe md:relative md:bottom-auto md:left-auto md:right-auto md:z-0 md:border-t-0 md:pb-0 shrink-0">
                        <!-- Selected Attachment Preview -->
                        {#if attachedImageUrl}
                            <div class="px-4 pb-2 shrink-0 bg-white pt-3">
                                <div class="relative inline-block bg-white border border-slate-200 rounded-2xl p-2 shadow-sm">
                                    <img
                                        src={attachedImageUrl}
                                        alt="Preview"
                                        class="w-20 h-20 rounded-xl object-cover"
                                    />
                                    <button
                                        onclick={() => { attachedImage = null; attachedImageUrl = null; }}
                                        class="absolute -top-1.5 -right-1.5 bg-rose-500 text-white hover:bg-rose-600 rounded-full w-5 h-5 flex items-center justify-center shadow cursor-pointer"
                                        aria-label="Hapus"
                                    >
                                        <i class="ti ti-x text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        {/if}

                        <!-- Input Bar -->
                        <div class="bg-white px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <button 
                                    onclick={() => attachMenuOpen = !attachMenuOpen}
                                    class="text-slate-400 hover:text-slate-600 w-10 h-10 flex items-center justify-center rounded-full transition hover:bg-slate-100 cursor-pointer {attachMenuOpen ? 'bg-slate-100' : ''}"
                                    aria-label="Lampirkan"
                                >
                                    <i class="ti ti-plus text-xl"></i>
                                </button>

                                <input
                                    type="text"
                                    bind:value={chatInput}
                                    onkeydown={(e: KeyboardEvent) => { if (e.key === 'Enter') sendMessage(); }}
                                    placeholder="Tulis pesan..."
                                    class="flex-grow bg-slate-100 rounded-full px-5 py-3 text-xs sm:text-sm focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-200 transition"
                                />

                                <button
                                    onclick={sendMessage}
                                    disabled={!chatInput.trim() && !attachedImage}
                                    class="w-10 h-10 rounded-full flex items-center justify-center text-white shadow-md transition active:scale-95 disabled:opacity-40 cursor-pointer shrink-0"
                                    style="background-color: {primary};"
                                    aria-label="Kirim"
                                >
                                    <i class="ti ti-send text-base"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Attachment Menu overlay -->
                        {#if attachMenuOpen}
                            <div class="bg-white border-t border-slate-100 px-6 py-4 shrink-0 animate-slide-up">
                                <div class="flex items-start gap-8">
                                    <button
                                        onclick={triggerImageUpload}
                                        class="flex flex-col items-center gap-2 cursor-pointer"
                                    >
                                        <div 
                                            class="w-12 h-12 rounded-full flex items-center justify-center shadow"
                                            style="background: linear-gradient(135deg, #f5a623, #e8891d);"
                                        >
                                            <i class="ti ti-photo text-white text-xl"></i>
                                        </div>
                                        <span class="text-[10px] font-bold text-slate-700">Gambar</span>
                                    </button>
                                </div>
                            </div>
                        {/if}
                    </div>

                {:else}
                    <!-- Empty State -->
                    <div class="flex-grow flex flex-col items-center justify-center text-center p-8">
                        <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center shadow-md mb-4 text-slate-300">
                            <i class="ti ti-message-circle text-4xl"></i>
                        </div>
                        <h2 class="text-slate-800 font-outfit font-black text-lg mb-1">Pusat Bantuan Chat</h2>
                        <p class="text-slate-400 text-xs max-w-xs leading-relaxed">
                            Silakan pilih obrolan di samping kiri atau buka halaman detail produk untuk mulai chat dengan Admin toko.
                        </p>
                    </div>
                {/if}
            </div>

        </div>

    </div>

    <!-- Delete Confirmation Modal -->
    {#if deleteModalOpen}
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
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
                    {deleteType === 'chat' ? 'Hapus Percakapan?' : 'Hapus Pesan?'}
                </h4>
                <p class="text-sm text-center text-slate-500 font-medium mb-8">
                    {#if deleteType === 'chat'}
                        Seluruh obrolan ini akan terhapus secara <strong>permanen</strong> dan tidak dapat dikembalikan.
                    {:else}
                        Pesan ini akan terhapus secara <strong>permanen</strong> dan tidak dapat dikembalikan.
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
</StorefrontLayout>

<style>
    /* Prevent scrollbar overlaying on quick replies */
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
