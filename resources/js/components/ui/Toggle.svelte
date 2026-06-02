<script lang="ts">
    let { 
        checked = $bindable(false), 
        label = '', 
        description = '', 
        icon = 'ti-settings' 
    } = $props();
</script>

<button type="button" class="w-full flex items-center justify-between gap-6 p-3 rounded-2xl border border-slate-100 hover:bg-slate-50 transition cursor-pointer text-left" onclick={() => checked = !checked}>
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center">
            <i class="ti {icon} text-lg"></i>
        </div>
        <div>
            {#if label}
                <span class="text-xs font-bold text-slate-800 block">{label}</span>
            {/if}
            {#if description}
                <span class="text-[10px] text-slate-400 font-semibold block">{description}</span>
            {/if}
        </div>
    </div>
    <label class="relative inline-flex items-center cursor-pointer pointer-events-none">
        <input type="checkbox" bind:checked class="sr-only peer">
        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-blueRoyal"></div>
    </label>
</button>
