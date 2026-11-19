<script lang="ts">
    /* eslint-disable svelte/require-each-key */
    let {
        chatOpen = $bindable(),
        attachedProduct = $bindable(),
        attachedImage = $bindable(),
        attachedImageUrl = $bindable(),
        chatInput = $bindable(),
        attachMenuOpen = $bindable(),
        productAttachOpen = $bindable(),
        productSearchQuery = $bindable(),
        chatPreviewUrl = $bindable(),
        isFoodPick,
        primary,
        storeName,
        chatMessages,
        quickReplies,
        product,
        relatedProducts,
        formatImagePath,
        quickReply,
        sendChat,
        triggerImageUpload,
    }: any = $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            chatPreviewUrl = null;
            productAttachOpen = false;
            attachMenuOpen = false;
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<!-- ============================
         CHAT MODAL (Mobile Full-screen)
         ============================ -->
{#if chatOpen && !isFoodPick}
    <!-- Full-screen overlay -->
    <div
        class="fixed inset-0 z-[200] flex flex-col bg-slate-100 md:hidden animate-slide-up"
        style="padding-bottom: env(safe-area-inset-bottom, 0px);"
    >
        <!-- ── Header ── -->
        <div
            class="bg-white flex items-center gap-3 px-4 py-3 border-b border-slate-100 shadow-sm shrink-0"
        >
            <button
                onclick={() => {
                    chatOpen = false;
                    attachMenuOpen = false;
                    productAttachOpen = false;
                }}
                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition shrink-0"
                aria-label="Tutup chat"
            >
                <i class="ti ti-x text-lg text-slate-700"></i>
            </button>
            <!-- Store avatar -->
            <div
                class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0 shadow"
                style="background-color: {primary};"
            >
                {storeName.charAt(0).toUpperCase()}
            </div>
            <div class="flex-1 min-w-0">
                <p
                    class="font-outfit font-black text-sm text-slate-800 truncate"
                >
                    {storeName}
                </p>
                <p
                    class="text-[10px] text-emerald-500 font-bold flex items-center gap-1"
                >
                    <span
                        class="w-1.5 h-1.5 bg-emerald-400 rounded-full inline-block"
                    ></span>
                    Online
                </p>
            </div>
            <button
                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition"
                aria-label="Opsi"
            >
                <i class="ti ti-dots-vertical text-lg text-slate-500"></i>
            </button>
        </div>

        <!-- ── Chat Body ── -->
        <div
            class="flex-1 overflow-y-auto px-4 py-4 space-y-3 relative chat-body-container"
        >
            <!-- Fraud warning banner -->
            <div
                class="bg-amber-50 border border-amber-100 rounded-2xl px-4 py-3 text-xs text-amber-800 leading-relaxed text-center"
            >
                ⚠️ Hati-hati penipuan! Jangan bertransaksi di luar platform dan
                jangan berikan data pribadi kepada penjual.
            </div>

            <!-- Messages -->
            {#each chatMessages as msg, idx (msg.id ? `${msg.id}-${idx}` : idx)}
                <div
                    class="flex flex-col {msg.sender_type === 'user'
                        ? 'items-end'
                        : 'items-start'} gap-1"
                >
                    {#if msg.attachment_type === 'product' && msg.attachment_data}
                        <!-- Product card bubble -->
                        <div
                            class="max-w-[75%] rounded-2xl overflow-hidden border shadow-sm {msg.sender_type ===
                            'user'
                                ? 'rounded-tr-sm'
                                : 'rounded-tl-sm'}"
                            style="background-color: {msg.sender_type === 'user'
                                ? primary
                                : 'white'};"
                        >
                            <div class="flex items-center gap-2.5 p-3">
                                <img
                                    src={formatImagePath(
                                        msg.attachment_data.image,
                                    )}
                                    alt={msg.attachment_data.name}
                                    class="w-12 h-12 rounded-xl object-cover shrink-0 bg-slate-100"
                                    onerror={(e: any) => {
                                        e.target.src = '/noimage/image.png';
                                    }}
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-bold truncate {msg.sender_type ===
                                        'user'
                                            ? 'text-white'
                                            : 'text-slate-800'}"
                                    >
                                        {msg.attachment_data.name}
                                    </p>
                                    <p
                                        class="text-xs mt-0.5 font-black {msg.sender_type ===
                                        'user'
                                            ? 'text-white/90'
                                            : 'text-orange-500'}"
                                    >
                                        Rp{Number(
                                            msg.attachment_data.price ?? 0,
                                        ).toLocaleString('id-ID')}
                                    </p>
                                </div>
                            </div>
                        </div>
                    {/if}

                    {#if msg.attachment_type === 'image' && msg.attachment_data?.url}
                        <!-- Image card bubble -->
                        <div
                            class="max-w-[75%] rounded-2xl overflow-hidden border shadow-sm {msg.sender_type ===
                            'user'
                                ? 'rounded-tr-sm'
                                : 'rounded-tl-sm'}"
                        >
                            <button
                                type="button"
                                aria-label="Lihat gambar"
                                onclick={() =>
                                    (chatPreviewUrl = msg.attachment_data.url)}
                                class="block w-full text-left p-0 border-0 bg-transparent cursor-pointer"
                            >
                                <img
                                    src={msg.attachment_data.url}
                                    alt="Gambar terlampir"
                                    class="max-w-full max-h-60 object-contain bg-slate-100 rounded-xl"
                                />
                            </button>
                        </div>
                    {/if}

                    {#if msg.body}
                        <div
                            class="max-w-[75%] px-4 py-2.5 rounded-2xl text-sm leading-relaxed shadow-sm {msg.sender_type ===
                            'user'
                                ? 'rounded-tr-sm text-white'
                                : 'rounded-tl-sm text-slate-800 bg-white'}"
                            style="background-color: {msg.sender_type === 'user'
                                ? primary
                                : 'white'};"
                        >
                            {msg.body}
                        </div>
                    {/if}

                    <span class="text-[10px] text-slate-400 px-1"
                        >{msg.time}</span
                    >
                </div>
            {/each}
        </div>

        <!-- ── Quick Replies ── -->
        <div class="flex gap-2 px-4 pb-2 overflow-x-auto shrink-0 no-scrollbar">
            {#each quickReplies as qr}
                <button
                    onclick={() => quickReply(qr)}
                    class="shrink-0 px-3 py-1.5 text-xs font-bold border-2 rounded-full transition active:scale-95 whitespace-nowrap"
                    style="border-color: {primary}; color: {primary}; background: white;"
                >
                    {qr.length > 18 ? qr.slice(0, 16) + '...' : qr}
                </button>
            {/each}
        </div>

        <!-- ── Attached Product Preview ── -->
        {#if attachedProduct}
            <div class="px-4 pb-2 shrink-0">
                <div
                    class="flex items-center gap-2.5 bg-white border border-slate-200 rounded-2xl px-3 py-2 shadow-sm"
                >
                    <img
                        src={formatImagePath(attachedProduct.image)}
                        alt={attachedProduct.name}
                        class="w-10 h-10 rounded-lg object-cover bg-slate-100 shrink-0"
                        onerror={(e: any) => {
                            e.target.src = '/noimage/image.png';
                        }}
                    />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-slate-800 truncate">
                            {attachedProduct.name}
                        </p>
                        <p class="text-xs font-black text-orange-500">
                            Rp{Number(
                                attachedProduct.price ?? 0,
                            ).toLocaleString('id-ID')}
                        </p>
                    </div>
                    <button
                        onclick={() => (attachedProduct = null)}
                        class="text-slate-400 hover:text-slate-600 p-1"
                        aria-label="Hapus lampiran"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
            </div>
        {/if}

        <!-- ── Attached Image Preview ── -->
        {#if attachedImageUrl}
            <div class="px-4 pb-2 shrink-0">
                <div
                    class="relative inline-block bg-white border border-slate-200 rounded-2xl p-2 shadow-sm"
                >
                    <img
                        src={attachedImageUrl}
                        alt="Preview"
                        class="w-20 h-20 rounded-xl object-cover"
                    />
                    <button
                        onclick={() => {
                            attachedImage = null;
                            attachedImageUrl = null;
                        }}
                        class="absolute -top-1.5 -right-1.5 bg-rose-500 text-white hover:bg-rose-600 rounded-full w-5 h-5 flex items-center justify-center shadow"
                        aria-label="Hapus gambar"
                    >
                        <i class="ti ti-x text-xs"></i>
                    </button>
                </div>
            </div>
        {/if}

        <!-- ── Input Bar ── -->
        <div
            class="bg-white border-t border-slate-100 px-4 pt-3 pb-6 sm:pb-3 shrink-0"
        >
            <div class="flex items-center gap-2">
                <!-- Emoji placeholder -->
                <button
                    class="text-slate-400 hover:text-slate-600 w-9 h-9 flex items-center justify-center rounded-full transition"
                    aria-label="Emoji"
                >
                    <i class="ti ti-mood-smile text-xl"></i>
                </button>

                <!-- Text input -->
                <input
                    type="text"
                    bind:value={chatInput}
                    onkeydown={(e: KeyboardEvent) => {
                        if (e.key === 'Enter') {
                            sendChat();
                        }
                    }}
                    placeholder="Tulis pesan..."
                    class="flex-1 bg-slate-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                />

                <!-- Attach (+) -->
                <button
                    onclick={() => {
                        attachMenuOpen = !attachMenuOpen;
                        productAttachOpen = false;
                    }}
                    class="text-slate-400 hover:text-slate-600 w-9 h-9 flex items-center justify-center rounded-full transition {attachMenuOpen
                        ? 'bg-slate-100'
                        : ''}"
                    aria-label="Lampirkan"
                >
                    <i class="ti ti-plus text-xl"></i>
                </button>

                <!-- Send -->
                <button
                    onclick={sendChat}
                    disabled={!chatInput.trim() &&
                        !attachedProduct &&
                        !attachedImage}
                    class="w-10 h-10 rounded-full flex items-center justify-center text-white shadow transition active:scale-95 disabled:opacity-40 cursor-pointer"
                    style="background-color: {primary};"
                    aria-label="Kirim pesan"
                >
                    <i class="ti ti-send text-base"></i>
                </button>
            </div>
        </div>

        <!-- ── Attachment Action Sheet ── -->
        {#if attachMenuOpen}
            <div
                class="bg-white border-t border-slate-100 px-6 py-5 shrink-0 animate-slide-up"
            >
                <div class="flex items-start gap-8">
                    <!-- Produk -->
                    <button
                        onclick={() => {
                            productAttachOpen = true;
                            attachMenuOpen = false;
                            productSearchQuery = '';
                        }}
                        class="flex flex-col items-center gap-2 cursor-pointer"
                    >
                        <div
                            class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                            style="background: linear-gradient(135deg, #3b9ef0, #1d6ee6);"
                        >
                            <i class="ti ti-tag text-white text-2xl"></i>
                        </div>
                        <span class="text-xs font-bold text-slate-700"
                            >Produk</span
                        >
                    </button>

                    <!-- Gambar -->
                    <button
                        onclick={triggerImageUpload}
                        class="flex flex-col items-center gap-2 cursor-pointer"
                    >
                        <div
                            class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                            style="background: linear-gradient(135deg, #f5a623, #e8891d);"
                        >
                            <i class="ti ti-photo text-white text-2xl"></i>
                        </div>
                        <span class="text-xs font-bold text-slate-700"
                            >Gambar</span
                        >
                    </button>

                    <!-- Invoice -->
                    <button
                        class="flex flex-col items-center gap-2 opacity-50 cursor-not-allowed"
                        disabled
                    >
                        <div
                            class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                            style="background: linear-gradient(135deg, #9b5de5, #7b2fbe);"
                        >
                            <i class="ti ti-receipt text-white text-2xl"></i>
                        </div>
                        <span class="text-xs font-bold text-slate-700"
                            >Invoice</span
                        >
                    </button>
                </div>
            </div>
        {/if}
    </div>

    <!-- ── Product Attach Modal ── -->
    {#if productAttachOpen}
        <div
            class="fixed inset-0 z-[210] flex flex-col bg-white md:hidden animate-slide-up"
        >
            <!-- Header -->
            <div
                class="flex items-center gap-3 px-4 py-4 border-b border-slate-100 shrink-0"
            >
                <button
                    onclick={() => {
                        productAttachOpen = false;
                        attachMenuOpen = true;
                    }}
                    class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-full transition"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-lg text-slate-700"></i>
                </button>
                <h2 class="font-outfit font-black text-base text-slate-800">
                    Lampirkan Barang
                </h2>
            </div>

            <!-- Search bar -->
            <div class="px-4 py-3 border-b border-slate-100 shrink-0">
                <div class="relative">
                    <i
                        class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                    ></i>
                    <input
                        type="text"
                        bind:value={productSearchQuery}
                        placeholder="Cari produk di semua etalase"
                        class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                    />
                </div>
            </div>

            <!-- Product List -->
            <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                {#each [product, ...(relatedProducts ?? [])]
                    .filter((p: any) => !productSearchQuery || p.name
                                ?.toLowerCase()
                                .includes(productSearchQuery.toLowerCase()))
                    .slice(0, 20) as p, idx (p.id ? `${p.id}-${idx}` : idx)}
                    <button
                        onclick={() => {
                            attachedProduct = {
                                name: p.name,
                                image:
                                    p.image ||
                                    (p.images?.[0]?.url ?? p.images?.[0]?.path),
                                price: p.price ?? p.product_price?.price ?? 0,
                            };
                            productAttachOpen = false;
                            attachMenuOpen = false;
                        }}
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition text-left"
                    >
                        <img
                            src={formatImagePath(
                                p.image ||
                                    (p.images?.[0]?.url ??
                                        p.images?.[0]?.path) ||
                                    null,
                            )}
                            alt={p.name}
                            class="w-14 h-14 rounded-2xl object-cover bg-slate-100 shrink-0"
                            onerror={(e: any) => {
                                e.target.src = '/noimage/image.png';
                            }}
                        />
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-sm font-bold text-slate-800 line-clamp-2 leading-tight"
                            >
                                {p.name}
                            </p>
                            <p class="text-sm font-black text-orange-500 mt-1">
                                Rp{Number(
                                    p.price ?? p.product_price?.price ?? 0,
                                ).toLocaleString('id-ID')}
                            </p>
                        </div>
                    </button>
                {:else}
                    <div class="py-16 text-center text-slate-400">
                        <i class="ti ti-search text-3xl mb-2"></i>
                        <p class="text-sm font-bold">Produk tidak ditemukan</p>
                    </div>
                {/each}
            </div>
        </div>
    {/if}
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
