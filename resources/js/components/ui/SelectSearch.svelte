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
    let search = $state('');
    let containerNode: HTMLElement | null = $state(null);
    let searchInput: HTMLInputElement | null = $state(null);

    // Derived filtered options
    let filteredOptions = $derived(
        options.filter(opt => opt.name.toLowerCase().includes(search.toLowerCase()))
    );

    // Derived selected label
    let selectedLabel = $derived(
        options.find(opt => opt.id == value || opt.name == value)?.name || value || ''
    );

    function toggleOpen() {
        if (!disabled) {
            isOpen = !isOpen;
            if (isOpen) {
                search = '';
                // Focus search input on next tick
                setTimeout(() => {
                    if (searchInput) searchInput.focus();
                }, 10);
            }
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
        <p class="text-xs font-bold text-slate-600 block">
            {label}
            {#if required}
                <span class="text-rose-500">*</span>
            {/if}
        </p>
    {/if}
    
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div 
        role="button"
        tabindex="0"
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
            <div class="p-2 border-b border-slate-100 bg-slate-50 sticky top-0">
                <div class="relative">
                    <i class="ti ti-search absolute left-3 top-2.5 text-slate-400"></i>
                    <input 
                        type="text" 
                        bind:value={search} 
                        placeholder="Cari..." 
                        bind:this={searchInput}
                        class="w-full pl-9 pr-3 py-2 text-sm bg-white border border-slate-200 rounded-lg outline-none focus:border-brand-blueRoyal"
                    >
                </div>
            </div>
            
            <div class="overflow-y-auto flex-grow">
                {#if filteredOptions.length === 0}
                    <div class="p-4 text-center text-sm text-slate-500">Tidak ada hasil</div>
                {:else}
                    {#each filteredOptions as option}
                        <!-- svelte-ignore a11y_click_events_have_key_events -->
                        <div 
                            role="button"
                            tabindex="0"
                            class="px-4 py-2.5 text-sm hover:bg-slate-50 cursor-pointer {value === option.id ? 'bg-brand-blueRoyal/5 text-brand-blueRoyal font-bold' : 'text-slate-700'}"
                            onclick={() => selectOption(option.id)}
                        >
                            {option.name}
                        </div>
                    {/each}
                {/if}
            </div>
        </div>
    {/if}
</div>
