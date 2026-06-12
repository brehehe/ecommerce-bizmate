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
    let search = $state('');
    let containerNode: HTMLElement | null = $state(null);
    let searchInput: HTMLInputElement | null = $state(null);

    // Derived filtered options
    let filteredOptions = $derived(
        options.filter(opt => opt.name.toLowerCase().includes(search.toLowerCase()))
    );

    // Derived selected options
    let selectedOptions = $derived(
        options.filter(opt => value.includes(opt.id) || value.includes(opt.name))
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

    let activeIndex = $state(-1);
    let optionsContainer: HTMLDivElement | null = $state(null);

    $effect(() => {
        if (isOpen) {
            if (filteredOptions.length > 0) {
                activeIndex = 0;
            } else {
                activeIndex = -1;
            }
        } else {
            activeIndex = -1;
        }
    });

    function scrollToActiveOption() {
        setTimeout(() => {
            if (!optionsContainer) return;
            const activeEl = optionsContainer.querySelector('[data-active="true"]');
            if (activeEl) {
                activeEl.scrollIntoView({ block: 'nearest' });
            }
        }, 10);
    }

    function handleKeyDown(event: KeyboardEvent) {
        if (!isOpen) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (filteredOptions.length > 0) {
                activeIndex = (activeIndex + 1) % filteredOptions.length;
                scrollToActiveOption();
            }
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (filteredOptions.length > 0) {
                activeIndex = (activeIndex - 1 + filteredOptions.length) % filteredOptions.length;
                scrollToActiveOption();
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (activeIndex >= 0 && activeIndex < filteredOptions.length) {
                const opt = filteredOptions[activeIndex];
                toggleOption(opt.id || opt.name);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            isOpen = false;
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
        <label class="text-xs font-bold text-slate-600 block" for="select-{label.toLowerCase().replace(/\s+/g, '-')}">
            {label}
            {#if required}
                <span class="text-rose-500">*</span>
            {/if}
        </label>
    {/if}
    
    <div 
        role="button"
        tabindex="0"
        onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleOpen(); } }}
        id="select-{label ? label.toLowerCase().replace(/\s+/g, '-') : 'search-multiple'}"
        class="w-full px-3 py-2 border rounded-xl text-sm transition min-h-[44px] flex flex-wrap gap-2 items-center text-left {disabled ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-white cursor-pointer hover:border-brand-blueRoyal'} {error ? 'border-rose-500' : 'border-slate-200'}"
        onclick={toggleOpen}
        aria-disabled={disabled}
        aria-haspopup="listbox"
        aria-expanded={isOpen}
    >
        {#if selectedOptions.length === 0}
            <span class="text-slate-400 px-1">{placeholder}</span>
        {:else}
            {#each selectedOptions as option}
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-xs font-bold text-slate-700">
                    {option.name}
                    <button type="button" aria-label="Remove {option.name}" onclick={(e) => removeOption(e, option.id || option.name)} class="text-slate-400 hover:text-rose-500 transition">
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
        <div class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 flex flex-col overflow-hidden" role="listbox">
            <div class="p-2 border-b border-slate-100 bg-slate-50 sticky top-0">
                <div class="relative">
                    <i class="ti ti-search absolute left-3 top-2.5 text-slate-400"></i>
                    <input 
                        type="text" 
                        bind:value={search} 
                        placeholder="Cari..." 
                        bind:this={searchInput}
                        onkeydown={handleKeyDown}
                        class="w-full pl-9 pr-3 py-2 text-sm bg-white border border-slate-200 rounded-lg outline-none focus:border-brand-blueRoyal"
                    >
                </div>
            </div>
            
            <div bind:this={optionsContainer} class="overflow-y-auto flex-grow">
                {#if filteredOptions.length === 0}
                    <div class="p-4 text-center text-sm text-slate-500">Tidak ada hasil</div>
                {:else}
                    {#each filteredOptions as option, index}
                        {@const isSelected = value.includes(option.id) || value.includes(option.name)}
                        <!-- svelte-ignore a11y_click_events_have_key_events -->
                        <label 
                            data-active={index === activeIndex}
                            class="flex items-center gap-3 px-4 py-2.5 text-sm cursor-pointer transition-colors
                                {isSelected ? 'bg-brand-blueRoyal/5' : ''}
                                {index === activeIndex ? 'bg-slate-100' : 'hover:bg-slate-50'}"
                        >
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
