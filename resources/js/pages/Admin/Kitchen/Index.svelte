<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import OrderItemModifiers from '@/components/Storefront/OrderItemModifiers.svelte';
    import { showToast } from '@/utils/toast';
    import { onMount, onDestroy } from 'svelte';

    let {
        tickets = {
            pesanan_masuk: [],
            dimasak: [],
            siap_diambil: [],
            selesai: [],
        },
        counts = {
            incoming: 0,
            cooking: 0,
            ready: 0,
            completed: 0,
            total_active: 0,
        },
        isSeller = false,
        selectedRestoId = null,
        restos = [],
        storeName = 'Bizmate Resto',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    let currentTime = $state('');
    let soundEnabled = $state(true);
    let processingTicketId = $state<string | null>(null);
    let autoRefreshTimer: any = null;
    let clockTimer: any = null;

    // Web Audio synthesizer chime for incoming orders
    const playKitchenBell = () => {
        if (!soundEnabled) return;
        try {
            const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.3);

            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.6);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            osc.stop(ctx.currentTime + 0.6);
        } catch {
            // Audio context requires user interaction first
        }
    };

    const updateClock = () => {
        const now = new Date();
        currentTime = now.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }) + ' WIB';
    };

    onMount(() => {
        updateClock();
        clockTimer = setInterval(updateClock, 1000);

        // Auto-refresh KDS tickets every 20 seconds
        autoRefreshTimer = setInterval(() => {
            router.reload({
                only: ['tickets', 'counts'],
                onSuccess: () => {
                    if (counts.incoming > 0) {
                        playKitchenBell();
                    }
                },
            });
        }, 20000);
    });

    onDestroy(() => {
        if (clockTimer) clearInterval(clockTimer);
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    });

    const updateKitchenStatus = (transactionId: string, nextStatus: string) => {
        processingTicketId = transactionId;
        router.post(
            `/admin/kitchen/${transactionId}/update-status`,
            { kitchen_status: nextStatus },
            {
                preserveScroll: true,
                onSuccess: () => {
                    processingTicketId = null;
                    showToast('Status tiket berhasil diperbarui!', 'success', 'top');
                },
                onError: (err) => {
                    processingTicketId = null;
                    const msg = Object.values(err)[0] || 'Gagal memperbarui status';
                    showToast(msg as string, 'error', 'top');
                },
            }
        );
    };

    const manualRefresh = () => {
        router.reload({
            only: ['tickets', 'counts'],
            onSuccess: () => showToast('Data tiket dapur diperbarui', 'info', 'top'),
        });
    };

    const getFulfillmentBadge = (type: string, pickupType?: string, tableNo?: string) => {
        if (type === 'self_pickup') {
            if (pickupType === 'dine_in') {
                return {
                    label: `Dine-In ${tableNo ? `(Meja ${tableNo})` : ''}`,
                    bg: 'bg-purple-50 text-purple-700 border-purple-200',
                    icon: 'ti-tools-kitchen',
                };
            }
            return {
                label: 'Takeaway (Ambil di Resto)',
                bg: 'bg-amber-50 text-amber-700 border-amber-200',
                icon: 'ti-shopping-bag',
            };
        }
        return {
            label: 'Kurir Resto (Delivery)',
            bg: 'bg-blue-50 text-blue-700 border-blue-200',
            icon: 'ti-moped',
        };
    };

    const sendWhatsAppReadyNotice = (ticket: any) => {
        if (!ticket.customer_phone) {
            showToast('Nomor WhatsApp pelanggan tidak tersedia', 'warning', 'top');
            return;
        }
        let phone = ticket.customer_phone.replace(/\D/g, '');
        if (phone.startsWith('0')) phone = '62' + phone.substring(1);

        const text = encodeURIComponent(
            `Halo kak ${ticket.customer_name}, pesanan makanan Anda *#${ticket.transaction_number}* di *${ticket.resto_name || storeName}* sudah matang dan siap diambil/diantar! Silakan nikmati hidangan hangat dari kami. Terima kasih! 🙏🍲`
        );
        window.open(`https://wa.me/${phone}?text=${text}`, '_blank');
    };
</script>

<svelte:head>
    <title>Dapur & KDS — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page Header (Identik dengan Transaksi & Dashboard) -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900">
                        Kitchen Display System (KDS)
                    </h1>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        Live Dapur
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">
                    Monitor antrean masak & pemenuhan pesanan makanan secara real-time
                </p>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center flex-wrap gap-2.5">
                <!-- Clock Pill -->
                <div class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-mono font-semibold text-slate-700 shadow-xs">
                    <i class="ti ti-clock text-slate-400"></i>
                    {currentTime || '12:00:00 WIB'}
                </div>

                <!-- Sound Alert Toggle -->
                <button
                    type="button"
                    onclick={() => {
                        soundEnabled = !soundEnabled;
                        if (soundEnabled) playKitchenBell();
                    }}
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-xs font-semibold transition-all shadow-xs active:scale-95 cursor-pointer {soundEnabled ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}"
                    title="Toggle Suara Notifikasi Dapur"
                >
                    <i class="ti {soundEnabled ? 'ti-bell text-amber-600' : 'ti-bell-off text-slate-400'} text-sm"></i>
                    <span>{soundEnabled ? 'Bel Aktif' : 'Bel Bisu'}</span>
                </button>

                <!-- Refresh Button -->
                <button
                    type="button"
                    onclick={manualRefresh}
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 active:scale-95 transition-all cursor-pointer"
                    title="Segarkan Tiket"
                >
                    <i class="ti ti-refresh text-sm"></i>
                    <span>Segarkan</span>
                </button>

                <!-- Resto Filter (Super Admin) -->
                {#if !isSeller && restos.length > 0}
                    <div class="relative">
                        <select
                            value={selectedRestoId || ''}
                            onchange={(e) => {
                                const val = (e.target as HTMLSelectElement).value;
                                router.get('/admin/kitchen', val ? { resto_id: val } : {}, { preserveState: true });
                            }}
                            class="h-9 rounded-lg border border-slate-200 bg-white px-3 pr-8 text-xs font-semibold text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                        >
                            <option value="">Semua Resto</option>
                            {#each restos as r}
                                <option value={r.id}>{r.name}</option>
                            {/each}
                        </select>
                    </div>
                {/if}
            </div>
        </div>

        <!-- Metric KPI Cards (Format Standar Admin Bizmate) -->
        <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-4">
            <!-- 1. Pesanan Baru -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Pesanan Baru</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{counts.incoming}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 border border-amber-100 text-lg">
                    <i class="ti ti-inbox"></i>
                </div>
            </div>

            <!-- 2. Sedang Dimasak -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Sedang Dimasak</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{counts.cooking}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 border border-blue-100 text-lg">
                    <i class="ti ti-flame"></i>
                </div>
            </div>

            <!-- 3. Siap Diambil / Diantar -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Siap Diambil</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{counts.ready}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 text-lg">
                    <i class="ti ti-package"></i>
                </div>
            </div>

            <!-- 4. Selesai Hari Ini -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Selesai Hari Ini</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{counts.completed}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600 border border-slate-200 text-lg">
                    <i class="ti ti-check"></i>
                </div>
            </div>
        </div>

        <!-- Kanban Board Pipeline (Sesuai Desain Admin UI) -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
            <!-- KOLOM 1: PESANAN BARU -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 flex flex-col min-h-[550px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-inbox text-amber-600 text-base"></i>
                        <span class="text-sm font-semibold text-slate-900">Pesanan Masuk</span>
                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">
                            {tickets.pesanan_masuk.length}
                        </span>
                    </div>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Antrean</span>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto">
                    {#if tickets.pesanan_masuk.length === 0}
                        <div class="text-center py-16 text-slate-400 text-xs italic">
                            Tidak ada pesanan baru masuk
                        </div>
                    {/if}

                    {#each tickets.pesanan_masuk as ticket}
                        {@const badge = getFulfillmentBadge(ticket.fulfillment_type, ticket.pickup_type, ticket.table_number)}
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 transition-all space-y-3">
                            <!-- Ticket Top Bar -->
                            <div class="flex items-start justify-between gap-1">
                                <div>
                                    <span class="text-xs font-mono font-bold text-slate-900 block">
                                        #{ticket.transaction_number}
                                    </span>
                                    <span class="text-[11px] text-slate-500">
                                        {ticket.created_at_formatted} ({ticket.elapsed_minutes}m lalu)
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        onclick={() => window.open(`/admin/kitchen/${ticket.id}/print?format=58mm&type=kitchen`, '_blank')}
                                        class="rounded-md border border-slate-200 bg-white p-1 text-slate-500 hover:bg-slate-50 hover:text-orange-600 transition-colors cursor-pointer"
                                        title="Cetak Tiket Dapur Thermal"
                                    >
                                        <i class="ti ti-printer text-xs"></i>
                                    </button>
                                    <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold {badge.bg}">
                                        <i class="ti {badge.icon}"></i>
                                        {badge.label}
                                    </span>
                                </div>
                            </div>

                            <!-- Scheduled Slot Target -->
                            {#if ticket.scheduled_time_slot}
                                <div class="inline-flex w-full items-center gap-1.5 rounded-lg border border-orange-200 bg-orange-50/70 px-2.5 py-1 text-xs font-semibold text-orange-900">
                                    <i class="ti ti-clock text-orange-600"></i>
                                    <span>Target Siap: {ticket.scheduled_time_slot} WIB</span>
                                </div>
                            {/if}

                            <!-- Customer Info -->
                            <div class="flex items-center gap-1.5 text-xs text-slate-700">
                                <i class="ti ti-user text-slate-400"></i>
                                <span class="font-semibold text-slate-900">{ticket.customer_name}</span>
                                {#if ticket.customer_phone}
                                    <span class="text-slate-500">({ticket.customer_phone})</span>
                                {/if}
                            </div>

                            <!-- Items List -->
                            <div class="border-y border-slate-100 py-2.5 space-y-2 text-xs">
                                {#each ticket.items as item}
                                    <div class="space-y-0.5">
                                        <div class="flex items-baseline justify-between">
                                            <span class="font-bold text-slate-900">{item.quantity}x</span>
                                            <span class="font-medium text-slate-800 flex-1 ml-2">{item.name}</span>
                                        </div>
                                        {#if item.variant_name}
                                            <span class="text-[11px] text-slate-500 block ml-5">({item.variant_name})</span>
                                        {/if}
                                        <OrderItemModifiers {item} compact={true} class="ml-5 mt-1" />
                                    </div>
                                {/each}
                            </div>

                            {#if ticket.notes}
                                <p class="text-[11px] text-slate-600 bg-slate-50 border border-slate-100 p-2 rounded-lg italic">
                                    "{ticket.notes}"
                                </p>
                            {/if}

                            <!-- Action: Start Cooking -->
                            <button
                                type="button"
                                disabled={processingTicketId === ticket.id}
                                onclick={() => updateKitchenStatus(ticket.id, 'dimasak')}
                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90 active:scale-95 disabled:opacity-50 cursor-pointer"
                                style="background-color: {secondary};"
                            >
                                {#if processingTicketId === ticket.id}
                                    <i class="ti ti-loader animate-spin text-sm"></i>
                                    <span>Memproses...</span>
                                {:else}
                                    <i class="ti ti-flame text-sm"></i>
                                    <span>Mulai Masak</span>
                                {/if}
                            </button>
                        </div>
                    {/each}
                </div>
            </div>

            <!-- KOLOM 2: SEDANG DIMASAK -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 flex flex-col min-h-[550px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-flame text-blue-600 text-base"></i>
                        <span class="text-sm font-semibold text-slate-900">Sedang Dimasak</span>
                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-bold text-blue-800">
                            {tickets.dimasak.length}
                        </span>
                    </div>
                    <span class="text-[10px] font-semibold text-blue-600 uppercase tracking-wider animate-pulse">Di Kompor</span>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto">
                    {#if tickets.dimasak.length === 0}
                        <div class="text-center py-16 text-slate-400 text-xs italic">
                            Dapur sedang senggang
                        </div>
                    {/if}

                    {#each tickets.dimasak as ticket}
                        {@const badge = getFulfillmentBadge(ticket.fulfillment_type, ticket.pickup_type, ticket.table_number)}
                        <div class="rounded-xl border border-blue-200 bg-white p-4 shadow-xs hover:border-blue-300 transition-all space-y-3">
                            <div class="flex items-start justify-between gap-1">
                                <div>
                                    <span class="text-xs font-mono font-bold text-slate-900 block">
                                        #{ticket.transaction_number}
                                    </span>
                                    <span class="text-[11px] text-blue-700 font-medium">
                                        Dimasak sejak {ticket.created_at_formatted}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        onclick={() => window.open(`/admin/kitchen/${ticket.id}/print?format=58mm&type=kitchen`, '_blank')}
                                        class="rounded-md border border-slate-200 bg-white p-1 text-slate-500 hover:bg-slate-50 hover:text-orange-600 transition-colors cursor-pointer"
                                        title="Cetak Tiket Dapur Thermal"
                                    >
                                        <i class="ti ti-printer text-xs"></i>
                                    </button>
                                    <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold {badge.bg}">
                                        <i class="ti {badge.icon}"></i>
                                        {badge.label}
                                    </span>
                                </div>
                            </div>

                            {#if ticket.scheduled_time_slot}
                                <div class="inline-flex w-full items-center gap-1.5 rounded-lg border border-orange-200 bg-orange-50/70 px-2.5 py-1 text-xs font-semibold text-orange-900">
                                    <i class="ti ti-clock text-orange-600"></i>
                                    <span>Target: {ticket.scheduled_time_slot} WIB</span>
                                </div>
                            {/if}

                            <div class="flex items-center gap-1.5 text-xs text-slate-700">
                                <i class="ti ti-user text-slate-400"></i>
                                <span class="font-semibold text-slate-900">{ticket.customer_name}</span>
                            </div>

                            <!-- Items -->
                            <div class="border-y border-slate-100 py-2.5 space-y-2 text-xs">
                                {#each ticket.items as item}
                                    <div class="space-y-0.5">
                                        <div class="flex items-baseline justify-between">
                                            <span class="font-bold text-blue-900">{item.quantity}x</span>
                                            <span class="font-medium text-slate-800 flex-1 ml-2">{item.name}</span>
                                        </div>
                                        {#if item.variant_name}
                                            <span class="text-[11px] text-slate-500 block ml-5">({item.variant_name})</span>
                                        {/if}
                                        <OrderItemModifiers {item} compact={true} class="ml-5 mt-1" />
                                    </div>
                                {/each}
                            </div>

                            <!-- Action: Ready -->
                            <button
                                type="button"
                                disabled={processingTicketId === ticket.id}
                                onclick={() => updateKitchenStatus(ticket.id, 'siap_diambil')}
                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90 active:scale-95 disabled:opacity-50 cursor-pointer"
                                style="background-color: {primary};"
                            >
                                {#if processingTicketId === ticket.id}
                                    <i class="ti ti-loader animate-spin text-sm"></i>
                                    <span>Memproses...</span>
                                {:else}
                                    <i class="ti ti-package text-sm"></i>
                                    <span>Selesai Masak & Siap</span>
                                {/if}
                            </button>
                        </div>
                    {/each}
                </div>
            </div>

            <!-- KOLOM 3: SIAP DIAMBIL / ANTAR -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 flex flex-col min-h-[550px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-package text-emerald-600 text-base"></i>
                        <span class="text-sm font-semibold text-slate-900">Siap Diambil</span>
                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800">
                            {tickets.siap_diambil.length}
                        </span>
                    </div>
                    <span class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wider">Siap Saji</span>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto">
                    {#if tickets.siap_diambil.length === 0}
                        <div class="text-center py-16 text-slate-400 text-xs italic">
                            Belum ada pesanan yang siap
                        </div>
                    {/if}

                    {#each tickets.siap_diambil as ticket}
                        {@const badge = getFulfillmentBadge(ticket.fulfillment_type, ticket.pickup_type, ticket.table_number)}
                        <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-xs hover:border-emerald-300 transition-all space-y-3">
                            <div class="flex items-start justify-between gap-1">
                                <div>
                                    <span class="text-xs font-mono font-bold text-slate-900 block">
                                        #{ticket.transaction_number}
                                    </span>
                                    <span class="text-[11px] text-emerald-700 font-medium">
                                        Menunggu diserahkan
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        onclick={() => window.open(`/admin/kitchen/${ticket.id}/print?format=58mm&type=kitchen`, '_blank')}
                                        class="rounded-md border border-slate-200 bg-white p-1 text-slate-500 hover:bg-slate-50 hover:text-orange-600 transition-colors cursor-pointer"
                                        title="Cetak Tiket Dapur Thermal"
                                    >
                                        <i class="ti ti-printer text-xs"></i>
                                    </button>
                                    <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold {badge.bg}">
                                        <i class="ti {badge.icon}"></i>
                                        {badge.label}
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 text-xs text-slate-700">
                                <i class="ti ti-user text-slate-400"></i>
                                <span class="font-semibold text-slate-900">{ticket.customer_name}</span>
                            </div>

                            <!-- Items Summary -->
                            <div class="border-y border-slate-100 py-2 text-xs text-slate-600">
                                {ticket.items.map((i: any) => `${i.quantity}x ${i.name}`).join(', ')}
                            </div>

                            <!-- WhatsApp Notification Button -->
                            {#if ticket.customer_phone}
                                <button
                                    type="button"
                                    onclick={() => sendWhatsAppReadyNotice(ticket)}
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 transition-colors cursor-pointer"
                                >
                                    <i class="ti ti-brand-whatsapp text-sm text-emerald-600"></i>
                                    <span>Kabari Pembeli via WA</span>
                                </button>
                            {/if}

                            <!-- Action: Complete -->
                            <button
                                type="button"
                                disabled={processingTicketId === ticket.id}
                                onclick={() => updateKitchenStatus(ticket.id, 'selesai')}
                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition-opacity hover:opacity-90 active:scale-95 disabled:opacity-50 cursor-pointer"
                            >
                                {#if processingTicketId === ticket.id}
                                    <i class="ti ti-loader animate-spin text-sm"></i>
                                    <span>Memproses...</span>
                                {:else}
                                    <i class="ti ti-check text-sm"></i>
                                    <span>Selesaikan Pesanan</span>
                                {/if}
                            </button>
                        </div>
                    {/each}
                </div>
            </div>

            <!-- KOLOM 4: SELESAI HARI INI -->
            <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3.5 flex flex-col min-h-[550px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-check text-slate-600 text-base"></i>
                        <span class="text-sm font-semibold text-slate-900">Selesai</span>
                        <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-700">
                            {tickets.selesai.length}
                        </span>
                    </div>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Hari Ini</span>
                </div>

                <div class="space-y-2.5 flex-1 overflow-y-auto">
                    {#if tickets.selesai.length === 0}
                        <div class="text-center py-16 text-slate-400 text-xs italic">
                            Belum ada pesanan selesai
                        </div>
                    {/if}

                    {#each tickets.selesai as ticket}
                        <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs opacity-90 hover:opacity-100 transition-opacity text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-mono font-bold text-slate-800">#{ticket.transaction_number}</span>
                                <span class="text-[11px] font-medium text-slate-400">{ticket.created_at_formatted}</span>
                            </div>
                            <p class="text-slate-700 font-medium">{ticket.customer_name}</p>
                            <p class="text-[11px] text-slate-500 truncate">
                                {ticket.items.map((i: any) => `${i.quantity}x ${i.name}`).join(', ')}
                            </p>
                        </div>
                    {/each}
                </div>
            </div>
        </div>
    </main>
</AdminLayout>
