<script lang="ts">
    let { 
        value = $bindable(''), 
        options = [], 
        label = '', 
        placeholder = 'Pilih salah satu',
        required = false,
        disabled = false,
        error = '',
        onchange = null
    } = $props();

    let isOpen = $state(false);
    let containerNode;

    // Derived selected label
    let selectedLabel = $derived(
        options.find(opt => opt.id == value || opt.name == value)?.name || value || ''
    );

    function toggleOpen() {
        if (!disabled) {
            isOpen = !isOpen;
        }
    }

    function selectOption(id) {
        value = id;
        isOpen = false;
        if (onchange) {
            onchange(id);
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
        class="w-full px-3 py-2 border rounded-xl text-sm transition flex justify-between items-center {disabled ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-white cursor-pointer hover:border-brand-blueRoyal'} {error ? 'border-rose-500' : 'border-slate-200'}"
        onclick={toggleOpen}
    >
        <span class="truncate {selectedLabel ? 'text-slate-800' : 'text-slate-400'}">
            {selectedLabel || placeholder}
        </span>
        <i class="ti ti-chevron-down text-slate-400 transition-transform {isOpen ? 'rotate-180' : ''}"></i>
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
                        <div 
                            class="px-4 py-2.5 text-sm hover:bg-slate-50 cursor-pointer {value == option.id || value == option.name ? 'bg-brand-blueRoyal/5 text-brand-blueRoyal font-bold' : 'text-slate-700'}"
                            onclick={() => selectOption(option.id || option.name)}
                        >
                            {option.name}
                        </div>
                    {/each}
                {/if}
            </div>
        </div>
    {/if}
</div>
