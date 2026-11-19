<script>
    let { shippingInfo } = $props();
</script>

<!-- Pengiriman -->
<div class="py-2.5 flex items-start gap-4">
    <span
        class="text-xs text-slate-400 w-20 sm:w-24 shrink-0 font-bold uppercase tracking-wider pt-0.5"
        >Lokasi</span
    >
    <div class="flex items-start gap-1.5 text-xs text-slate-700">
        <i class="ti ti-map-pin text-green-500 text-sm mt-0.5"></i>
        <div>
            <span class="font-bold text-slate-800">
                {shippingInfo.store_city || 'Lokasi toko'}
            </span>
        </div>
    </div>
</div>
