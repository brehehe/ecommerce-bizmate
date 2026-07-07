<script lang="ts">
    let { 
        value = $bindable(''), 
        id = '', 
        label = '', 
        placeholder = '0', 
        required = false,
        prefix = 'Rp',
        readonly = false,
        error = '',
        oninput = undefined
    } = $props();

    let displayValue = $state('');

    function formatCurrency(val) {
        if (val === null || val === undefined || val === '') return '';
        // Extract the integer part (ignore decimals from DB like .00)
        let numStr = val.toString().split('.')[0];
        return numStr.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    $effect(() => {
        let strippedDisplay = displayValue.toString().replace(/\./g, '');
        // Extract integer part from current value
        let currentValString = (value ?? '').toString().split('.')[0];
        
        if (strippedDisplay !== currentValString) {
            displayValue = formatCurrency(currentValString);
        }
    });

    function handleInput(e) {
        let raw = e.target.value;
        let numeric = raw.replace(/\D/g, '');
        
        displayValue = formatCurrency(numeric);
        value = numeric ? parseInt(numeric, 10) : '';
        
        if (oninput) oninput(e);
    }

    let paddingLeft = $derived(
        prefix === 'Rp' ? '44px'
        : prefix ? `${prefix.length * 10 + 20}px`
        : null
    );
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
    <div class="relative">
        {#if prefix}
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold select-none pointer-events-none">{prefix}</span>
        {/if}
        
        <input 
            type="text"
            {id} 
            value={displayValue}
            {placeholder}
            {required}
            {readonly}
            oninput={handleInput}
            style={paddingLeft ? `padding-left: ${paddingLeft}` : undefined}
            class="w-full px-3 py-2 border rounded-xl text-sm focus:ring-2 focus:ring-brand-blueRoyal focus:outline-none transition {readonly ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : 'bg-white'} {error ? 'border-rose-500 focus:ring-rose-500/20 focus:border-rose-500' : 'border-slate-200 hover:border-slate-350'}"
        >
    </div>
    {#if error}
        <span class="text-xs text-rose-500 font-medium block mt-1">{error}</span>
    {/if}
</div>
