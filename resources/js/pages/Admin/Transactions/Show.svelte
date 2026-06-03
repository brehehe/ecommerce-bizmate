<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        transaction,
        statusLabels = {},
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    // svelte-ignore state_referenced_locally
    let newStatus = $state(transaction.status);
    let cancelReason = $state('');
    let showStatusModal = $state(false);
    let showRejectModal = $state(false);
    let rejectNotes = $state('');
    let isUpdating = $state(false);

    // Resi tracking modal state and method
    let showResiModal = $state(false);
    // svelte-ignore state_referenced_locally
    let resiInput = $state(transaction.tracking_number ?? '');
    // svelte-ignore state_referenced_locally
    let courierInput = $state(transaction.courier_name ?? '');
    let submittingResi = $state(false);

    function submitResi() {
        if (!resiInput.trim()) {
            showToast('Nomor resi tidak boleh kosong.', 'error');
            return;
        }
        submittingResi = true;
        router.post(
            `/admin/transactions/${transaction.id}/tracking`,
            {
                tracking_number: resiInput.trim(),
                courier_name: courierInput.trim() || null,
            },
            {
                onSuccess: () => {
                    showToast(
                        'Nomor resi berhasil disimpan. Status diubah ke Dikirim.',
                        'success',
                    );
                    showResiModal = false;
                },
                onError: (err: any) => {
                    const first = Object.values(err)[0] as string;
                    showToast(first ?? 'Gagal menyimpan nomor resi.', 'error');
                },
                onFinish: () => {
                    submittingResi = false;
                },
            },
        );
    }

    const paymentMethod = $derived(
        transaction.payment_method ?? transaction.paymentMethod,
    );
    const customerAddress = $derived(
        transaction.customer_address ?? transaction.customerAddress,
    );

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    function fmtDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const statusSteps = [
        { key: 'belum_bayar', label: 'Belum Bayar', icon: 'ti-cash' },
        { key: 'menunggu', label: 'Menunggu', icon: 'ti-clock' },
        { key: 'diproses', label: 'Diproses', icon: 'ti-settings' },
        { key: 'dikemas', label: 'Dikemas', icon: 'ti-package' },
        { key: 'out_for_pickup', label: 'Out for Pickup', icon: 'ti-truck-delivery' },
        { key: 'dikirim', label: 'Dikirim', icon: 'ti-truck' },
        { key: 'selesai', label: 'Selesai', icon: 'ti-circle-check' },
    ];

    const statusIndex = $derived(
        transaction.status === 'batal'
            ? -1
            : statusSteps.findIndex((s) => s.key === transaction.status),
    );

    const statusColors: Record<string, { bg: string; text: string }> = {
        belum_bayar: { bg: '#fef3c7', text: '#92400e' },
        menunggu: { bg: '#dbeafe', text: '#1e40af' },
        diproses: { bg: '#ede9fe', text: '#5b21b6' },
        dikemas: { bg: '#cffafe', text: '#0e7490' },
        out_for_pickup: { bg: '#fef3c7', text: '#b45309' }, // Amber-100 / Amber-700
        dikirim: { bg: '#ffedd5', text: '#9a3412' },
        selesai: { bg: '#dcfce7', text: '#166534' },
        batal: { bg: '#fee2e2', text: '#991b1b' },
    };

    const latestPayment = $derived(
        transaction.payments?.[transaction.payments.length - 1] ?? null,
    );

    function updateStatus() {
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/status`,
            {
                status: newStatus,
                cancel_reason: cancelReason,
            },
            {
                onSuccess: () => {
                    showToast(
                        'Status transaksi berhasil diperbarui.',
                        'success',
                    );
                    showStatusModal = false;
                },
                onError: () => {
                    showToast('Gagal memperbarui status.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
    }

    function confirmPayment() {
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/confirm-payment`,
            {},
            {
                onSuccess: () => {
                    showToast('Pembayaran berhasil dikonfirmasi.', 'success');
                },
                onError: () => {
                    showToast('Gagal mengkonfirmasi pembayaran.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
    }

    function rejectPayment() {
        if (!rejectNotes.trim()) {
            showToast('Masukkan alasan penolakan.', 'error');
            return;
        }
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/reject-payment`,
            {
                notes: rejectNotes,
            },
            {
                onSuccess: () => {
                    showToast('Pembayaran ditolak.', 'success');
                    showRejectModal = false;
                    rejectNotes = '';
                },
                onError: () => {
                    showToast('Gagal menolak pembayaran.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
    }

    function formatImagePath(path: string | null | undefined): string {
        if (!path) return '/noimage/image.png';
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        ) {
            return path;
        }
        return '/storage/' + path;
    }

    let pickupTime = $state('');
    let showPickupModal = $state(false);
    let vehicleType = $state('motorcycle');
    let bookingLoading = $state(false);
    let trackingTimeline = $state([]);
    let trackingLoading = $state(false);
    let trackingErr = $state('');

    async function loadAdminTracking() {
        if (!transaction.tracking_number) return;
        trackingLoading = true;
        trackingErr = '';
        try {
            const resp = await fetch(`/admin/transactions/${transaction.id}/komerce/track`);
            const data = await resp.json();
            if (resp.ok && data.success) {
                trackingTimeline = data.history;
            } else {
                trackingErr = data.error ?? 'Gagal memuat history tracking.';
            }
        } catch {
            trackingErr = 'Gagal melacak pengiriman.';
        } finally {
            trackingLoading = false;
        }
    }

    $effect(() => {
        if (transaction.tracking_number) {
            loadAdminTracking();
        }
    });

    function storeKomerceShipment() {
        bookingLoading = true;
        router.post(`/admin/transactions/${transaction.id}/komerce/store`, {}, {
            onSuccess: () => {
                showToast('Booking pengiriman Komerce berhasil!', 'success');
            },
            onError: (err) => {
                const first = Object.values(err)[0] as string;
                showToast(first ?? 'Gagal booking pengiriman.', 'error');
            },
            onFinish: () => {
                bookingLoading = false;
            }
        });
    }

    function cancelKomerceShipment() {
        bookingLoading = true;
        router.post(`/admin/transactions/${transaction.id}/komerce/cancel`, {}, {
            onSuccess: () => {
                showToast('Booking pengiriman berhasil dibatalkan.', 'success');
            },
            onError: (err) => {
                const first = Object.values(err)[0] as string;
                showToast(first ?? 'Gagal membatalkan booking.', 'error');
            },
            onFinish: () => {
                bookingLoading = false;
            }
        });
    }

    function requestPickupKomerce() {
        if (!pickupTime) {
            showToast('Silakan pilih waktu pickup.', 'error');
            return;
        }
        bookingLoading = true;
        router.post(`/admin/transactions/${transaction.id}/komerce/pickup`, {
            pickup_time: pickupTime,
            vehicle_type: vehicleType,
        }, {
            onSuccess: () => {
                showToast('Request pickup berhasil diajukan!', 'success');
                showPickupModal = false;
            },
            onError: (err) => {
                const first = Object.values(err)[0] as string;
                showToast(first ?? 'Gagal mengajukan request pickup.', 'error');
            },
            onFinish: () => {
                bookingLoading = false;
            }
        });
    }
</script>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div class="space-y-6">
            <!-- Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div class="flex items-center gap-3">
                    <a
                        href="/admin/transactions"
                        class="p-2.5 hover:bg-slate-100 rounded-xl border border-slate-200 text-slate-600 transition flex items-center justify-center"
                        aria-label="Kembali ke Transaksi"
                    >
                        <i class="ti ti-arrow-left text-lg"></i>
                    </a>
                    <div>
                        <h3
                            class="font-outfit font-black text-2xl text-slate-800 flex items-center gap-2"
                        >
                            {transaction.transaction_number}
                        </h3>
                        <p
                            class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                        >
                            {fmtDate(transaction.created_at)}
                        </p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-2 self-stretch sm:self-auto justify-end"
                >
                    <span
                        class="text-[10px] font-black px-3 py-1.5 rounded-lg uppercase tracking-wider border"
                        style="background:{(
                            statusColors[transaction.status] ?? {
                                bg: '#f1f5f9',
                                text: '#475569',
                            }
                        ).bg}; color:{(
                            statusColors[transaction.status] ?? {
                                bg: '#f1f5f9',
                                text: '#475569',
                            }
                        ).text}; border-color:{(
                            statusColors[transaction.status] ?? {
                                bg: '#f1f5f9',
                                text: '#475569',
                            }
                        ).text}20;"
                    >
                        {statusLabels[transaction.status] ?? transaction.status}
                    </span>
                    {#if transaction.status !== 'selesai' && transaction.status !== 'batal'}
                        <button
                            onclick={() => (showResiModal = true)}
                            class="px-5 py-2.5 rounded-xl text-xs font-bold text-white transition font-outfit uppercase tracking-wider shadow-lg flex items-center gap-1.5"
                            style="background:{secondary}; box-shadow: 0 4px 12px -2px {secondary}40;"
                        >
                            <i class="ti ti-truck-delivery text-sm"></i>
                            {transaction.tracking_number
                                ? 'Ubah Resi'
                                : 'Input Resi'}
                        </button>
                    {/if}
                    <a
                        href="/admin/transactions/{transaction.id}/print-invoice"
                        target="_blank"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 transition font-outfit uppercase tracking-wider shadow-sm flex items-center gap-1.5 hover:bg-slate-50"
                    >
                        <i class="ti ti-printer text-sm text-slate-500"
                        ></i>Invoice
                    </a>
                    {#if transaction.tracking_number}
                        <a
                            href="/admin/transactions/{transaction.id}/print-shipping-label"
                            target="_blank"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 transition font-outfit uppercase tracking-wider shadow-sm flex items-center gap-1.5 hover:bg-slate-50"
                        >
                            <i class="ti ti-barcode text-sm text-slate-500"
                            ></i>Cetak Resi
                        </a>
                    {/if}
                    <button
                        onclick={() => (showStatusModal = true)}
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-white transition font-outfit uppercase tracking-wider shadow-lg flex items-center gap-1.5"
                        style="background:{primary}; box-shadow: 0 4px 12px -2px {primary}40;"
                    >
                        <i class="ti ti-edit text-sm"></i>Ubah Status
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Status Tracker -->
                    {#if transaction.status !== 'batal'}
                        <div
                            class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
                        >
                            <h3
                                class="font-outfit font-black text-slate-800 text-sm mb-5 uppercase tracking-wider"
                            >
                                Perjalanan Pesanan
                            </h3>
                            <div
                                class="flex items-start justify-between relative px-2"
                            >
                                <div
                                    class="absolute left-6 right-6 top-4 h-0.5 bg-slate-100 z-0"
                                ></div>
                                {#each statusSteps as step, i}
                                    {@const isCompleted = statusIndex >= i}
                                    {@const isCurrent = statusIndex === i}
                                    <div
                                        class="flex flex-col items-center gap-1.5 z-10 flex-1"
                                    >
                                        <div
                                            class="w-8.5 h-8.5 rounded-full flex items-center justify-center border-2 transition-all shadow-sm"
                                            style={isCompleted
                                                ? `background:${primary}; border-color:${primary}`
                                                : 'background:white; border-color:#e2e8f0'}
                                        >
                                            <i
                                                class="ti {step.icon} text-sm"
                                                style={isCompleted
                                                    ? 'color:white'
                                                    : 'color:#94a3b8'}
                                            ></i>
                                        </div>
                                        <span
                                            class="text-[9px] font-bold text-center uppercase tracking-wider"
                                            style={isCurrent
                                                ? `color:${primary}`
                                                : isCompleted
                                                  ? 'color:#64748b'
                                                  : 'color:#94a3b8'}
                                        >
                                            {step.label}
                                        </span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {:else}
                        <div
                            class="bg-rose-50 border border-rose-200/60 rounded-3xl p-6"
                        >
                            <div class="flex items-start gap-3">
                                <div
                                    class="p-2 bg-rose-100 text-rose-600 rounded-xl"
                                >
                                    <i class="ti ti-circle-x text-2xl"></i>
                                </div>
                                <div>
                                    <p
                                        class="font-outfit font-black text-rose-800 text-sm uppercase tracking-wider"
                                    >
                                        Pesanan Dibatalkan
                                    </p>
                                    {#if transaction.cancel_reason}
                                        <p
                                            class="text-xs text-rose-600/90 font-medium mt-1 leading-relaxed whitespace-pre-wrap break-words"
                                        >
                                            {transaction.cancel_reason}
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Payment Proof Review -->
                    {#if latestPayment?.proof_image}
                        <div
                            class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
                        >
                            <h3
                                class="font-outfit font-black text-slate-800 text-sm mb-4 uppercase tracking-wider"
                            >
                                Bukti Pembayaran
                            </h3>
                            <div class="flex flex-col sm:flex-row gap-5">
                                <a
                                    href={formatImagePath(
                                        latestPayment.proof_image,
                                    )}
                                    target="_blank"
                                    class="shrink-0 group relative overflow-hidden rounded-2xl border border-slate-200 shadow-sm block"
                                >
                                    <img
                                        src={formatImagePath(
                                            latestPayment.proof_image,
                                        )}
                                        alt="Bukti Bayar"
                                        class="w-36 h-36 object-cover hover:scale-105 transition duration-300"
                                    />
                                    <div
                                        class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold pointer-events-none"
                                    >
                                        <i class="ti ti-zoom-in text-lg mr-1"
                                        ></i> Lihat
                                    </div>
                                </a>
                                <div
                                    class="flex-1 flex flex-col justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-xs text-slate-400 font-bold uppercase tracking-wider"
                                        >
                                            Tanggal Unggah
                                        </p>
                                        <p
                                            class="text-xs text-slate-600 font-semibold mt-0.5"
                                        >
                                            {fmtDate(
                                                latestPayment.proof_uploaded_at,
                                            )}
                                        </p>

                                        {#if latestPayment.status === 'confirmed'}
                                            <div
                                                class="mt-3 flex items-center gap-2 text-emerald-600"
                                            >
                                                <i
                                                    class="ti ti-circle-check text-lg"
                                                ></i>
                                                <p class="text-sm font-bold">
                                                    Dikonfirmasi oleh {latestPayment
                                                        .confirmedByUser?.name}
                                                </p>
                                            </div>
                                            <p
                                                class="text-[10px] text-slate-400 font-medium mt-0.5 ml-7"
                                            >
                                                {fmtDate(
                                                    latestPayment.confirmed_at,
                                                )}
                                            </p>
                                        {:else if latestPayment.status === 'rejected'}
                                            <div
                                                class="mt-3 flex items-start gap-2 text-rose-600"
                                            >
                                                <i
                                                    class="ti ti-circle-x text-lg mt-0.5"
                                                ></i>
                                                <div>
                                                    <p
                                                        class="text-sm font-bold"
                                                    >
                                                        Pembayaran Ditolak
                                                    </p>
                                                    {#if latestPayment.notes}
                                                        <p
                                                            class="text-xs text-rose-500 font-medium mt-0.5 leading-relaxed whitespace-pre-wrap break-words"
                                                        >
                                                            {latestPayment.notes}
                                                        </p>
                                                    {/if}
                                                </div>
                                            </div>
                                        {:else}
                                            <div
                                                class="mt-3 flex items-center gap-1.5 text-amber-600"
                                            >
                                                <i
                                                    class="ti ti-hourglass text-lg"
                                                ></i>
                                                <p
                                                    class="text-sm font-bold uppercase tracking-wider"
                                                >
                                                    Menunggu Konfirmasi
                                                </p>
                                            </div>
                                        {/if}
                                    </div>

                                    {#if latestPayment.status !== 'confirmed' && latestPayment.status !== 'rejected'}
                                        <div class="flex gap-2 mt-4">
                                            <button
                                                onclick={confirmPayment}
                                                disabled={isUpdating}
                                                class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-600 transition shadow-md shadow-emerald-500/10 uppercase tracking-wider font-outfit disabled:opacity-50"
                                            >
                                                <i class="ti ti-check text-base"
                                                ></i>Konfirmasi
                                            </button>
                                            <button
                                                onclick={() =>
                                                    (showRejectModal = true)}
                                                class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-rose-500 hover:bg-rose-600 transition shadow-md shadow-rose-500/10 uppercase tracking-wider font-outfit"
                                            >
                                                <i class="ti ti-x text-base"
                                                ></i>Tolak
                                            </button>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Items -->
                    <div
                        class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
                    >
                        <div
                            class="px-6 pt-5 pb-3 bg-slate-50/50 border-b border-slate-100"
                        >
                            <h3
                                class="font-outfit font-black text-slate-800 text-sm uppercase tracking-wider"
                            >
                                Produk Dipesan
                            </h3>
                        </div>
                        <div class="divide-y divide-slate-100">
                            {#each transaction.items ?? [] as item}
                                <div
                                    class="p-6 flex gap-4 hover:bg-slate-50/20 transition"
                                >
                                    {#if item.product_image}
                                        <img
                                            src={formatImagePath(
                                                item.product_image,
                                            )}
                                            alt={item.product_name}
                                            class="w-16 h-16 object-cover rounded-2xl shrink-0 border border-slate-200/60 shadow-sm"
                                            onerror={(e: any) => {
                                                e.target.src =
                                                    '/noimage/image.png';
                                            }}
                                        />
                                    {:else}
                                        <div
                                            class="w-16 h-16 rounded-2xl bg-slate-50 shrink-0 flex items-center justify-center border border-slate-200/60"
                                        >
                                            <i
                                                class="ti ti-package text-2xl text-slate-300"
                                            ></i>
                                        </div>
                                    {/if}
                                    <div class="flex-1">
                                        <p
                                            class="text-sm font-bold text-slate-800 leading-snug whitespace-pre-wrap break-words"
                                        >
                                            {item.product_name}
                                        </p>
                                        {#if item.variant_name}
                                            <span
                                                class="inline-block px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-bold rounded mt-1 whitespace-pre-wrap break-words"
                                                >{item.variant_name}</span
                                            >
                                        {/if}
                                        <p
                                            class="text-[10px] text-slate-400 font-bold mt-1 break-all"
                                        >
                                            SKU: {item.product_sku}
                                        </p>
                                        <div
                                            class="grid grid-cols-4 gap-2 mt-3 text-xs"
                                        >
                                            <div>
                                                <p
                                                    class="text-slate-400 font-bold text-[10px] uppercase"
                                                >
                                                    HPP
                                                </p>
                                                <p
                                                    class="font-semibold text-slate-700 mt-0.5"
                                                >
                                                    {fmt(item.hpp)}
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-slate-400 font-bold text-[10px] uppercase"
                                                >
                                                    Harga
                                                </p>
                                                <p
                                                    class="font-semibold text-slate-700 mt-0.5"
                                                >
                                                    {fmt(item.harga_jual)}
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-slate-400 font-bold text-[10px] uppercase"
                                                >
                                                    Diskon
                                                </p>
                                                <p
                                                    class="font-semibold text-slate-700 mt-0.5"
                                                >
                                                    {fmt(item.diskon_item)}
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-slate-400 font-bold text-[10px] uppercase"
                                                >
                                                    Qty
                                                </p>
                                                <p
                                                    class="font-bold text-slate-800 mt-0.5"
                                                >
                                                    ×{item.quantity}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="text-right shrink-0 flex flex-col justify-center"
                                    >
                                        <p
                                            class="text-[10px] text-slate-400 font-bold uppercase"
                                        >
                                            Subtotal
                                        </p>
                                        <p
                                            class="text-sm font-black text-slate-800 mt-0.5"
                                        >
                                            {fmt(item.subtotal)}
                                        </p>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Komerce Shipping Delivery Dashboard -->
                    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6">
                        <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-3">
                            <h3 class="font-outfit font-black text-slate-800 text-sm uppercase tracking-wider flex items-center gap-2">
                                <i class="ti ti-truck text-lg" style="color:{primary}"></i>
                                Komerce Shipping
                            </h3>
                            {#if transaction.booking_code}
                                <span class="text-[9px] font-black px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase tracking-wider">
                                    Booked
                                </span>
                            {:else}
                                <span class="text-[9px] font-black px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200 uppercase tracking-wider">
                                    Ready
                                </span>
                            {/if}
                        </div>

                        <div class="space-y-4">
                            {#if !transaction.booking_code}
                                <p class="text-xs text-slate-500 leading-relaxed">
                                    Pesan kurir pengiriman secara otomatis melalui integrasi Komerce Delivery.
                                </p>
                                <button
                                    onclick={storeKomerceShipment}
                                    disabled={bookingLoading}
                                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-xs font-bold text-white transition active:scale-95 shadow-md shadow-brand-blueRoyal/10"
                                    style="background:{primary}"
                                >
                                    <i class="ti ti-package-export text-sm"></i>
                                    {bookingLoading ? 'Memproses Booking...' : 'Pesan Pengiriman (Komerce)'}
                                </button>
                            {:else}
                                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-3 text-xs space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-slate-400 font-bold uppercase text-[9px]">Kode Booking</span>
                                        <span class="font-mono font-bold text-slate-800 select-all">{transaction.booking_code}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400 font-bold uppercase text-[9px]">Nomor Resi / AWB</span>
                                        <span class="font-mono font-bold text-slate-800 select-all">{transaction.tracking_number ?? '-'}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-400 font-bold uppercase text-[9px]">Kurir</span>
                                        <span class="font-bold text-slate-800 uppercase">{transaction.shipping_courier} ({transaction.shipping_service})</span>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 pt-2">
                                    <!-- Print Label -->
                                    <a
                                        href="/admin/transactions/{transaction.id}/komerce/print"
                                        target="_blank"
                                        class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 transition active:scale-95 shadow-sm"
                                    >
                                        <i class="ti ti-printer text-sm text-slate-500"></i>
                                        Cetak Label Pengiriman
                                    </a>
                                    <!-- Request Pickup -->
                                    {#if ['diproses', 'dikemas', 'out_for_pickup'].includes(transaction.status)}
                                        <button
                                            onclick={() => (showPickupModal = true)}
                                            class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-xs font-bold text-white transition active:scale-95 shadow-md shadow-brand-orange/10"
                                            style="background:{secondary}"
                                        >
                                            <i class="ti ti-calendar-event text-sm"></i>
                                            Request Courier Pickup
                                        </button>
                                    {/if}

                                    <!-- Cancel Booking -->
                                    {#if ['diproses', 'dikemas', 'out_for_pickup'].includes(transaction.status)}
                                        <button
                                            onclick={cancelKomerceShipment}
                                            disabled={bookingLoading}
                                            class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border border-red-200 text-xs font-bold text-red-600 bg-white hover:bg-red-50/50 transition active:scale-[0.98]"
                                        >
                                            <i class="ti ti-trash text-sm text-red-500"></i>
                                            Batalkan Booking Komerce
                                        </button>
                                    {/if}
                                </div>

                                <!-- Live tracking subpanel -->
                                {#if transaction.tracking_number}
                                    <div class="mt-4 pt-4 border-t border-slate-100">
                                        <h4 class="font-bold text-slate-800 text-xs mb-3 flex items-center gap-1.5">
                                            <i class="ti ti-timeline text-slate-400"></i>
                                            Riwayat Perjalanan Paket
                                        </h4>
                                        
                                        {#if trackingLoading}
                                            <div class="space-y-3 py-1">
                                                {#each [1, 2] as _}
                                                    <div class="flex gap-2.5 animate-pulse">
                                                        <div class="w-2 h-2 rounded-full bg-slate-200 mt-1 shrink-0"></div>
                                                        <div class="space-y-1 w-full">
                                                            <div class="h-2.5 bg-slate-200 rounded w-1/4"></div>
                                                            <div class="h-2.5 bg-slate-200 rounded w-5/6"></div>
                                                        </div>
                                                    </div>
                                                {/each}
                                            </div>
                                        {:else if trackingErr}
                                            <p class="text-[10px] text-slate-400 italic">{trackingErr}</p>
                                        {:else if trackingTimeline && trackingTimeline.length > 0}
                                            <div class="relative pl-3 border-l border-slate-100 space-y-3 py-1">
                                                {#each trackingTimeline as step}
                                                    <div class="relative">
                                                        <div class="absolute -left-[16.5px] top-1 w-2 h-2 rounded-full bg-slate-300 border border-white"></div>
                                                        <div>
                                                            <p class="text-[9px] text-slate-400 font-bold">{step.date}</p>
                                                            <p class="text-[11px] text-slate-600 mt-0.5 leading-snug">{step.desc}</p>
                                                        </div>
                                                    </div>
                                                {/each}
                                            </div>
                                        {:else}
                                            <p class="text-[10px] text-slate-400 italic">Belum ada history tracking.</p>
                                        {/if}
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div
                        class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
                    >
                        <h3
                            class="font-outfit font-black text-slate-800 text-sm mb-4 uppercase tracking-wider"
                        >
                            Customer
                        </h3>
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0 shadow-md shadow-brand-blueRoyal/10"
                                style="background:{primary}"
                            >
                                {(transaction.user?.name ?? 'C')
                                    .substring(0, 2)
                                    .toUpperCase()}
                            </div>
                            <div>
                                <p class="font-bold text-slate-800 text-sm">
                                    {transaction.user?.name}
                                </p>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-0.5"
                                >
                                    {transaction.user?.email}
                                </p>
                            </div>
                        </div>
                        {#if customerAddress}
                            <div
                                class="bg-slate-50/60 border border-slate-100 rounded-2xl p-4 text-xs space-y-1"
                            >
                                <p
                                    class="font-bold text-slate-700 whitespace-pre-wrap break-words"
                                >
                                    {customerAddress.receiver_name}
                                </p>
                                <p
                                    class="text-slate-500 font-semibold break-all"
                                >
                                    {customerAddress.phone_number}
                                </p>
                                <p
                                    class="text-slate-600 leading-relaxed font-medium pt-1 border-t border-slate-100 mt-1 whitespace-pre-wrap break-words"
                                >
                                    {customerAddress.full_address}
                                </p>
                                {#if customerAddress.regency_name}
                                    <p
                                        class="text-[11px] text-slate-400 font-semibold mt-0.5"
                                    >
                                        {customerAddress.district_name}, {customerAddress.regency_name},
                                        {customerAddress.province_name}
                                        {customerAddress.postal_code}
                                    </p>
                                {/if}
                                {#if transaction.shipping_courier}
                                    <div
                                        class="mt-2.5 pt-2.5 border-t border-slate-200/60 flex items-center gap-2 text-[11px] text-slate-500"
                                    >
                                        <i
                                            class="ti ti-truck text-slate-400 text-sm"
                                        ></i>
                                        <span
                                            class="font-bold uppercase text-slate-700"
                                            >{transaction.shipping_courier}</span
                                        >
                                        <span
                                            class="font-semibold text-slate-600"
                                            >{transaction.shipping_service}</span
                                        >
                                        {#if transaction.shipping_etd}
                                            <span class="text-slate-400"
                                                >· Est. {transaction.shipping_etd}
                                                hari</span
                                            >
                                        {/if}
                                    </div>
                                {/if}
                                {#if transaction.tracking_number}
                                    <div
                                        class="mt-2 pt-2 border-t border-slate-200/60 text-[11px] text-slate-600"
                                    >
                                        <span
                                            class="font-bold text-slate-400 uppercase tracking-wider block mb-0.5 text-[9px]"
                                            >Nomor Resi</span
                                        >
                                        <span
                                            class="font-mono font-bold text-slate-800"
                                            >{transaction.tracking_number}</span
                                        >
                                        {#if transaction.courier_name}
                                            <span
                                                class="text-slate-400 font-semibold"
                                            >
                                                ({transaction.courier_name})</span
                                            >
                                        {/if}
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <!-- Payment Summary -->
                    <div
                        class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
                    >
                        <h3
                            class="font-outfit font-black text-slate-800 text-sm mb-4 uppercase tracking-wider"
                        >
                            Rincian Pembayaran
                        </h3>
                        <div class="space-y-3 text-xs font-semibold">
                            <div class="flex justify-between text-slate-500">
                                <span>Subtotal</span>
                                <span class="text-slate-800"
                                    >{fmt(transaction.subtotal)}</span
                                >
                            </div>
                            {#if transaction.discount_amount > 0}
                                <div
                                    class="flex justify-between text-emerald-600"
                                >
                                    <span>Diskon Voucher</span>
                                    <span class="font-bold"
                                        >-{fmt(
                                            transaction.discount_amount,
                                        )}</span
                                    >
                                </div>
                            {/if}
                            {#if transaction.coins_value > 0}
                                <div
                                    class="flex justify-between text-emerald-600"
                                >
                                    <span
                                        >Potongan Poin ({new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_redeemed)} Poin)</span
                                    >
                                    <span class="font-bold"
                                        >-{fmt(transaction.coins_value)}</span
                                    >
                                </div>
                            {/if}
                            <div class="flex justify-between text-slate-500">
                                <span
                                    >Ongkir ({transaction.shipping_courier?.toUpperCase()}
                                    {transaction.shipping_service})</span
                                >
                                <span class="text-slate-800"
                                    >{fmt(transaction.shipping_fee)}</span
                                >
                            </div>
                            {#if transaction.shipping_discount > 0}
                                <div
                                    class="flex justify-between text-emerald-600"
                                >
                                    <span>Diskon Ongkir</span>
                                    <span class="font-bold"
                                        >-{fmt(
                                            transaction.shipping_discount,
                                        )}</span
                                    >
                                </div>
                            {/if}
                            {#if transaction.admin_fee > 0}
                                <div
                                    class="flex justify-between text-slate-500"
                                >
                                    <span>Biaya Admin</span>
                                    <span class="text-slate-800"
                                        >{fmt(transaction.admin_fee)}</span
                                    >
                                </div>
                            {/if}
                            {#if transaction.application_fee > 0}
                                <div
                                    class="flex justify-between text-slate-500"
                                >
                                    <span>Biaya Aplikasi</span>
                                    <span class="text-slate-800"
                                        >{fmt(
                                            transaction.application_fee,
                                        )}</span
                                    >
                                </div>
                            {/if}
                            <div
                                class="border-t border-slate-100 pt-3 flex justify-between items-center"
                            >
                                <span
                                    class="font-outfit font-black text-slate-800 text-sm uppercase"
                                    >Total</span
                                >
                                <span
                                    class="font-outfit font-black text-lg"
                                    style="color:{primary}"
                                    >{fmt(transaction.grand_total)}</span
                                >
                            </div>
                        </div>

                        {#if transaction.coins_earned > 0}
                            <div
                                class="mt-4 pt-3.5 border-t border-slate-100 flex items-center gap-1.5 text-xs text-emerald-600 font-bold bg-emerald-50/50 p-2.5 rounded-xl border border-emerald-100/50"
                            >
                                <i class="ti ti-coins text-sm text-amber-500"
                                ></i>
                                <span>
                                    {#if transaction.status === 'selesai'}
                                        Customer mendapatkan {new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_earned)} Poin dari
                                        transaksi ini.
                                    {:else if transaction.status === 'batal'}
                                        Earning {new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_earned)} Poin dibatalkan.
                                    {:else}
                                        Customer akan mendapatkan {new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_earned)} Poin setelah
                                        transaksi selesai.
                                    {/if}
                                </span>
                            </div>
                        {/if}
                        {#if paymentMethod}
                            <div
                                class="mt-4 pt-4 border-t border-slate-100 text-xs text-slate-400 font-bold uppercase tracking-wider flex items-center gap-1"
                            >
                                <i
                                    class="ti ti-credit-card text-sm text-slate-500"
                                ></i>
                                <span class="text-slate-600 font-extrabold"
                                    >{paymentMethod.name}</span
                                >
                                {#if paymentMethod.type === 'manual' && paymentMethod.bank_name}
                                    <span class="text-slate-400"
                                        >· {paymentMethod.bank_name}</span
                                    >
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <!-- Notes -->
                    {#if transaction.notes}
                        <div
                            class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
                        >
                            <h3
                                class="font-outfit font-black text-slate-800 text-sm mb-2.5 uppercase tracking-wider"
                            >
                                Catatan
                            </h3>
                            <p
                                class="text-xs text-slate-600 leading-relaxed font-medium whitespace-pre-wrap break-words"
                            >
                                {transaction.notes}
                            </p>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    {#if showStatusModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            onclick={() => (showStatusModal = false)}
            onkeypress={() => (showStatusModal = false)}
            role="button"
            tabindex="-1"
        >
            <div
                class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            ></div>
            <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                class="relative z-10 bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 w-full max-w-sm"
                onclick={(e: any) => e.stopPropagation()}
                onkeypress={(e: any) => e.stopPropagation()}
                role="document"
            >
                <h3
                    class="font-outfit font-black text-slate-800 text-lg mb-4 uppercase tracking-wider"
                >
                    Ubah Status
                </h3>
                <select
                    bind:value={newStatus}
                    class="w-full px-4 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition mb-4 appearance-none"
                >
                    {#each Object.entries(statusLabels) as [key, label]}
                        <option value={key}>{label as string}</option>
                    {/each}
                </select>
                {#if newStatus === 'batal'}
                    <textarea
                        bind:value={cancelReason}
                        rows="3"
                        placeholder="Masukkan alasan pembatalan..."
                        class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition mb-4 resize-none"
                    ></textarea>
                {/if}
                <div class="flex gap-3">
                    <button
                        onclick={() => (showStatusModal = false)}
                        class="flex-1 py-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 hover:bg-slate-50 transition uppercase tracking-wider font-outfit"
                    >
                        Batal
                    </button>
                    <button
                        onclick={updateStatus}
                        disabled={isUpdating ||
                            (newStatus === 'batal' && !cancelReason.trim())}
                        class="flex-1 py-3 rounded-xl text-xs font-bold text-white transition uppercase tracking-wider font-outfit disabled:opacity-50"
                        style="background:{primary}"
                    >
                        {isUpdating ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Reject Payment Modal -->
    {#if showRejectModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            onclick={() => (showRejectModal = false)}
            onkeypress={() => (showRejectModal = false)}
            role="button"
            tabindex="-1"
        >
            <div
                class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            ></div>
            <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                class="relative z-10 bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 w-full max-w-sm"
                onclick={(e: any) => e.stopPropagation()}
                onkeypress={(e: any) => e.stopPropagation()}
                role="document"
            >
                <h3
                    class="font-outfit font-black text-slate-800 text-lg mb-2 uppercase tracking-wider text-rose-600"
                >
                    Tolak Pembayaran
                </h3>
                <p
                    class="text-xs text-slate-500 mb-4 leading-relaxed font-semibold"
                >
                    Masukkan alasan penolakan. Customer akan diminta untuk
                    mengunggah ulang bukti pembayaran.
                </p>
                <textarea
                    bind:value={rejectNotes}
                    rows="3"
                    placeholder="Masukkan alasan penolakan..."
                    class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition mb-4 resize-none"
                ></textarea>
                <div class="flex gap-3">
                    <button
                        onclick={() => (showRejectModal = false)}
                        class="flex-1 py-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 hover:bg-slate-50 transition uppercase tracking-wider font-outfit"
                    >
                        Batal
                    </button>
                    <button
                        onclick={rejectPayment}
                        disabled={isUpdating}
                        class="flex-1 py-3 rounded-xl text-xs font-bold text-white bg-rose-500 hover:bg-rose-600 transition uppercase tracking-wider font-outfit disabled:opacity-50"
                    >
                        {isUpdating ? 'Memproses...' : 'Tolak'}
                    </button>
                </div>
            </div>
        </div>
    {/if}
    <!-- Input Resi Modal (Single) -->
    {#if showResiModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            onclick={() => (showResiModal = false)}
            onkeypress={() => (showResiModal = false)}
            role="button"
            tabindex="-1"
        >
            <div
                class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            ></div>
            <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                class="relative z-10 bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 w-full max-w-sm"
                onclick={(e: any) => e.stopPropagation()}
                onkeypress={(e: any) => e.stopPropagation()}
                role="document"
            >
                <h3
                    class="font-outfit font-black text-slate-800 text-lg mb-4 uppercase tracking-wider"
                >
                    Input Nomor Resi
                </h3>

                <div
                    class="flex items-start gap-3 p-3 rounded-xl bg-orange-50 border border-orange-200/70 mb-4"
                >
                    <i
                        class="ti ti-info-circle text-sm text-orange-500 mt-0.5 shrink-0"
                    ></i>
                    <p
                        class="text-xs text-orange-700 font-medium leading-relaxed"
                    >
                        Setelah nomor resi disimpan, status transaksi akan <strong
                            >otomatis berubah ke "Dikirim"</strong
                        >.
                    </p>
                </div>

                <div class="space-y-4">
                    <div>
                        <p
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                        >
                            Nomor Resi <span class="text-red-500">*</span>
                        </p>
                        <input
                            type="text"
                            bind:value={resiInput}
                            placeholder="Contoh: JNE1234567890"
                            class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition font-mono font-bold tracking-wider"
                            onkeydown={(e) => e.key === 'Enter' && submitResi()}
                        />
                    </div>

                    <div>
                        <p
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                        >
                            Nama Kurir <span
                                class="text-slate-300 font-normal normal-case"
                                >(opsional)</span
                            >
                        </p>
                        <input
                            type="text"
                            bind:value={courierInput}
                            placeholder="Contoh: JNE, J&T, SiCepat"
                            class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        />
                    </div>
                </div>

                <div class="flex gap-3 mt-5">
                    <button
                        onclick={() => (showResiModal = false)}
                        class="flex-1 py-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 hover:bg-slate-50 transition uppercase tracking-wider font-outfit"
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitResi}
                        disabled={submittingResi || !resiInput.trim()}
                        class="flex-1 py-3 rounded-xl text-xs font-bold text-white transition uppercase tracking-wider font-outfit disabled:opacity-50 flex items-center justify-center gap-1.5"
                        style="background:{secondary}"
                    >
                        {#if submittingResi}
                            <i class="ti ti-loader-2 animate-spin text-sm"></i>
                            Menyimpan...
                        {:else}
                            <i class="ti ti-truck-delivery text-sm"></i>
                            Simpan Resi
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Komerce Pickup Modal -->
    {#if showPickupModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            onclick={() => (showPickupModal = false)}
            onkeypress={() => (showPickupModal = false)}
            role="button"
            tabindex="-1"
        >
            <div
                class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            ></div>
            <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                class="relative z-10 bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 w-full max-w-sm"
                onclick={(e: any) => e.stopPropagation()}
                onkeypress={(e: any) => e.stopPropagation()}
                role="document"
            >
                <h3
                    class="font-outfit font-black text-slate-800 text-sm mb-4 uppercase tracking-wider flex items-center gap-1.5"
                >
                    <i class="ti ti-calendar-event text-base" style="color:{primary}"></i>
                    Request Pickup Kurir
                </h3>

                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                            for="pickup-time-input"
                        >
                            Waktu Pickup <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="pickup-time-input"
                            type="datetime-local"
                            bind:value={pickupTime}
                            class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition text-slate-700"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                            for="vehicle-type-select"
                        >
                            Tipe Kendaraan
                        </label>
                        <select
                            id="vehicle-type-select"
                            bind:value={vehicleType}
                            class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition text-slate-700 font-medium"
                        >
                            <option value="motorcycle">Motor (Regular)</option>
                            <option value="car">Mobil (Cargo/Besar)</option>
                            <option value="truck">Truk (Sangat Besar/Berat &gt;= 10kg)</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button
                        onclick={() => (showPickupModal = false)}
                        class="flex-1 py-3 rounded-xl border border-slate-200 text-xs font-bold text-slate-500 hover:bg-slate-50 transition uppercase tracking-wider font-outfit"
                    >
                        Batal
                    </button>
                    <button
                        onclick={requestPickupKomerce}
                        disabled={bookingLoading || !pickupTime}
                        class="flex-1 py-3 rounded-xl text-xs font-bold text-white transition uppercase tracking-wider font-outfit disabled:opacity-50 flex items-center justify-center gap-1.5"
                        style="background:{secondary}"
                    >
                        {#if bookingLoading}
                            <i class="ti ti-loader-2 animate-spin text-sm"></i>
                            Memproses...
                        {:else}
                            <i class="ti ti-check text-sm"></i>
                            Request Pickup
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>
