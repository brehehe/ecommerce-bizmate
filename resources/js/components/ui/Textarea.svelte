<script lang="ts">
    let { 
        value = $bindable(''), 
        id = '', 
        label = '', 
        placeholder = '',
        required = false,
        rows = 4,
        error = ''
    } = $props();
</script>

<div class="space-y-2 w-full">
    {#if label}
        <label for={id} class="text-xs font-bold text-slate-600 block">
            {label}
            {#if required}
                <span class="text-rose-500">*</span>
            {/if}
        </label>
    {/if}
    <textarea 
        {id} 
        bind:value 
        {placeholder}
        {required}
        {rows}
        class="w-full px-3 py-2 border rounded-xl text-sm focus:ring-2 focus:ring-brand-blueRoyal focus:outline-none transition resize-none {error ? 'border-rose-500 focus:ring-rose-500/20 focus:border-rose-500' : 'border-slate-200 hover:border-slate-300'}"
    ></textarea>
    {#if error}
        <span class="text-xs text-rose-500 font-medium block mt-1">{error}</span>
    {/if}
</div>
