<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { usePage, router, Link } from '@inertiajs/svelte';
    import { onMount, onDestroy } from 'svelte';

    let { chats = { data: [], links: [] }, totalUnread = 0 } = $props();

    const page = usePage();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    const storeName = $derived(
        (page.props as any).settings?.store_name || 'Bizmate',
    );

    let searchQuery = $state('');
    let pollInterval: any = null;

    onMount(() => {
        const urlParams = new URLSearchParams(window.location.search);
        searchQuery = urlParams.get('search') || '';

        // Poll chat list and unread counts every 2 seconds for realtime updates
        pollInterval = setInterval(() => {
            router.reload({
                only: ['chats', 'totalUnread', 'adminChatUnreadCount'],
                preserveScroll: true,
            });
        }, 2000);
    });

    onDestroy(() => {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
    });

    function handleSearch(e: Event) {
        e.preventDefault();
        router.get(
            '/admin/chats',
            { search: searchQuery },
            { preserveState: true },
        );
    }

    function resetSearch() {
        searchQuery = '';
        router.get('/admin/chats', {}, { preserveState: true });
    }

    function formatImagePath(path: any): string {
        if (!path || typeof path !== 'string') return '/noimage/image.png';
        if (path.startsWith('http://') || path.startsWith('https://'))
            return path;
        return path.startsWith('/') ? path : '/' + path;
    }

    function formatAvatarPath(path: any): string {
        if (!path || typeof path !== 'string') return '';
        if (path.startsWith('http://') || path.startsWith('https://'))
            return path;
        if (path.startsWith('/storage/')) return path;
        if (path.startsWith('storage/')) return '/' + path;
        return path.startsWith('/') ? '/storage' + path : '/storage/' + path;
    }
</script>

<svelte:head>
    <title>Chat — {storeName}</title>
</svelte:head>

<AdminLayout>
    <div class="flex h-[calc(100dvh-3.5rem)] w-full overflow-hidden">

        <!-- Chat list sidebar -->
        <div class="flex w-full flex-col border-r border-slate-200 bg-white md:w-80 lg:w-96">

            <!-- Header -->
            <div class="flex h-14 items-center justify-between border-b border-slate-100 px-4">
                <div class="flex items-center gap-2">
                    <h1 class="text-sm font-semibold text-slate-800">Pesan</h1>
                    {#if totalUnread > 0}
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold text-white" style="background-color: {secondaryColor};">
                            {totalUnread}
                        </span>
                    {/if}
                </div>
            </div>

            <!-- Search -->
            <div class="border-b border-slate-100 px-3 py-2.5">
                <form onsubmit={handleSearch} class="relative">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                    <input
                        type="search"
                        placeholder="Cari percakapan..."
                        bind:value={searchQuery}
                        class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-8 text-xs text-slate-700 placeholder-slate-400 focus:border-slate-300 focus:bg-white focus:outline-none transition-colors"
                    />
                    {#if searchQuery}
                        <button
                            type="button"
                            onclick={resetSearch}
                            aria-label="Reset pencarian"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                        >
                            <i class="ti ti-x text-xs"></i>
                        </button>
                    {/if}
                </form>
            </div>

            <!-- Chat list -->
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                {#if chats.data.length === 0}
                    <div class="flex flex-col items-center justify-center py-16 text-center px-6">
                        <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                            <i class="ti ti-message-circle-off text-xl"></i>
                        </div>
                        <p class="text-sm font-medium text-slate-600">Belum ada percakapan</p>
                        <p class="mt-1 text-xs text-slate-400">
                            {searchQuery ? 'Coba kata kunci lain' : 'Chat dari pelanggan akan muncul di sini'}
                        </p>
                    </div>
                {:else}
                    {#each chats.data as chat}
                        <Link
                            href="/admin/chats/{chat.id}"
                            class="flex items-start gap-3 px-4 py-3.5 transition-colors hover:bg-slate-50 {chat.has_unread ? 'bg-blue-50/40' : ''} border-b border-slate-100 last:border-0"
                        >
                            <!-- Avatar -->
                            <div class="relative shrink-0">
                                {#if chat.user?.avatar}
                                    <img
                                        src={formatAvatarPath(chat.user.avatar)}
                                        alt={chat.user?.name}
                                        class="h-9 w-9 rounded-full object-cover"
                                    />
                                {:else}
                                    <div
                                        class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold uppercase text-white"
                                        style="background-color: {primaryColor};"
                                    >
                                        {chat.user?.name?.substring(0, 2) || '??'}
                                    </div>
                                {/if}
                                {#if chat.has_unread}
                                    <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full border-2 border-white" style="background-color: {secondaryColor};"></span>
                                {/if}
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm {chat.has_unread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700'}">
                                        {chat.user?.name || 'Pelanggan'}
                                    </p>
                                    <span class="shrink-0 text-[10px] text-slate-400">{chat.last_message_at ? new Date(chat.last_message_at).toLocaleTimeString("id-ID", {hour:"2-digit",minute:"2-digit"}) : ""}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs {chat.has_unread ? 'font-medium text-slate-600' : 'text-slate-400'}">
                                    {typeof chat.last_message === "object" ? (chat.last_message?.body || chat.subject || "Percakapan baru") : (chat.last_message || chat.subject || "Percakapan baru")}
                                </p>
                            </div>
                        </Link>
                    {/each}
                {/if}
            </div>
        </div>

        <!-- Empty state (desktop) -->
        <div class="hidden flex-1 flex-col items-center justify-center bg-slate-50 md:flex">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-slate-300 shadow-xs mb-4">
                <i class="ti ti-messages text-3xl"></i>
            </div>
            <p class="text-sm font-semibold text-slate-700">Pusat Pesan Pelanggan</p>
            <p class="mt-1.5 max-w-xs text-center text-xs leading-relaxed text-slate-400">
                Pilih percakapan di sebelah kiri untuk mulai membalas pesan pelanggan.
            </p>
        </div>

    </div>
</AdminLayout>
