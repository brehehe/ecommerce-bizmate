<script>
    let { product, user, isFoodPick, openChat, openWhatsApp } = $props();
</script>

<!-- ─────────────────────────────────────────────────────
     SELLER INFO CARD
 ───────────────────────────────────────────────────── -->
{#if product.seller || product.user}
    {@const sellerObj = product.seller || product.user}
    <div class="max-w-6xl mx-auto px-0 sm:px-6 lg:px-8 w-full min-w-0">
        <div
            class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-100 shadow-sm p-4 sm:p-5 flex flex-col gap-3"
        >
            <!-- Seller identity row -->
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div
                        class="w-11 h-11 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center shrink-0 overflow-hidden text-sm border border-slate-200 shadow-sm"
                    >
                        {#if sellerObj.avatar}
                            <img
                                src="/storage/{sellerObj.avatar}"
                                alt={sellerObj.store_name || sellerObj.name}
                                class="w-full h-full object-cover"
                            />
                        {:else}
                            {(sellerObj.store_name || sellerObj.name)
                                .substring(0, 2)
                                .toUpperCase()}
                        {/if}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            {#if sellerObj.store_slug}
                                <a
                                    href="/{sellerObj.store_slug}"
                                    class="font-bold text-sm text-slate-800 hover:text-blue-600 truncate transition"
                                >
                                    {sellerObj.store_name || sellerObj.name}
                                </a>
                            {:else}
                                <span
                                    class="font-bold text-sm text-slate-800 truncate"
                                    >{sellerObj.store_name ||
                                        sellerObj.name}</span
                                >
                            {/if}
                            <span
                                class="px-2 py-0.5 text-[9px] font-black bg-blue-100 text-blue-700 rounded-full shrink-0"
                                >Penjual</span
                            >
                        </div>
                        {#if sellerObj.store_slug}
                            <p
                                class="text-[10px] text-blue-500 font-mono mt-0.5 truncate"
                            >
                                /{sellerObj.store_slug}
                            </p>
                        {/if}
                        {#if user && (product.contact_name || product.contact_phone)}
                            <div
                                class="mt-1 flex flex-wrap items-center gap-3 text-xs"
                            >
                                {#if product.contact_name}
                                    <span
                                        class="flex items-center gap-1 text-slate-600 font-medium"
                                    >
                                        <i
                                            class="ti ti-user text-emerald-600 text-sm"
                                        ></i>
                                        Kontak:
                                        <strong class="text-slate-800 font-bold"
                                            >{product.contact_name}</strong
                                        >
                                    </span>
                                {/if}
                                {#if product.contact_phone}
                                    <span
                                        class="flex items-center gap-1 text-slate-600 font-medium"
                                    >
                                        <i
                                            class="ti ti-phone text-blue-600 text-sm"
                                        ></i>
                                        No. HP/WA:
                                        <strong class="text-slate-800 font-bold"
                                            >{product.contact_phone}</strong
                                        >
                                    </span>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>
                <!-- Desktop buttons (hidden on mobile) -->
                <div class="hidden sm:flex items-center gap-2 shrink-0">
                    {#if sellerObj.store_slug}
                        <a
                            href="/{sellerObj.store_slug}"
                            class="px-3 py-1.5 text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 rounded-xl transition flex items-center gap-1.5 shadow-sm"
                        >
                            <i
                                class="ti ti-building-store text-sm text-blue-600"
                            ></i>
                            <span>Produk</span>
                        </a>
                    {/if}
                    {#if !isFoodPick}
                        <button
                            type="button"
                            onclick={openChat}
                            class="px-3 py-1.5 text-xs font-bold text-blue-600 bg-blue-50 border border-blue-200 hover:bg-blue-100 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-sm"
                        >
                            <i class="ti ti-message text-sm"></i>
                            <span>Chat</span>
                        </button>
                        <button
                            type="button"
                            onclick={openWhatsApp}
                            class="px-3 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 border border-emerald-600 rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-xs active:scale-[0.98]"
                            title="Tanya via WhatsApp"
                        >
                            <i class="ti ti-brand-whatsapp text-sm text-white"
                            ></i>
                            <span>WhatsApp</span>
                        </button>
                    {/if}
                </div>
            </div>

            <!-- Mobile action buttons (visible only on mobile) -->
            <div class="flex sm:hidden items-center gap-2">
                {#if sellerObj.store_slug}
                    <a
                        href="/{sellerObj.store_slug}"
                        class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 rounded-xl transition shadow-sm"
                    >
                        <i class="ti ti-building-store text-sm text-blue-600"
                        ></i>
                        Produk
                    </a>
                {/if}
                {#if !isFoodPick}
                    <button
                        type="button"
                        onclick={openChat}
                        class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-bold text-blue-600 bg-blue-50 border border-blue-200 hover:bg-blue-100 rounded-xl transition cursor-pointer shadow-sm"
                    >
                        <i class="ti ti-message text-sm"></i>
                        Chat
                    </button>
                    <button
                        type="button"
                        onclick={openWhatsApp}
                        class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 border border-emerald-600 rounded-xl transition cursor-pointer shadow-xs active:scale-[0.98]"
                        title="Tanya via WhatsApp"
                    >
                        <i class="ti ti-brand-whatsapp text-sm text-white"></i>
                        WhatsApp
                    </button>
                {/if}
            </div>
        </div>
    </div>
{/if}
