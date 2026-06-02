<script lang="ts">
    let { 
        value = $bindable(''), 
        id = '', 
        label = '', 
        placeholder = '', 
        type = 'text',
        required = false,
        prefix = '', // like '@'
        icon = '', // like 'ti-search'
        readonly = false,
        min = null,
        max = null,
        error = '',
        oninput = undefined
    } = $props();

    // Dynamically calculate left padding based on the prefix value or icon to prevent overlap
    let paddingLeft = $derived(
        icon ? '44px'
        : prefix === 'Rp' ? '44px'
        : prefix === 'Gram' ? '64px'
        : prefix === 'Cm' ? '44px'
        : prefix === '@' ? '36px'
        : prefix ? `${prefix.length * 10 + 20}px`
        : null
    );

    let showPassword = $state(false);
    let inputType = $derived(type === 'password' ? (showPassword ? 'text' : 'password') : type);

    function togglePassword() {
        showPassword = !showPassword;
    }
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
        {#if icon}
            <i class="ti {icon} absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none"></i>
        {:else if prefix}
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold select-none pointer-events-none">{prefix}</span>
        {/if}
        
        <input 
            type={inputType}
            {id} 
            bind:value 
            {placeholder}
            {required}
            {readonly}
            {min}
            {max}
            oninput={(e) => {
                if (oninput) oninput(e);
            }}
            style={paddingLeft ? `padding-left: ${paddingLeft}` : undefined}
            class="w-full px-3 py-2 border rounded-xl text-sm focus:ring-2 focus:ring-brand-blueRoyal focus:outline-none transition {readonly ? 'bg-slate-50 text-slate-500 cursor-not-allowed' : 'bg-white'} {error ? 'border-rose-500 focus:ring-rose-500/20 focus:border-rose-500' : 'border-slate-200 hover:border-slate-350'} {type === 'password' ? 'pr-10' : ''}"
        >

        {#if type === 'password'}
            <button 
                type="button" 
                aria-label={showPassword ? "Sembunyikan password" : "Tampilkan password"}
                tabindex="-1"
                onclick={togglePassword}
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition flex items-center justify-center w-6 h-6"
            >
                <i class="ti {showPassword ? 'ti-eye-off' : 'ti-eye'} text-lg"></i>
            </button>
        {/if}
    </div>
    {#if error}
        <span class="text-xs text-rose-500 font-medium block mt-1">{error}</span>
    {/if}
</div>
