<script lang="ts">
    let {
        title,
        methods,
        fallbackIcon,
        kind,
        primary,
        methodColors,
        methodIcons,
        selectedMethodKey = $bindable(),
    }: {
        title: string;
        methods: any[];
        fallbackIcon: string;
        kind: 'bank' | 'wallet' | 'retail' | 'card';
        primary: string;
        methodColors: Record<string, string>;
        methodIcons: Record<string, string>;
        selectedMethodKey: string | null;
    } = $props();
</script>

{#if methods.length > 0}
    <div>
        <p
            class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 px-1"
        >
            {title}
        </p>
        <div class="grid grid-cols-2 gap-1.5">
            {#each methods as method (method.key)}
                {@const isSelected = selectedMethodKey === method.key}
                <button
                    type="button"
                    onclick={() => (selectedMethodKey = method.key)}
                    class="flex items-center gap-2 px-2.5 py-2.5 rounded-xl border-2 text-left transition {isSelected
                        ? 'border-transparent'
                        : 'border-slate-200 hover:border-slate-300'}"
                    style={isSelected
                        ? `border-color:${methodColors[method.key] ?? primary}; background:${methodColors[method.key] ?? primary}15`
                        : ''}
                >
                    <div
                        class="w-7 h-7 rounded-lg flex items-center justify-center text-white shrink-0 text-xs"
                        style="background:{methodColors[method.key] ?? primary}"
                    >
                        <i
                            class="ti {methodIcons[method.key] ??
                                fallbackIcon} text-xs"
                        ></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span
                            class="text-xs font-semibold text-slate-800 leading-tight block truncate"
                            >{method.label}</span
                        >
                        {#if kind === 'retail' && method.key === 'alfamart'}
                            <span
                                class="text-[9px] text-slate-400 font-medium block"
                                >Alfamidi, Dan+Dan</span
                            >
                        {:else if kind === 'retail' && method.key === 'indomaret'}
                            <span
                                class="text-[9px] text-slate-400 font-medium block"
                                >Gerai Indomaret</span
                            >
                        {:else if kind === 'card'}
                            <span
                                class="text-[9px] text-slate-400 font-medium block"
                                >Visa, Mastercard, JCB</span
                            >
                        {/if}
                    </div>
                    {#if isSelected}
                        <i
                            class="ti ti-circle-check text-sm shrink-0 ml-auto"
                            style="color:{methodColors[method.key] ?? primary}"
                        ></i>
                    {/if}
                </button>
            {/each}
        </div>
    </div>
{/if}
