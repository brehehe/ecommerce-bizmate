<script lang="ts">
    import { onDestroy } from 'svelte';

    let {
        value = $bindable(''),
        options = [],
        label = '',
        placeholder = 'Pilih salah satu',
        required = false,
        disabled = false,
        error = '',
        onchange = null,
    } = $props();

    let isOpen = $state(false);
    let search = $state('');
    let triggerNode: HTMLElement | null = $state(null);
    let dropdownNode: HTMLElement | null = $state(null);
    let searchInput: HTMLInputElement | null = $state(null);

    // Dropdown position
    let dropTop = $state(0);
    let dropLeft = $state(0);
    let dropWidth = $state(0);
    let dropAbove = $state(false);

    const DROPDOWN_MAX_H = 256; // max-h-64

    function computePosition() {
        if (!triggerNode) return;
        const rect = triggerNode.getBoundingClientRect();
        const viewportH = window.innerHeight;
        const spaceBelow = viewportH - rect.bottom;
        const spaceAbove = rect.top;

        dropAbove = spaceBelow < DROPDOWN_MAX_H && spaceAbove > spaceBelow;
        dropWidth = rect.width;
        dropLeft = rect.left;

        if (dropAbove) {
            dropTop = rect.top - 4; // will anchor to bottom of dropdown
        } else {
            dropTop = rect.bottom + 4;
        }
    }

    // Derived filtered options
    let filteredOptions = $derived(
        options.filter((opt) =>
            opt.name.toLowerCase().includes(search.toLowerCase()),
        ),
    );

    // Derived selected label
    let selectedLabel = $derived(
        options.find((opt) => opt.id == value || opt.name == value)?.name ||
            value ||
            '',
    );

    function toggleOpen() {
        if (disabled) return;
        if (!isOpen) {
            isOpen = true;
            computePosition();
            search = '';
            setTimeout(() => {
                if (searchInput) searchInput.focus();
            }, 10);
        } else {
            isOpen = false;
        }
    }

    let activeIndex = $state(-1);
    let optionsContainer: HTMLDivElement | null = $state(null);

    $effect(() => {
        if (isOpen) {
            if (filteredOptions.length > 0) {
                const foundIdx = filteredOptions.findIndex(opt => opt.id == value || opt.name == value);
                activeIndex = foundIdx !== -1 ? foundIdx : 0;
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
                selectOption(filteredOptions[activeIndex].id);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            isOpen = false;
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
        if (!isOpen) return;
        const clickedTrigger = triggerNode?.contains(event.target);
        const clickedDropdown = dropdownNode?.contains(event.target);
        if (!clickedTrigger && !clickedDropdown) {
            isOpen = false;
        }
    }

    function handleScroll() {
        if (isOpen) computePosition();
    }
</script>

<svelte:window onclick={handleWindowClick} onscroll={handleScroll} />

<div class="space-y-1.5">
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
        bind:this={triggerNode}
        role="button"
        tabindex="0"
        class="w-full px-3 py-2 border rounded-xl text-sm transition flex justify-between items-center {disabled ? 'bg-slate-50 text-slate-400 cursor-not-allowed' : 'bg-white cursor-pointer hover:border-brand-blueRoyal'} {error ? 'border-rose-500' : 'border-slate-200'} {isOpen ? 'border-brand-blueRoyal ring-2 ring-brand-blueRoyal/10' : ''}"
        onclick={toggleOpen}
        onkeydown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleOpen();
            }
        }}
    >
        <span class="truncate {selectedLabel ? 'text-slate-800' : 'text-slate-400'}">
            {selectedLabel || placeholder}
        </span>
        <i class="ti ti-chevron-down text-slate-400 transition-transform duration-200 {isOpen ? 'rotate-180' : ''}"></i>
    </div>

    {#if error}
        <span class="text-xs text-rose-500 font-medium block">{error}</span>
    {/if}
</div>

<!-- Portal-style dropdown rendered at fixed position -->
{#if isOpen}
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        bind:this={dropdownNode}
        class="fixed z-[9999] bg-white border border-slate-200 rounded-xl shadow-xl max-h-64 flex flex-col overflow-hidden"
        style="
            width: {dropWidth}px;
            left: {dropLeft}px;
            {dropAbove
                ? `bottom: calc(100vh - ${dropTop}px);`
                : `top: ${dropTop}px;`}
        "
    >
        <!-- Search -->
        <div class="p-2 border-b border-slate-100 bg-slate-50 shrink-0">
            <div class="relative">
                <i class="ti ti-search absolute left-3 top-2.5 text-slate-400 text-sm"></i>
                <input
                    type="text"
                    bind:value={search}
                    placeholder="Cari..."
                    bind:this={searchInput}
                    onkeydown={handleKeyDown}
                    class="w-full pl-8 pr-3 py-2 text-sm bg-white border border-slate-200 rounded-lg outline-none focus:border-brand-blueRoyal transition"
                />
            </div>
        </div>

        <!-- Options list -->
        <div bind:this={optionsContainer} class="overflow-y-auto flex-grow">
            {#if filteredOptions.length === 0}
                <div class="p-4 text-center text-sm text-slate-400">Tidak ada hasil</div>
            {:else}
                {#each filteredOptions as option, index}
                    <!-- svelte-ignore a11y_click_events_have_key_events -->
                    <div
                        role="button"
                        tabindex="0"
                        data-active={index === activeIndex}
                        class="px-4 py-2.5 text-sm cursor-pointer flex items-center gap-2 transition-colors
                            {value === option.id || value === option.name
                                ? 'bg-brand-blueRoyal/5 text-brand-blueRoyal font-bold'
                                : ''}
                            {index === activeIndex
                                ? 'bg-slate-100 text-slate-900 font-bold'
                                : 'text-slate-700 hover:bg-slate-50'}"
                        onclick={() => selectOption(option.id)}
                    >
                        {#if value === option.id || value === option.name}
                            <i class="ti ti-check text-xs shrink-0"></i>
                        {:else}
                            <span class="w-3.5 shrink-0"></span>
                        {/if}
                        {option.name}
                    </div>
                {/each}
            {/if}
        </div>
    </div>
{/if}
