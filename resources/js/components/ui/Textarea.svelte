<script lang="ts">
    let { 
        value = $bindable(''), 
        id = '', 
        label = '', 
        placeholder = '',
        required = false,
        rows = 4,
        error = '',
        autoHeight = false,
        oninput = undefined
    } = $props();

    let textareaEl = $state<HTMLTextAreaElement | null>(null);

    function adjustHeight() {
        if (autoHeight && textareaEl) {
            textareaEl.style.height = 'auto';
            textareaEl.style.height = `${textareaEl.scrollHeight}px`;
        }
    }

    $effect(() => {
        if (autoHeight && textareaEl && value !== undefined) {
            adjustHeight();
        }
    });
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
        bind:this={textareaEl}
        bind:value 
        {placeholder}
        {required}
        {rows}
        oninput={(e) => {
            adjustHeight();
            if (oninput) oninput(e);
        }}
        class="w-full px-3 py-2 border rounded-xl text-sm focus:ring-2 focus:ring-brand-blueRoyal focus:outline-none transition resize-none {error ? 'border-rose-500 focus:ring-rose-500/20 focus:border-rose-500' : 'border-slate-200 hover:border-slate-300'}"
    ></textarea>
    {#if error}
        <span class="text-xs text-rose-500 font-medium block mt-1">{error}</span>
    {/if}
</div>
