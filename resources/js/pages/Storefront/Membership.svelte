<script lang="ts">
    import { page, router, Link } from '@inertiajs/svelte';
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { showToast } from '@/utils/toast';

    let { levels = [], storeName = 'Bizmate', storeLogo = '' } = $props();

    // Get current customer auth details
    const auth = $derived(page.props.auth?.user);
    const membershipInfo = $derived((page.props as any).membershipInfo);
    
    // Find current active level order
    const currentLevelOrder = $derived(
        membershipInfo?.level?.id 
            ? levels.find((l: any) => l.id === membershipInfo.level.id)?.order ?? 0 
            : 0
    );

    // Track active card index in carousel
    let activeIndex = $state(0);
    
    // Track scanner/barcode modal state
    let showBarcodeModal = $state(false);

    // Card background gradient configuration based on level name
    function getCardGradient(levelName: string): string {
        switch (levelName.toLowerCase()) {
            case 'member':
                return 'linear-gradient(135deg, #475569 0%, #1e293b 100%)';
            case 'silver':
                return 'linear-gradient(135deg, #cbd5e1 0%, #64748b 100%)';
            case 'gold':
                return 'linear-gradient(135deg, #fef08a 0%, #d97706 100%)';
            case 'platinum':
                return 'linear-gradient(135deg, #c084fc 0%, #6d28d9 100%)';
            case 'diamond':
                return 'linear-gradient(135deg, #67e8f9 0%, #0891b2 100%)';
            default:
                return 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)';
        }
    }

    // Format currency to IDR
    function fmtCurrency(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    // Generate unique card member number
    function getMemberCardNumber(): string {
        if (!auth) return 'MEMBER-XXXXXX-XXXX';
        const cleanId = auth.id.replace(/[^0-9]/g, '').slice(0, 6);
        const year = new Date(auth.created_at || new Date()).getFullYear();
        return `BM-${cleanId || '001929'}-${year}`;
    }

    // Centering & Syncing Scroll
    let carouselEl = $state<HTMLDivElement>();
    let isScrollingProgrammatically = false;

    function selectLevel(idx: number) {
        activeIndex = idx;
        if (carouselEl) {
            isScrollingProgrammatically = true;
            const cardEl = carouselEl.children[idx] as HTMLElement;
            if (cardEl) {
                const containerCenter = carouselEl.clientWidth / 2;
                const cardCenter = cardEl.offsetLeft + cardEl.clientWidth / 2;
                carouselEl.scrollTo({
                    left: cardCenter - containerCenter,
                    behavior: 'smooth'
                });
                
                setTimeout(() => {
                    isScrollingProgrammatically = false;
                }, 400);
            }
        }
    }

    function handleCarouselScroll() {
        if (!carouselEl || isScrollingProgrammatically) return;
        
        const scrollLeft = carouselEl.scrollLeft;
        const containerWidth = carouselEl.clientWidth;
        const center = scrollLeft + containerWidth / 2;
        
        let closestIdx = 0;
        let minDistance = Infinity;
        
        Array.from(carouselEl.children).forEach((child: any, idx) => {
            const childCenter = child.offsetLeft + child.clientWidth / 2;
            const distance = Math.abs(center - childCenter);
            if (distance < minDistance) {
                minDistance = distance;
                closestIdx = idx;
            }
        });
        
        if (activeIndex !== closestIdx) {
            activeIndex = closestIdx;
        }
    }
</script>

<svelte:head>
    <title>Membership Saya — {storeName}</title>
</svelte:head>

<StorefrontLayout hideMobileFooter={true}>
    <div class="min-h-screen bg-slate-50 pb-20 dark:bg-slate-900 transition-colors">
        <!-- ── HEADER ── -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30 dark:bg-slate-800 dark:border-slate-700">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <Link
                        href="/"
                        class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full transition flex items-center justify-center text-slate-700 dark:text-slate-200"
                    >
                        <i class="ti ti-arrow-left text-xl"></i>
                    </Link>
                    <h1 class="text-base font-bold text-slate-800 dark:text-slate-100">
                        Membership Saya
                    </h1>
                </div>
                <div class="flex h-8 items-center gap-1 bg-brand-blueRoyal/5 text-brand-blueRoyal font-black text-[10px] rounded-full px-3 dark:bg-brand-blueRoyal/20 dark:text-blue-300">
                    <i class="ti ti-coins text-sm animate-pulse"></i>
                    <span>{auth?.coins ?? 0} Poin</span>
                </div>
            </div>
        </div>

        <main class="max-w-6xl mx-auto px-4 py-6 pb-12">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- ── LEFT COLUMN: CAROUSEL CARD SELECTOR ── -->
                <div class="lg:col-span-6 space-y-4">
                    <div class="flex items-center justify-between px-1">
                        <h2 class="text-xs font-black uppercase text-slate-400 tracking-wider">
                            Pilih Kartu Member
                        </h2>
                        <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            {activeIndex + 1} dari {levels.length} Level
                        </span>
                    </div>

                    <!-- Carousel Wrapper -->
                    <div class="relative w-full overflow-hidden bg-slate-100/50 dark:bg-slate-800/40 rounded-3xl py-6 px-1 border border-slate-100 dark:border-slate-800">
                        <div 
                            bind:this={carouselEl}
                            onscroll={handleCarouselScroll}
                            class="flex overflow-x-auto snap-x snap-mandatory gap-6 py-4 scrollbar-none pl-[calc(50%-160px)] pr-[calc(50%-160px)] sm:pl-[calc(50%-200px)] sm:pr-[calc(50%-200px)] lg:pl-[calc(50%-230px)] lg:pr-[calc(50%-230px)]"
                            style="scroll-behavior: smooth; touch-action: pan-y;"
                        >
                            {#each levels as level, idx (level.id)}
                                {@const isLocked = level.order > currentLevelOrder}
                                {@const isActive = idx === activeIndex}
                                
                                <!-- Card Body -->
                                <div
                                    role="button"
                                    tabindex="0"
                                    onclick={() => selectLevel(idx)}
                                    onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') selectLevel(idx); }}
                                    class="w-[320px] sm:w-[400px] lg:w-[460px] h-52 sm:h-64 shrink-0 snap-center rounded-3xl p-6 sm:p-7 relative overflow-hidden flex flex-col justify-between shadow-xl text-left transition-all duration-300 outline-none border-0 {isActive ? 'scale-[1.02] ring-4 ring-brand-blueRoyal ring-offset-4 dark:ring-offset-slate-900 shadow-brand-blueRoyal/25' : 'scale-95 opacity-40 cursor-pointer'}"
                                    style="background: {getCardGradient(level.name)}; color: {level.name.toLowerCase() === 'silver' ? '#1e293b' : '#ffffff'};"
                                >
                                    <!-- Glossy light overlay -->
                                    <div class="absolute -right-16 -top-16 w-40 h-40 rounded-full bg-white/10 blur-xl pointer-events-none"></div>
                                    <div class="absolute -left-16 -bottom-16 w-36 h-36 rounded-full bg-black/5 blur-xl pointer-events-none"></div>

                                    <!-- Card Header -->
                                    <div class="flex items-start justify-between z-10">
                                        <div>
                                            <p class="text-[9px] sm:text-[11px] font-bold uppercase tracking-widest opacity-85">
                                                {storeName} EXCLUSIVE
                                            </p>
                                            <h3 class="text-xl sm:text-2xl lg:text-3xl font-black italic tracking-wide">
                                                {level.name}
                                            </h3>
                                        </div>
                                        <div class="h-9 w-9 sm:h-12 sm:w-12 rounded-2xl flex items-center justify-center bg-white/15 shadow-inner">
                                            <i class="ti {level.icon || 'ti-award'} text-lg sm:text-2xl"></i>
                                        </div>
                                    </div>

                                    <!-- Barcode / QR Simulation -->
                                    <div class="flex items-center justify-between border-t border-white/10 pt-4 z-10">
                                        <button 
                                            type="button" 
                                            onclick={(e) => {
                                                e.stopPropagation();
                                                if (!isLocked) showBarcodeModal = true;
                                                else showToast('Kartu ini masih terkunci. Belanja lagi untuk membukanya!', 'warning');
                                            }}
                                            class="flex items-center gap-3 cursor-pointer group bg-black/10 hover:bg-black/20 px-3 py-1.5 sm:px-4 sm:py-2 rounded-xl transition border-0 text-white animate-pulse"
                                            style="color: inherit;"
                                        >
                                            <!-- Simulated Barcode Lines -->
                                            <div class="flex gap-[2px] items-center h-6 sm:h-9 py-0.5">
                                                <div class="w-[2px] h-full bg-current"></div>
                                                <div class="w-[1px] h-full bg-current"></div>
                                                <div class="w-[3px] h-full bg-current"></div>
                                                <div class="w-[1px] h-full bg-current"></div>
                                                <div class="w-[2px] h-full bg-current"></div>
                                                <div class="w-[1px] h-full bg-current"></div>
                                                <div class="w-[4px] h-full bg-current"></div>
                                            </div>
                                            <div class="text-[8px] sm:text-[10px] leading-tight font-black font-mono">
                                                <p class="opacity-70 group-hover:opacity-100 transition">LIHAT BARCODE</p>
                                                <p class="opacity-90 tracking-tighter sm:tracking-normal sm:text-xs">{getMemberCardNumber()}</p>
                                            </div>
                                        </button>
                                    </div>

                                    <!-- Card Footer / Name -->
                                    <div class="flex items-end justify-between z-10">
                                        <div>
                                            <p class="text-[8px] sm:text-[10px] uppercase tracking-wider opacity-60">Nama Pemilik</p>
                                            <p class="text-xs sm:text-sm lg:text-base font-black truncate max-w-[180px] sm:max-w-[260px]">
                                                {auth?.name || 'Customer'}
                                            </p>
                                        </div>
                                        <span class="text-[9px] sm:text-[11px] font-black uppercase px-2 py-0.5 sm:px-3 sm:py-1 bg-white/20 rounded-lg tracking-wider">
                                            {#if isLocked}
                                                TERKUNCI
                                            {:else}
                                                AKTIF
                                            {/if}
                                        </span>
                                    </div>

                                    <!-- Locked Overlay (Indomaret Card Lock Concept) -->
                                    {#if isLocked}
                                        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px] flex flex-col items-center justify-center z-20 transition-all pointer-events-none">
                                            <div class="h-10 w-10 sm:h-14 sm:w-14 rounded-full bg-white/90 shadow-md flex items-center justify-center text-slate-800 mb-1.5">
                                                <i class="ti ti-lock text-lg sm:text-2xl"></i>
                                            </div>
                                            <p class="text-[10px] sm:text-xs font-black text-white uppercase tracking-widest">
                                                Belum Terbuka
                                            </p>
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    </div>

                    <!-- Navigation Dot Indicators -->
                    <div class="flex justify-center gap-1.5 mt-2">
                        {#each levels as _, idx}
                            <button
                                type="button"
                                onclick={() => selectLevel(idx)}
                                class="h-1.5 rounded-full transition-all duration-300 {idx === activeIndex ? 'w-5 bg-brand-blueRoyal' : 'w-1.5 bg-slate-300'}"
                            ></button>
                        {/each}
                    </div>
                </div>

                <!-- ── RIGHT COLUMN: ACTIVE STATUS, PROGRESS & BENEFITS ── -->
                <div class="lg:col-span-6 space-y-6">
                    <!-- Current Membership Status -->
                    {#if membershipInfo?.level}
                        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm space-y-4 dark:bg-slate-800 dark:border-slate-700">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl flex items-center justify-center text-white" style="background-color: {membershipInfo.level.badge_color};">
                                    <i class="ti {membershipInfo.level.icon || 'ti-award'} text-xl"></i>
                                </div>
                                <div>
                                    <p class="text-[9px] font-black uppercase text-slate-400 tracking-wider">Level Anda Saat Ini</p>
                                    <h4 class="text-sm font-black text-slate-800 dark:text-slate-100">
                                        {membershipInfo.level.name}
                                    </h4>
                                </div>
                            </div>

                            <!-- Level Up Progress Bar -->
                            {#if membershipInfo.next_level && membershipInfo.progress}
                                <div class="border-t border-slate-50 pt-3 dark:border-slate-700/50 space-y-2">
                                    <div class="flex items-center justify-between text-[11px] font-bold">
                                        <span class="text-slate-500">Progress menuju {membershipInfo.next_level.name}</span>
                                        <span style="color: {membershipInfo.next_level.badge_color};">
                                            {membershipInfo.progress.purchase}%
                                        </span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                        <div 
                                            class="h-full rounded-full transition-all duration-500" 
                                            style="width: {membershipInfo.progress.purchase}%; background-color: {membershipInfo.next_level.badge_color};"
                                        ></div>
                                    </div>
                                    {#if membershipInfo.progress.remaining_purchase > 0}
                                        <p class="text-[10px] text-slate-400 font-semibold flex items-center gap-1 mt-1">
                                            <i class="ti ti-info-circle text-xs text-brand-blueRoyal"></i>
                                            Belanja {fmtCurrency(membershipInfo.progress.remaining_purchase)} lagi untuk naik level.
                                        </p>
                                    {/if}
                                </div>
                            {:else}
                                <div class="border-t border-slate-50 pt-3 dark:border-slate-700/50 text-center py-1">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-black text-amber-600 bg-amber-50 px-3 py-1 rounded-full dark:bg-amber-900/30 dark:text-amber-300">
                                        <i class="ti ti-crown"></i>
                                        Anda berada di Level Tertinggi ({membershipInfo.level.name})
                                    </span>
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Dynamic Level Benefits -->
                    <div class="space-y-3">
                        <div class="px-1">
                            <h2 class="text-xs font-black uppercase text-slate-400 tracking-wider">
                                Keuntungan Level {levels[activeIndex]?.name}
                            </h2>
                        </div>

                        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 dark:bg-slate-800 dark:border-slate-700">
                            {#if levels[activeIndex]?.benefits?.length > 0}
                                <div class="divide-y divide-slate-50 dark:divide-slate-700/50">
                                    {#each levels[activeIndex].benefits as benefit}
                                        <div class="flex items-start gap-3 py-3.5 first:pt-0 last:pb-0">
                                            <div class="h-8 w-8 rounded-lg flex items-center justify-center shrink-0" style="background-color: {levels[activeIndex].badge_color}12; color: {levels[activeIndex].badge_color};">
                                                <i class="ti {benefit.icon || 'ti-circle-check'} text-base"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-black text-slate-800 dark:text-slate-100">
                                                    {benefit.label}
                                                </p>
                                                {#if benefit.type === 'discount_percentage'}
                                                    <p class="text-[10px] text-slate-400 font-medium">
                                                        Potongan diskon otomatis {benefit.value}% di keranjang belanja.
                                                    </p>
                                                {:else if benefit.type === 'point_multiplier'}
                                                    <p class="text-[10px] text-slate-400 font-medium">
                                                        Dapatkan poin koin {benefit.value}x lipat dari transaksi biasa.
                                                    </p>
                                                {:else if benefit.type === 'free_shipping'}
                                                    <p class="text-[10px] text-slate-400 font-medium">
                                                        Subsidi ongkos kirim hingga {fmtCurrency(benefit.value)}.
                                                    </p>
                                                {:else}
                                                    <p class="text-[10px] text-slate-400 font-medium">
                                                        Keuntungan eksklusif khusus untuk level {levels[activeIndex].name}.
                                                    </p>
                                                {/if}
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            {:else}
                                <div class="text-center py-6">
                                    <i class="ti ti-list-details text-3xl text-slate-300 dark:text-slate-600 block mb-1.5"></i>
                                    <p class="text-xs text-slate-400 font-semibold">
                                        Belum ada benefit spesifik untuk level ini.
                                    </p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

            </div>
        </main>

        <!-- ── DIGITAL BARCODE / QR MODAL (Indomaret Member Style) ── -->
        {#if showBarcodeModal}
            <div 
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-[2px] transition-opacity"
                onclick={() => showBarcodeModal = false}
            >
                <div 
                    class="bg-white rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl animate-scale-up dark:bg-slate-800"
                    onclick={(e) => e.stopPropagation()}
                >
                    <!-- Modal Header -->
                    <div 
                        class="p-5 text-white flex items-center justify-between"
                        style="background: {getCardGradient(levels[activeIndex]?.name)};"
                    >
                        <div>
                            <p class="text-[8px] font-black tracking-widest opacity-75">KARTU DIGITAL MEMBER</p>
                            <h3 class="text-base font-black uppercase tracking-wide">{levels[activeIndex]?.name}</h3>
                        </div>
                        <button 
                            type="button" 
                            onclick={() => showBarcodeModal = false}
                            class="h-7 w-7 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition cursor-pointer text-white"
                        >
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>

                    <!-- Modal Body / Barcode Screen -->
                    <div class="p-6 text-center space-y-6">
                        <p class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">
                            Tunjukkan Barcode ke Kasir / Saat Pembayaran
                        </p>

                        <!-- Big Simulated Barcode -->
                        <div class="bg-slate-50 border border-slate-100 rounded-2xl p-6 flex flex-col items-center justify-center space-y-3 dark:bg-slate-900 dark:border-slate-800">
                            <!-- Barcode visual -->
                            <div class="flex gap-[3px] items-center h-20 w-full justify-center text-slate-800 dark:text-slate-200">
                                <div class="w-[3px] h-full bg-current"></div>
                                <div class="w-[1px] h-full bg-current"></div>
                                <div class="w-[4px] h-full bg-current"></div>
                                <div class="w-[2px] h-full bg-current"></div>
                                <div class="w-[1px] h-full bg-current"></div>
                                <div class="w-[5px] h-full bg-current"></div>
                                <div class="w-[1px] h-full bg-current"></div>
                                <div class="w-[3px] h-full bg-current"></div>
                                <div class="w-[2px] h-full bg-current"></div>
                                <div class="w-[1px] h-full bg-current"></div>
                                <div class="w-[4px] h-full bg-current"></div>
                                <div class="w-[1px] h-full bg-current"></div>
                                <div class="w-[3px] h-full bg-current"></div>
                                <div class="w-[5px] h-full bg-current"></div>
                            </div>
                            <span class="text-sm font-black font-mono tracking-widest text-slate-700 dark:text-slate-300">
                                {getMemberCardNumber()}
                            </span>
                        </div>

                        <!-- Info Card -->
                        <div class="text-left bg-blue-50/50 border border-blue-100/50 p-3.5 rounded-xl text-[10px] text-slate-500 font-bold dark:bg-slate-900/30 dark:border-slate-800">
                            <p class="text-brand-blueRoyal uppercase mb-0.5">ℹ️ Catatan Penting</p>
                            <p class="leading-relaxed">
                                Barcode ini berlaku sebagai kartu fisik member Bizmate Anda. Poin belanja akan otomatis terakumulasi langsung setelah pembayaran diverifikasi.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</StorefrontLayout>

<style>
    /* Hide scrollbar utility for clean carousel */
    .scrollbar-none::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-none {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }

    @keyframes scale-up {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    .animate-scale-up {
        animation: scale-up 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>
