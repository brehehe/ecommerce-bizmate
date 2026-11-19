<script lang="ts">
    let {
        primary,
        orderNotes = $bindable(),
    }: {
        primary: string;
        orderNotes: string;
    } = $props();
</script>

<div
    class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
>
    <div class="px-4 pt-4 pb-4">
        <div class="flex items-center gap-2 mb-3">
            <i class="ti ti-notes text-lg" style="color:{primary}"></i>
            <span class="font-bold text-slate-800 text-sm">Catatan Pesanan</span
            >
        </div>
        <textarea
            bind:value={orderNotes}
            rows="2"
            placeholder="Tambahkan catatan untuk penjual (opsional)"
            class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-transparent resize-none"
            style="--tw-ring-color:{primary}30"
        ></textarea>
    </div>
</div>
