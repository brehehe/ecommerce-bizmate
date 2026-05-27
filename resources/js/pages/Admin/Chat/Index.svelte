<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

    let { chats = { data: [], links: [] }, totalUnread = 0 } = $props();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    let searchQuery = $state('');

    onMount(() => {
        const urlParams = new URLSearchParams(window.location.search);
        searchQuery = urlParams.get('search') || '';
    });

    function handleSearch(e: Event) {
        e.preventDefault();
        router.get('/admin/chats', { search: searchQuery }, { preserveState: true });
    }

    function resetSearch() {
        searchQuery = '';
        router.get('/admin/chats', {}, { preserveState: true });
    }
</script>

<svelte:head>
    <title>Pusat Chat Admin - bizmate</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-6 flex flex-col h-[calc(100vh-5rem)] min-h-0 overflow-hidden w-full max-w-[1600px] mx-auto">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-4 shrink-0">
            <div>
                <h1 class="font-outfit font-black text-xl text-slate-800 flex items-center gap-2">
                    <i class="ti ti-message-dots" style="color: {primaryColor};"></i>
                    Pusat Obrolan (Inbox)
                </h1>
                <p class="text-[10px] text-slate-400 font-semibold mt-0.5">
                    Kelola dan balas pertanyaan pelanggan secara realtime.
                </p>
            </div>
            
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black bg-orange-50 text-orange-600 rounded-xl">
                    <i class="ti ti-mail-unread text-xs"></i>
                    {totalUnread} Obrolan Belum Dibaca
                </span>
            </div>
        </div>

        <!-- Unified Workspace (Split Screen) -->
        <div class="flex-grow flex-1 min-h-0 flex bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm w-full">
            
            <!-- LEFT PANEL: Conversation List -->
            <div class="w-full md:w-85 border-r border-slate-200 flex flex-col shrink-0 min-h-0">
                <!-- Search bar -->
                <div class="px-4 py-3 border-b border-slate-200 bg-white shrink-0 h-[65px] flex items-center w-full">
                    <form onsubmit={handleSearch} class="relative w-full">
                        <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
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
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold"
                            >
                                Batal
                            </button>
                        {/if}
                    </form>
                </div>

                <!-- Scrollable thread list -->
                <div class="flex-grow overflow-y-auto p-2.5 space-y-1 bg-white min-h-0 custom-scrollbar">
                    {#if chats.data.length === 0}
                        <div class="py-16 text-center text-slate-400 px-4">
                            <i class="ti ti-message-off text-3xl mb-2 text-slate-300 block"></i>
                            <span class="text-xs font-bold">Tidak ada percakapan</span>
                        </div>
                    {:else}
                        {#each chats.data as c (c.id)}
                            <Link
                                href={`/admin/chats/${c.id}`}
                                class="w-full text-left p-3 flex items-start gap-3 hover:bg-slate-50 rounded-2xl transition duration-150 relative cursor-pointer block"
                            >
                                <div 
                                    class="w-10 h-10 rounded-full flex items-center justify-center text-white text-xs font-black shrink-0 shadow-sm"
                                    style="background-color: {c.unread_count > 0 ? secondaryColor : primaryColor};"
                                >
                                    {c.user.name.charAt(0).toUpperCase()}
                                </div>

                                <div class="flex-grow min-w-0">
                                    <div class="flex items-center justify-between gap-1 mb-1">
                                        <p class="font-outfit font-black text-xs text-slate-800 truncate">
                                            {c.user.name}
                                        </p>
                                        {#if c.last_message_at}
                                            <span class="text-[9px] font-medium text-slate-400 whitespace-nowrap">
                                                {new Date(c.last_message_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        {/if}
                                    </div>
                                    <p class="text-xs text-slate-500 truncate leading-normal">
                                        {#if c.last_message}
                                            {#if c.last_message.sender_type === 'admin'}
                                                Anda: 
                                            {/if}
                                            {#if c.last_message.attachment_type === 'image'}
                                                📷 Gambar
                                            {:else if c.last_message.attachment_type === 'product'}
                                                📦 Produk
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
                    <div class="p-3 bg-slate-50 border-t border-slate-200 flex items-center justify-center gap-1 shrink-0">
                        {#each chats.links.filter((l: any) => l.label.includes('Prev') || l.label.includes('Next') || l.active) as link}
                            {#if link.url}
                                <Link
                                    href={link.url}
                                    class="px-2.5 py-1.5 rounded-lg text-[10px] font-bold transition duration-150 active:scale-95 cursor-pointer
                                           {link.active ? 'text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'}"
                                    style={link.active ? `background-color: ${primaryColor};` : ''}
                                >
                                    {@html link.label.replace('Previous', 'Prev')}
                                </Link>
                            {/if}
                        {/each}
                    </div>
                {/if}
            </div>

            <!-- RIGHT PANEL: Thread Detail (Empty State for Index) -->
            <div class="flex-grow flex flex-col bg-slate-50/50 min-h-0 w-full md:w-auto min-w-0 hidden md:flex">
                <!-- Top Toolbar Placeholder Header to match search bar height -->
                <div class="bg-white border-b border-slate-200 h-[65px] shrink-0 w-full"></div>
                
                <div class="flex-grow flex flex-col justify-center items-center text-center p-8">
                    <div class="w-20 h-20 rounded-full bg-white flex items-center justify-center shadow-sm mb-4 text-slate-300">
                        <i class="ti ti-brand-hipchat text-4xl"></i>
                    </div>
                    <h2 class="text-slate-800 font-outfit font-black text-base mb-1">Pusat Pesan Pelanggan</h2>
                    <p class="text-slate-400 text-xs max-w-xs leading-relaxed">
                        Silakan klik salah satu daftar obrolan di sebelah kiri untuk melihat pesan dan mulai membalas chat pelanggan secara realtime.
                    </p>
                </div>
            </div>

        </div>

    </div>
</AdminLayout>
