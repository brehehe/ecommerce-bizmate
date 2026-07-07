<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { usePage } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    const page = usePage();
    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    let activeStepIndex = $state(0);
    let isLoading = $state(false);
    let runResult = $state<any>(null);
    let runCurl = $state<string>('');

    const steps = [
        {
            id: 'destination',
            label: 'Destination',
            name: 'Search address with postal code, village, sub-district, or district',
            input: 'Subdistrict_name = cibungbulang',
            expect: `{ "meta": { "message": "Successfully Get Destination Data", "code": 200, "status": "success" }\nSuccess in displaying the response:\n    id\n    label\n    subdistrict_name\n    district_name\n    city_name\n    zip_code\n}`,
        },
        {
            id: 'calculate',
            label: 'Calculate Shipping',
            name: 'Calculate shipping cost / tariff',
            input: 'origin_id = 3578, destination_id = 69363, weight = 1.0kg, courier = jne',
            expect: `{ "meta": { "message": "Successfully Calculate Tariff Data", "code": 200, "status": "success" }\nSuccess in displaying the response:\n    calculate_reguler\n    calculate_cargo\n}`,
        },
        {
            id: 'order',
            label: 'Order',
            name: 'Store order / booking shipment',
            input: 'transaction = (Terbaru di database)',
            expect: `{ "meta": { "message": "Successfully Store Order", "code": 200, "status": "success" }\nSuccess in displaying the response:\n    booking_code\n    airway_bill\n}`,
        },
        {
            id: 'pickup',
            label: 'Pickup',
            name: 'Request courier pickup',
            input: 'booking_code = BOOK-..., courier = JNE',
            expect: `{ "meta": { "message": "Successfully Request Pickup", "code": 200, "status": "success" }\nSuccess in displaying the response:\n    pickup_code\n}`,
        },
        {
            id: 'print_label',
            label: 'Print Label',
            name: 'Generate shipping label / label cetak PDF',
            input: 'booking_code = BOOK-...',
            expect: `{ "meta": { "message": "Successfully Get Label URL", "code": 200, "status": "success" }\nSuccess in displaying the response:\n    label_url\n}`,
        },
        {
            id: 'history_awb',
            label: 'History AWB',
            name: 'Track airway bill history',
            input: 'airway_bill = RES-..., courier = JNE',
            expect: `{ "meta": { "message": "Successfully Get History AWB", "code": 200, "status": "success" }\nSuccess in displaying the response:\n    history\n}`,
        },
    ];

    let currentStep = $derived(steps[activeStepIndex]);

    $effect(() => {
        // Clear result when changing steps
        activeStepIndex;
        runResult = null;
        runCurl = '';
    });

    async function executeStep() {
        isLoading = true;
        runResult = null;
        runCurl = '';
        try {
            const response = await fetch(`/admin/uat/run/${currentStep.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    Accept: 'application/json',
                },
            });

            if (response.ok) {
                const data = await response.json();
                runCurl = data.curl;
                runResult = data.result;
                showToast('Uji langkah sukses dijalankan!', 'success');
            } else {
                const data = await response.json();
                runResult = data.result || {
                    error: 'Terjadi kesalahan sistem.',
                };
                showToast('Uji langkah gagal.', 'error');
            }
        } catch (err: any) {
            console.error(err);
            runResult = { error: err.message || 'Network error' };
            showToast('Koneksi bermasalah.', 'error');
        } finally {
            isLoading = false;
        }
    }

    function copyToClipboard(text: string) {
        if (!text) return;
        navigator.clipboard.writeText(text);
        showToast('Teks berhasil disalin!', 'success');
    }
</script>

<svelte:head>
    <title>User Acceptance Testing (UAT) Komerce</title>
</svelte:head>

<AdminLayout>
    <div
        class="flex-grow p-6 flex flex-col h-[calc(100vh-5rem)] min-h-0 overflow-hidden w-full max-w-[1600px] mx-auto bg-white"
    >
        <!-- Header -->
        <div class="flex items-center gap-3 pb-5 shrink-0">
            <button
                onclick={() => window.history.back()}
                class="w-8 h-8 rounded-lg flex items-center justify-center bg-[#fa4a2a] text-white hover:bg-[#e04828] cursor-pointer transition shadow-sm"
                aria-label="Kembali"
            >
                <i class="ti ti-chevron-left text-sm"></i>
            </button>
            <h1 class="font-outfit font-black text-lg text-slate-800">
                User Acceptance Testing
            </h1>
        </div>

        <!-- Step Wizard Bar -->
        <div class="bg-[#f8f9fa] rounded-xl p-4 mb-6 shrink-0">
            <div class="flex flex-wrap items-center justify-between gap-4">
                {#each steps as step, idx}
                    <button
                        onclick={() => (activeStepIndex = idx)}
                        class="flex items-center gap-2.5 cursor-pointer focus:outline-none transition"
                    >
                        <div
                            class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-[11px] transition-colors duration-300 text-white"
                            style="background-color: {idx <= activeStepIndex
                                ? '#fa4a2a'
                                : '#8d9196'};"
                        >
                            {idx + 1}
                        </div>
                        <span
                            class="text-xs font-bold transition-colors duration-300"
                            style="color: {idx <= activeStepIndex
                                ? '#fa4a2a'
                                : '#8d9196'};"
                        >
                            {step.label}
                        </span>
                    </button>
                {/each}
            </div>
        </div>

        <!-- Content Panel -->
        <div class="flex-grow flex flex-col min-h-0">
            <!-- Step Title -->
            <div class="mb-5 shrink-0">
                <h2 class="text-sm font-bold text-slate-800 mb-2">
                    {currentStep.label}
                </h2>
                <div class="h-[1px] bg-slate-200"></div>
            </div>

            <!-- Table content -->
            <div
                class="flex-grow overflow-y-auto min-h-0 custom-scrollbar pr-2"
            >
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="text-[11px] font-bold text-slate-900 border-b border-slate-200"
                        >
                            <th class="pb-3 w-[18%]">Name</th>
                            <th class="pb-3 w-[12%]">Input</th>
                            <th class="pb-3 w-[25%]">Expect Result</th>
                            <th class="pb-3 w-[22%]">cURL</th>
                            <th class="pb-3 w-[22%]">Result</th>
                            <th class="pb-3 w-[5%]">Note</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs align-top">
                        <tr>
                            <td
                                class="py-4 pr-3 text-slate-500 italic font-normal leading-relaxed text-[11px]"
                            >
                                {currentStep.name}
                            </td>
                            <td
                                class="py-4 pr-3 text-slate-700 font-normal text-[11px] leading-relaxed"
                            >
                                {currentStep.input}
                            </td>
                            <td
                                class="py-4 pr-3 text-slate-600 font-mono text-[10px] whitespace-pre-wrap leading-relaxed"
                            >
                                {currentStep.expect}
                            </td>
                            <td class="py-4 pr-3">
                                <div class="relative w-full h-[180px]">
                                    <textarea
                                        readonly
                                        value={runCurl || '-'}
                                        placeholder="-"
                                        class="w-full h-full font-mono text-[9px] bg-white border border-slate-200 p-2.5 rounded-lg focus:outline-none resize-none cursor-text text-slate-600"
                                    ></textarea>
                                    {#if runCurl && runCurl !== '-'}
                                        <button
                                            onclick={() =>
                                                copyToClipboard(runCurl)}
                                            class="absolute right-2 top-2 px-2 py-1 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-[9px] font-bold text-slate-600 rounded-md cursor-pointer shadow-sm active:scale-95 transition"
                                        >
                                            Copy
                                        </button>
                                    {/if}
                                </div>
                            </td>
                            <td class="py-4 pr-3">
                                <div class="relative w-full h-[180px]">
                                    <textarea
                                        readonly
                                        value={runResult
                                            ? JSON.stringify(runResult, null, 2)
                                            : '-'}
                                        placeholder="-"
                                        class="w-full h-full font-mono text-[9px] bg-white border border-slate-200 p-2.5 rounded-lg focus:outline-none resize-none cursor-text text-slate-600"
                                    ></textarea>
                                    {#if runResult}
                                        <button
                                            onclick={() =>
                                                copyToClipboard(
                                                    JSON.stringify(
                                                        runResult,
                                                        null,
                                                        2,
                                                    ),
                                                )}
                                            class="absolute right-2 top-2 px-2 py-1 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-[9px] font-bold text-slate-600 rounded-md cursor-pointer shadow-sm active:scale-95 transition"
                                        >
                                            Copy
                                        </button>
                                    {/if}
                                </div>
                            </td>
                            <td
                                class="py-4 text-slate-500 font-normal text-[11px]"
                            >
                                -
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer navigation buttons -->
            <div
                class="py-4 border-t border-slate-200 bg-white flex justify-between items-center shrink-0 mt-4"
            >
                <div>
                    <button
                        onclick={executeStep}
                        disabled={isLoading}
                        class="px-5 py-2 bg-[#fa4a2a] hover:bg-[#e04828] text-white rounded-lg text-xs font-bold transition flex items-center gap-2 cursor-pointer shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {#if isLoading}
                            <svg
                                class="animate-spin h-3.5 w-3.5 text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <span>Mengirim...</span>
                        {:else}
                            <i class="ti ti-player-play-filled"></i>
                            <span>Jalankan Uji Coba</span>
                        {/if}
                    </button>
                </div>
                <div class="flex gap-2">
                    <button
                        onclick={() =>
                            (activeStepIndex = Math.max(
                                0,
                                activeStepIndex - 1,
                            ))}
                        disabled={activeStepIndex === 0}
                        class="px-4 py-2 border border-slate-300 hover:bg-slate-50 rounded-lg text-xs font-semibold text-slate-700 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        Back
                    </button>
                    <button
                        onclick={() =>
                            (activeStepIndex = Math.min(
                                steps.length - 1,
                                activeStepIndex + 1,
                            ))}
                        disabled={activeStepIndex === steps.length - 1}
                        class="px-4 py-2 bg-[#8d9196] hover:bg-[#7b7e83] rounded-lg text-xs font-semibold text-white cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
