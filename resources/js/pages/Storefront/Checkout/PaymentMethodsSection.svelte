<script lang="ts">
    import MidtransMethodGroup from './MidtransMethodGroup.svelte';

    let {
        primary,
        paymentMethods,
        midtransEnabledMethods,
        selectedPaymentMethod,
        midtransMethodColors,
        midtransMethodIcons,
        selectedPaymentMethodId = $bindable(),
        selectedMidtransMethodKey = $bindable(),
    }: {
        primary: string;
        paymentMethods: any[];
        midtransEnabledMethods: any[];
        selectedPaymentMethod: any;
        midtransMethodColors: Record<string, string>;
        midtransMethodIcons: Record<string, string>;
        selectedPaymentMethodId: number | string | null;
        selectedMidtransMethodKey: string | null;
    } = $props();

    const visiblePaymentMethods = $derived(
        paymentMethods.filter(
            (paymentMethod: any) =>
                midtransEnabledMethods.length === 0 ||
                !paymentMethod.name?.toLowerCase().includes('midtrans'),
        ),
    );
    const bankMethods = $derived(
        midtransEnabledMethods.filter(
            (method: any) =>
                method.type === 'bank_transfer' || method.type === 'echannel',
        ),
    );
    const ewalletMethods = $derived(
        midtransEnabledMethods.filter(
            (method: any) =>
                method.type === 'qris' ||
                method.type === 'gopay' ||
                method.type === 'shopeepay',
        ),
    );
    const cstoreMethods = $derived(
        midtransEnabledMethods.filter(
            (method: any) => method.type === 'cstore',
        ),
    );
    const cardMethods = $derived(
        midtransEnabledMethods.filter(
            (method: any) => method.type === 'credit_card',
        ),
    );
</script>

<div
    class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
>
    <div class="px-4 pt-4 pb-2">
        <div class="flex items-center gap-2 mb-3">
            <i class="ti ti-credit-card text-lg" style="color:{primary}"></i>
            <span class="font-bold text-slate-800 text-sm"
                >Metode Pembayaran</span
            >
        </div>
        <div class="space-y-2">
            {#each visiblePaymentMethods as paymentMethod (paymentMethod.id)}
                <button
                    onclick={() => (selectedPaymentMethodId = paymentMethod.id)}
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border-2 text-left transition {selectedPaymentMethodId ===
                    paymentMethod.id
                        ? 'border-transparent'
                        : 'border-slate-200 hover:border-slate-300'}"
                    style={selectedPaymentMethodId === paymentMethod.id
                        ? `border-color:${primary}; background:${primary}10`
                        : ''}
                >
                    <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-white shrink-0"
                        style="background:{primary}"
                    >
                        <i
                            class="ti {paymentMethod.bank_name === 'COD' ||
                            paymentMethod.name?.toLowerCase().includes('cod')
                                ? 'ti-cash'
                                : paymentMethod.bank_name === 'WARIS QRIS' ||
                                    paymentMethod.settings?.gateway ===
                                        'waris' ||
                                    paymentMethod.name
                                        ?.toLowerCase()
                                        .includes('waris')
                                  ? 'ti-bolt'
                                  : paymentMethod.bank_name === 'QRIS' ||
                                      paymentMethod.name
                                          ?.toLowerCase()
                                          .includes('qris')
                                    ? 'ti-qrcode'
                                    : paymentMethod.type === 'manual'
                                      ? 'ti-building-bank'
                                      : 'ti-credit-card'} text-sm"
                        ></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-slate-800 truncate">
                            {paymentMethod.name}
                        </p>
                        {#if paymentMethod.bank_name === 'COD' || paymentMethod.name
                                ?.toLowerCase()
                                .includes('cod')}
                            <p class="text-xs text-slate-500">
                                {paymentMethod.description ||
                                    paymentMethod.settings?.description ||
                                    'Bayar tunai di tempat / kasir resto'}
                            </p>
                        {:else if paymentMethod.bank_name === 'WARIS QRIS' || paymentMethod.settings?.gateway === 'waris' || paymentMethod.name
                                ?.toLowerCase()
                                .includes('waris')}
                            <p class="text-xs text-slate-500">
                                QRIS Dinamis Otomatis · Verifikasi Instan
                            </p>
                        {:else if paymentMethod.bank_name === 'QRIS' || paymentMethod.name
                                ?.toLowerCase()
                                .includes('qris')}
                            <p class="text-xs text-slate-500">
                                {paymentMethod.account_name
                                    ? `Atas nama: ${paymentMethod.account_name}`
                                    : paymentMethod.description ||
                                      'Scan QRIS Semua E-Wallet & Bank'}
                            </p>
                        {:else if paymentMethod.type === 'manual' && paymentMethod.bank_name}
                            <p class="text-xs text-slate-500">
                                {paymentMethod.bank_name} · {paymentMethod.account_number}
                                ({paymentMethod.account_name || 'Resto'})
                            </p>
                        {:else if paymentMethod.type === 'gateway'}
                            <p class="text-xs text-slate-500">
                                Pembayaran Otomatis Terverifikasi
                            </p>
                        {/if}
                    </div>
                    {#if selectedPaymentMethodId === paymentMethod.id}
                        <i
                            class="ti ti-circle-check text-lg shrink-0"
                            style="color:{primary}"
                        ></i>
                    {:else}
                        <div
                            class="w-5 h-5 rounded-full border-2 border-slate-300 shrink-0"
                        ></div>
                    {/if}
                </button>
            {:else}
                <div
                    class="p-4 rounded-xl bg-slate-50 border border-slate-100 text-center text-xs text-slate-400"
                >
                    Tidak ada metode pembayaran aktif untuk resto ini.
                </div>
            {/each}

            {#if midtransEnabledMethods.length > 0}
                <div class="space-y-4 pt-1">
                    <MidtransMethodGroup
                        title="Transfer Bank (Virtual Account)"
                        methods={bankMethods}
                        fallbackIcon="ti-building-bank"
                        kind="bank"
                        {primary}
                        methodColors={midtransMethodColors}
                        methodIcons={midtransMethodIcons}
                        bind:selectedMethodKey={selectedMidtransMethodKey}
                    />
                    <MidtransMethodGroup
                        title="E-Wallet & QRIS"
                        methods={ewalletMethods}
                        fallbackIcon="ti-wallet"
                        kind="wallet"
                        {primary}
                        methodColors={midtransMethodColors}
                        methodIcons={midtransMethodIcons}
                        bind:selectedMethodKey={selectedMidtransMethodKey}
                    />
                    <MidtransMethodGroup
                        title="Gerai Retail / Minimarket"
                        methods={cstoreMethods}
                        fallbackIcon="ti-building-store"
                        kind="retail"
                        {primary}
                        methodColors={midtransMethodColors}
                        methodIcons={midtransMethodIcons}
                        bind:selectedMethodKey={selectedMidtransMethodKey}
                    />
                    <MidtransMethodGroup
                        title="Kartu Kredit / Debit"
                        methods={cardMethods}
                        fallbackIcon="ti-credit-card"
                        kind="card"
                        {primary}
                        methodColors={midtransMethodColors}
                        methodIcons={midtransMethodIcons}
                        bind:selectedMethodKey={selectedMidtransMethodKey}
                    />
                </div>
            {/if}
        </div>

        {#if selectedPaymentMethod?.bank_name === 'COD' || selectedPaymentMethod?.settings?.code === 'cod' || selectedPaymentMethod?.name
                ?.toLowerCase()
                .includes('cod') || selectedPaymentMethod?.name
                ?.toLowerCase()
                .includes('bayar di tempat')}
            <div
                class="mt-3 bg-amber-50/90 border border-amber-200/90 rounded-xl p-3 text-xs text-amber-900 space-y-1"
            >
                <p class="font-bold flex items-center gap-1.5 text-amber-950">
                    <i class="ti ti-cash text-base text-amber-600"></i>
                    Bayar Tunai di Kasir / Kurir
                </p>
                <p class="text-[11.5px] text-amber-800 leading-relaxed">
                    {selectedPaymentMethod.description ||
                        selectedPaymentMethod.settings?.description ||
                        'Siapkan uang pas saat mengambil pesanan di resto atau saat makanan diantar oleh kurir resto.'}
                </p>
            </div>
        {:else if selectedPaymentMethod?.settings?.gateway === 'waris' || selectedPaymentMethod?.name
                ?.toLowerCase()
                .includes('waris')}
            <!-- <div
                class="mt-3 bg-rose-50/90 border border-rose-200/90 rounded-xl p-3 text-xs text-rose-900 space-y-1.5"
            >
                <p class="font-bold flex items-center gap-1.5 text-rose-950">
                    <i class="ti ti-bolt text-base text-rose-600"></i>
                    {selectedPaymentMethod.name}
                </p>
                <p class="text-[11.5px] text-rose-800 leading-relaxed">
                    Kode QRIS dinamis akan dibuat otomatis secara instan setelah
                    pesanan diajukan, pembayaran Anda langsung terverifikasi via
                    sistem Webhook WARIS.
                </p>
            </div> -->
        {:else if selectedPaymentMethod?.bank_name === 'QRIS' || selectedPaymentMethod?.settings?.code === 'qris' || (selectedPaymentMethod?.type === 'manual' && selectedPaymentMethod?.name
                    ?.toLowerCase()
                    .includes('qris'))}
            <div
                class="mt-3 bg-indigo-50/90 border border-indigo-200/90 rounded-xl p-3 text-xs text-indigo-900 space-y-1.5"
            >
                <p class="font-bold flex items-center gap-1.5 text-indigo-950">
                    <i class="ti ti-qrcode text-base text-indigo-600"></i>
                    {selectedPaymentMethod.name}
                </p>
                {#if selectedPaymentMethod.account_name || selectedPaymentMethod.settings?.merchant_name}
                    <p class="text-[11.5px] text-indigo-800">
                        Merchant: <span class="font-bold"
                            >{selectedPaymentMethod.account_name ||
                                selectedPaymentMethod.settings
                                    ?.merchant_name}</span
                        >
                    </p>
                {/if}
                {#if selectedPaymentMethod.settings?.qris_image_path}
                    <div
                        class="mt-2 p-2 bg-white rounded-lg border border-indigo-100 flex justify-center"
                    >
                        <img
                            src={selectedPaymentMethod.settings.qris_image_path}
                            alt="Kode QRIS Resto"
                            width="192"
                            height="192"
                            class="max-h-48 object-contain rounded"
                        />
                    </div>
                {/if}
                <p class="text-[11px] text-indigo-700 mt-1">
                    Scan barcode QRIS di atas menggunakan GoPay, OVO, ShopeePay,
                    DANA, BCA, atau Mobile Banking pilihan Anda.
                </p>
            </div>
        {:else if selectedPaymentMethod?.type === 'manual'}
            <div class="mt-3 bg-blue-50 border border-blue-200 rounded-xl p-3">
                <p
                    class="text-xs font-bold text-blue-800 mb-1 flex items-center gap-1.5"
                >
                    <i class="ti ti-building-bank text-blue-600"></i>
                    Info Rekening Transfer Resto
                </p>
                <p class="text-xs text-blue-700">
                    Bank: {selectedPaymentMethod.bank_name}
                </p>
                {#if selectedPaymentMethod.account_number}
                    <p class="text-xs text-blue-700">
                        No. Rekening: <span class="font-bold font-mono"
                            >{selectedPaymentMethod.account_number}</span
                        >
                    </p>
                {/if}
                {#if selectedPaymentMethod.account_name}
                    <p class="text-xs text-blue-700">
                        Atas Nama: {selectedPaymentMethod.account_name}
                    </p>
                {/if}
                <p class="text-xs text-blue-600 mt-1">
                    Upload bukti bayar setelah pesanan dibuat.
                </p>
            </div>
        {/if}

        {#if selectedMidtransMethodKey}
            {@const selectedMethod = midtransEnabledMethods.find(
                (method: any) => method.key === selectedMidtransMethodKey,
            )}
            <div
                class="mt-3 bg-emerald-50 border border-emerald-200 rounded-xl p-3"
            >
                <p
                    class="text-xs font-bold text-emerald-800 mb-1 flex items-center gap-1"
                >
                    <i class="ti ti-shield-check"></i>
                    {selectedMethod?.label ?? 'Midtrans'}
                </p>
                <p class="text-xs text-emerald-700">
                    Instruksi pembayaran (nomor VA / QR code) akan tampil di
                    halaman transaksi setelah order dibuat.
                </p>
            </div>
        {/if}
    </div>
    <div class="pb-4"></div>
</div>
