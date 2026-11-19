<script lang="ts">
    import { parseToppingsAndNote } from '@/utils/orderModifiers';

    interface Props {
        item: any;
        class?: string;
        compact?: boolean;
    }

    let { item, class: className = '', compact = false }: Props = $props();

    const info = $derived(parseToppingsAndNote(item));
</script>

{#if info.toppings.length > 0 || info.note}
    <div class="space-y-1.5 {compact ? 'text-[11px]' : 'text-xs'} {className}">
        {#if info.toppings.length > 0}
            <div>
                <p class="font-semibold text-slate-700 dark:text-slate-300">Toping :</p>
                <ol class="list-decimal list-inside pl-0.5 space-y-0.5 text-slate-600 dark:text-slate-400">
                    {#each info.toppings as topping}
                        <li class="leading-relaxed">
                            {#if topping.group}
                                <span class="text-slate-500 dark:text-slate-400">{topping.group}: </span>
                            {/if}
                            <span class="font-medium text-slate-800 dark:text-slate-200">{topping.name}</span>
                            {#if topping.price > 0 && !topping.name.includes('(+Rp')}
                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold text-[10.5px] ml-0.5">
                                    (+Rp {topping.price.toLocaleString('id-ID')})
                                </span>
                            {/if}
                        </li>
                    {/each}
                </ol>
            </div>
        {/if}

        {#if info.note}
            <div class={info.toppings.length > 0 ? 'mt-1' : ''}>
                <p class="font-semibold text-slate-700 dark:text-slate-300">Catatan :</p>
                <p class="text-slate-600 dark:text-slate-400 italic pl-0.5 leading-relaxed whitespace-pre-line">
                    {info.note}
                </p>
            </div>
        {/if}
    </div>
{/if}
