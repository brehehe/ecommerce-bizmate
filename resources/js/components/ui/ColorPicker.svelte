<script lang="ts">
    import '@melloware/coloris/dist/coloris.css';
    import Coloris from '@melloware/coloris';
    import { onMount } from 'svelte';

    let { 
        value = $bindable(''), 
        id = '', 
        label = '',
        class: className = '',
        required = false,
        readonly = false
    } = $props();

    let inputEl: HTMLInputElement;

    onMount(() => {
        Coloris.init();
        Coloris({
            el: inputEl,
            theme: 'pill',
            themeMode: 'light',
            onChange: (color) => {
                value = color;
            }
        });
    });
</script>

<div class="space-y-2">
    {#if label}
        <label for={id} class="text-xs font-bold text-slate-600 block">
            {label}
            {#if required}
                <span class="text-rose-500">*</span>
            {/if}
        </label>
    {/if}
    <div class="relative color-picker-wrapper">
        <input
            type="text"
            {id}
            bind:value
            bind:this={inputEl}
            {required}
            {readonly}
            class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-brand-blueRoyal focus:outline-none transition {className} {readonly ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : 'bg-white'}"
            data-coloris
        />
    </div>
</div>

<style>
    .color-picker-wrapper :global(.clr-field) {
        display: block;
        width: 100%;
    }
    .color-picker-wrapper :global(.clr-field input) {
        width: 100%;
    }
</style>
