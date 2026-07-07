<script lang="ts">
    let { 
        value = $bindable([]), 
        options = [], 
        label = '', 
        placeholder = 'Pilih beberapa',
        required = false,
        disabled = false,
        error = '',
        onchange = null
    } = $props();

    let isOpen = $state(false);
    let containerNode;

    // Derived selected options
    let selectedOptions = $derived(
        options.filter(opt => value.includes(opt.id) || value.includes(opt.name))
    );

    function toggleOpen() {
        if (!disabled) {
            isOpen = !isOpen;
        }
    }

    function toggleOption(id) {
        if (value.includes(id)) {
            value = value.filter(v => v !== id);
        } else {
            value = [...value, id];
        }
        
        if (onchange) {
            onchange(value);
        }
    }

    function removeOption(e, id) {
        e.stopPropagation();
        value = value.filter(v => v !== id);
        if (onchange) {
            onchange(value);
        }
    }

    function handleWindowClick(event) {
        if (isOpen && containerNode && !containerNode.contains(event.target)) {
            isOpen = false;
        }
    }
</script>

<svelte:window onclick={handleWindowClick} />

<div class="space-y-2 relative" bind:this={containerNode}>
    {#if label}
        <label class="text-xs font-bold text-slate-600 block">
            {label}
            {#if required}
                <span class="text-rose-500">*</span>
            {/if}
        </label>
    {/if}
    
    <div 
        class="w-full min-h-[44px] px-3 py-2 border rounded-xl text-sm transition flex flex-wrap gap-2 items-center {disabled ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-white cursor-pointer hover:border-brand-blueRoyal'} {error ? 'border-rose-500' : 'border-slate-200'}"
        onclick={toggleOpen}
    >
        {#if selectedOptions.length === 0}
            <span class="text-slate-400 px-1">{placeholder}</span>
        {:else}
            {#each selectedOptions as option}
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-xs font-bold text-slate-700">
                    {option.name}
                    <button type="button" onclick={(e) => removeOption(e, option.id || option.name)} class="text-slate-400 hover:text-rose-500 transition">
                        <i class="ti ti-x text-[10px]"></i>
                    </button>
                </span>
            {/each}
        {/if}
        
        <div class="ml-auto pl-2">
            <i class="ti ti-chevron-down text-slate-400 transition-transform {isOpen ? 'rotate-180' : ''}"></i>
        </div>
    </div>

    {#if error}
        <span class="text-xs text-rose-500 font-medium block mt-1">{error}</span>
    {/if}

    {#if isOpen}
        <div class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 flex flex-col overflow-hidden">
            <div class="overflow-y-auto flex-grow">
                {#if options.length === 0}
                    <div class="p-4 text-center text-sm text-slate-500">Tidak ada pilihan</div>
                {:else}
                    {#each options as option}
                        {@const isSelected = value.includes(option.id) || value.includes(option.name)}
                        <label class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50 cursor-pointer {isSelected ? 'bg-brand-blueRoyal/5' : ''}">
                            <input 
                                type="checkbox" 
                                checked={isSelected}
                                onchange={() => toggleOption(option.id || option.name)}
                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                            >
                            <span class={isSelected ? 'text-brand-blueRoyal font-bold' : 'text-slate-700'}>
                                {option.name}
                            </span>
                        </label>
                    {/each}
                {/if}
            </div>
        </div>
    {/if}
</div>
