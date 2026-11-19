<script lang="ts">
    let {
        primary,
        guestName = $bindable(),
        guestPhone = $bindable(),
        guestEmail = $bindable(),
    }: {
        primary: string;
        guestName: string;
        guestPhone: string;
        guestEmail: string;
    } = $props();
</script>

<div
    class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
>
    <div
        class="py-2.5 px-4 flex items-center justify-between border-b border-slate-100 bg-slate-50/70"
    >
        <div class="flex items-center gap-2">
            <i class="ti ti-user-check text-lg" style="color:{primary}"></i>
            <span class="font-bold text-slate-800 text-sm"
                >Data Pemesan (Guest Checkout)</span
            >
        </div>
        <span
            class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200"
        >
            Tanpa Login
        </span>
    </div>
    <div class="p-4 space-y-3.5">
        <div
            class="p-3 bg-blue-50/60 rounded-xl border border-blue-100 text-xs text-blue-800 flex items-start gap-2"
        >
            <i class="ti ti-info-circle text-base text-blue-600 shrink-0 mt-0.5"
            ></i>
            <div class="min-w-0">
                <p class="font-bold">Checkout Cepat Tanpa Perlu Daftar Akun</p>
                <p class="text-[11px] text-blue-700 mt-0.5 leading-relaxed">
                    Kode pesanan dan link pelacakan status pesanan akan
                    dikirimkan otomatis ke alamat email Anda melalui antrean
                    sistem (queue).
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Nama Lengkap <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <i
                        class="ti ti-user absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"
                    ></i>
                    <input
                        type="text"
                        bind:value={guestName}
                        placeholder="Contoh: Budi Santoso"
                        class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-slate-300"
                        required
                    />
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Nomor WhatsApp / HP <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <i
                        class="ti ti-phone absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"
                    ></i>
                    <input
                        type="tel"
                        bind:value={guestPhone}
                        placeholder="Contoh: 081234567890"
                        class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-slate-300"
                        required
                    />
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Alamat Email <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <i
                        class="ti ti-mail absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base"
                    ></i>
                    <input
                        type="email"
                        bind:value={guestEmail}
                        placeholder="Contoh: budi@gmail.com (Untuk pengiriman kode pesanan)"
                        class="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-slate-300"
                        required
                    />
                </div>
                <p
                    class="text-[10.5px] text-slate-500 mt-1 flex items-center gap-1"
                >
                    <i class="ti ti-shield-check text-emerald-600"></i>
                    Kode pesanan dan faktur akan dikirim ke email ini via antrean
                    sistem.
                </p>
            </div>
        </div>
    </div>
</div>
