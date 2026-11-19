<script>
    /* eslint-disable svelte/no-at-html-tags, svelte/require-each-key */
    let {
        parsedSpecifications,
        showSpecsModal = $bindable(),
        primary,
        secondary,
        product,
        withOpacity,
        userHeight = $bindable(),
        userWeight = $bindable(),
        recommendedSize,
    } = $props();
</script>

<!-- Combined Spesifikasi & Deskripsi Section -->
<div
    class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-100 shadow-sm p-5 sm:p-7 w-full min-w-0 overflow-hidden"
>
    <!-- Spesifikasi Row Trigger (Merged inside Deskripsi) -->
    {#if parsedSpecifications.length > 0}
        <button
            onclick={() => (showSpecsModal = true)}
            class="w-full flex items-center justify-between pb-5 mb-5 border-b border-slate-100/70 hover:bg-slate-50/50 transition cursor-pointer text-left focus:outline-none select-none"
        >
            <div class="flex items-center gap-3">
                <div
                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm transition-all"
                    style="background: {withOpacity(
                        primary,
                        0.08,
                    )}; color: {primary};"
                >
                    <i class="ti ti-list text-lg"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800">
                        Spesifikasi Produk
                    </p>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        {#if product.brand || (product.brands && product.brands.length > 0)}
                            Merk: {product.brand || product.brands[0].name} •
                        {/if}
                        Lihat detail spesifikasi lengkap
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 text-slate-400">
                <span
                    class="text-xs font-semibold text-slate-500 hidden sm:inline"
                    >Lihat</span
                >
                <i class="ti ti-chevron-right text-lg"></i>
            </div>
        </button>
    {/if}

    <!-- Deskripsi Section -->
    <div class="w-full min-w-0 overflow-x-auto">
        <h3
            class="text-base font-bold text-slate-800 flex items-center gap-2 mb-4"
        >
            <i class="ti ti-file-text text-lg" style="color: {primary};"></i>
            Deskripsi Produk
        </h3>
        {#if product.description}
            <div
                class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-700 min-w-0"
            >
                {@html product.description}
            </div>
        {:else if product.summary}
            <p class="text-sm text-slate-600 leading-relaxed">
                {product.summary}
            </p>
        {:else}
            <div class="text-center py-10 text-slate-400">
                <i class="ti ti-file-text text-5xl block mb-2"></i>
                <p class="text-sm">Deskripsi belum tersedia</p>
            </div>
        {/if}
    </div>

    <!-- ── Panduan Ukuran & Kalkulator Rekomendasi ── -->
    {#if product.size_chart && product.size_chart.enabled}
        <div class="mt-6 border-t border-slate-100 pt-6">
            <h3
                class="text-base font-bold text-slate-800 flex items-center gap-2 mb-4"
            >
                <i class="ti ti-shirt text-lg" style="color: {primary};"></i>
                Kalkulator & Panduan Ukuran
            </h3>

            <!-- Calculator Card -->
            <div
                class="bg-slate-50/70 rounded-2xl border border-slate-100 p-4 sm:p-5 mb-5 shadow-sm/5"
            >
                <h4
                    class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5"
                >
                    <i class="ti ti-calculator text-base text-slate-400"></i> Cari
                    Ukuran Rekomendasi Anda
                </h4>
                <div
                    class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end mb-4"
                >
                    <div>
                        <label
                            for="height-input"
                            class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider"
                        >
                            Tinggi Badan (cm)
                        </label>
                        <input
                            type="number"
                            id="height-input"
                            bind:value={userHeight}
                            placeholder="Cth: 170"
                            class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm focus:border-brand-blueRoyal focus:outline-none text-slate-750 font-bold"
                        />
                    </div>
                    <div>
                        <label
                            for="weight-input"
                            class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider"
                        >
                            Berat Badan (kg)
                        </label>
                        <input
                            type="number"
                            id="weight-input"
                            bind:value={userWeight}
                            placeholder="Cth: 65"
                            class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm focus:border-brand-blueRoyal focus:outline-none text-slate-750 font-bold"
                        />
                    </div>
                </div>

                <!-- Calculator Results Alert -->
                {#if recommendedSize}
                    <div
                        class="p-4 rounded-xl flex items-center gap-3.5 transition-all duration-300 shadow-sm"
                        style="background: {withOpacity(
                            primary,
                            0.08,
                        )}; border: 1px solid {withOpacity(primary, 0.15)};"
                    >
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                            style="background: linear-gradient(135deg, {primary}, {secondary}); color: #fff;"
                        >
                            <i class="ti ti-sparkles text-base"></i>
                        </div>
                        <div>
                            <p
                                class="text-[10px] text-slate-500 font-bold uppercase tracking-wider"
                            >
                                Ukuran Rekomendasi
                            </p>
                            <p
                                class="text-sm font-black mt-0.5"
                                style="color: {primary};"
                            >
                                Ukuran yang paling pas untuk Anda adalah <span
                                    class="bg-white px-2 py-0.5 rounded-md border border-slate-100 shadow-sm ml-1"
                                    style="color: {secondary};"
                                    >{recommendedSize}</span
                                >
                            </p>
                        </div>
                    </div>
                {:else if userHeight || userWeight}
                    <div
                        class="p-3 bg-amber-50/50 border border-amber-100 rounded-xl flex items-center gap-2 text-xs text-amber-600 font-medium"
                    >
                        <i class="ti ti-info-circle text-base"></i>
                        Belum ada ukuran yang pas untuk tinggi/berat tersebut. Silakan
                        hubungi admin via chat.
                    </div>
                {/if}
            </div>

            <!-- Size Guide Table -->
            <div
                class="w-full max-w-full overflow-x-auto border border-slate-100 rounded-2xl bg-white shadow-sm/5"
            >
                <table
                    class="w-full text-left text-xs border-collapse min-w-[500px] sm:min-w-0"
                >
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th
                                class="p-2 sm:p-3.5 font-bold text-slate-500 uppercase tracking-wider text-center"
                                >{product.size_chart.headers[0]}</th
                            >
                            {#each product.size_chart.headers.slice(1) as header}
                                <th
                                    class="p-2 sm:p-3.5 font-bold text-slate-500 uppercase tracking-wider text-center"
                                    >{header}</th
                                >
                            {/each}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        {#each product.size_chart.rows as row}
                            <tr
                                class="hover:bg-slate-50/40 transition {recommendedSize ===
                                row.size
                                    ? 'bg-slate-50/75 font-bold'
                                    : ''}"
                            >
                                <td
                                    class="p-2 sm:p-3.5 text-center font-bold text-slate-800 bg-slate-50/20 w-20"
                                    >{row.size}</td
                                >
                                {#each row.values as val}
                                    <td
                                        class="p-2 sm:p-3.5 text-center text-slate-600 font-semibold"
                                        >{val}</td
                                    >
                                {/each}
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <!-- Scroll Hint for Mobile -->
            <div
                class="flex items-center justify-end gap-1 text-[10px] text-slate-400 mt-2 sm:hidden"
            >
                <i class="ti ti-arrows-horizontal text-xs"></i>
                <span>Geser tabel ke samping untuk melihat detail</span>
            </div>
        </div>
    {/if}
</div>
