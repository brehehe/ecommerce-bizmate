<script lang="ts">
    import { page, Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

    let { status = 500, message = null } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    const errorDetails: Record<number, { 
        title: string; 
        subtitle: string; 
        icon: string; 
        gradient: string; 
        shadowColor: string; 
        glowColor: string;
        accentText: string;
        digitL: string;
        digitR: string;
    }> = {
        404: {
            title: 'Halaman Tidak Ditemukan',
            subtitle: 'Ups! Halaman yang Anda cari tidak ada atau telah dipindahkan ke alamat lain.',
            icon: 'ti-ghost',
            gradient: 'from-indigo-500 via-purple-500 to-rose-500',
            shadowColor: 'shadow-indigo-500/25',
            glowColor: 'bg-indigo-400/20',
            accentText: 'text-indigo-500',
            digitL: '4',
            digitR: '4'
        },
        403: {
            title: 'Akses Ditolak',
            subtitle: 'Maaf, Anda tidak memiliki izin atau otorisasi untuk mengakses halaman ini.',
            icon: 'ti-shield-lock',
            gradient: 'from-red-500 via-rose-500 to-orange-500',
            shadowColor: 'shadow-red-500/25',
            glowColor: 'bg-red-400/20',
            accentText: 'text-red-500',
            digitL: '4',
            digitR: '3'
        },
        500: {
            title: 'Gangguan Sistem Server',
            subtitle: 'Terjadi kesalahan sistem internal pada server kami. Kami sedang berupaya memperbaikinya segera.',
            icon: 'ti-server-off',
            gradient: 'from-amber-500 via-yellow-500 to-orange-500',
            shadowColor: 'shadow-amber-500/25',
            glowColor: 'bg-amber-400/20',
            accentText: 'text-amber-500',
            digitL: '5',
            digitR: '0'
        },
        503: {
            title: 'Pemeliharaan Sistem',
            subtitle: 'Server kami saat ini sedang dalam pemeliharaan berkala. Silakan kembali beberapa saat lagi.',
            icon: 'ti-cone-2',
            gradient: 'from-amber-500 via-orange-500 to-yellow-500',
            shadowColor: 'shadow-amber-500/25',
            glowColor: 'bg-amber-400/20',
            accentText: 'text-amber-500',
            digitL: '5',
            digitR: '3'
        }
    };

    const details = $derived(errorDetails[Number(status)] ?? {
        title: `Kesalahan #${status}`,
        subtitle: message ?? 'Terjadi kesalahan tak terduga pada sistem.',
        icon: 'ti-alert-circle',
        gradient: 'from-slate-500 via-slate-600 to-slate-700',
        shadowColor: 'shadow-slate-500/25',
        glowColor: 'bg-slate-400/20',
        accentText: 'text-slate-500',
        digitL: 'E',
        digitR: 'R'
    });

    function goBack() {
        if (typeof window !== 'undefined') {
            window.history.back();
        }
    }
</script>

<svelte:head>
    <title>{details.title} - {status}</title>
</svelte:head>

<div class="min-h-dvh w-full flex flex-col items-center justify-center p-6 bg-slate-50 font-sans relative overflow-hidden select-none">
    
    <!-- Premium background grid pattern -->
    <div class="absolute inset-0 bg-grid opacity-[0.35] pointer-events-none"></div>

    <!-- Glowing dynamic backdrop blobs -->
    <div class="absolute -top-[10%] -left-[10%] w-[50vw] h-[50vw] rounded-full {details.glowColor} blur-[120px] animate-float pointer-events-none"></div>
    <div class="absolute -bottom-[10%] -right-[10%] w-[50vw] h-[50vw] rounded-full {details.glowColor} blur-[120px] animate-float-delayed pointer-events-none"></div>

    <!-- Ambient drifting floating particles -->
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="particle p1"></div>
        <div class="particle p2"></div>
        <div class="particle p3"></div>
        <div class="particle p4"></div>
        <div class="particle p5"></div>
    </div>

    <!-- Main Container -->
    <div class="max-w-xl w-full text-center space-y-8 relative z-10 px-4">
        
        <!-- Premium Glassmorphic Main Card -->
        <div class="bg-white/80 backdrop-blur-xl border border-white/50 shadow-[0_20px_50px_rgba(0,0,0,0.06)] rounded-3xl p-8 sm:p-10 relative overflow-hidden">
            <!-- Subtle diagonal shine line -->
            <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-transparent pointer-events-none"></div>
            
            <div class="space-y-8">
                <!-- Large Futuristic Error Digit Display -->
                <div class="relative flex items-center justify-center font-outfit select-none">
                    <span class="text-8xl sm:text-9xl font-black text-slate-900 tracking-tighter leading-none opacity-90">{details.digitL}</span>
                    
                    <!-- Futuristic Rotating Core Portal (acts as the middle digit '0' or status indicator) -->
                    <div class="w-24 h-24 sm:w-28 sm:h-28 mx-3 rounded-[2rem] relative flex items-center justify-center overflow-hidden bg-slate-950 shadow-2xl border-4 border-slate-900 {details.shadowColor} transform hover:rotate-12 transition duration-500">
                        <!-- Portal particle ring -->
                        <div class="absolute inset-0 bg-gradient-to-tr {details.gradient} animate-spin duration-5000 opacity-90"></div>
                        <!-- Core overlay inner circle -->
                        <div class="absolute inset-[3px] rounded-[1.8rem] bg-slate-950 flex items-center justify-center">
                            <i class="ti {details.icon} text-4xl sm:text-5xl text-white animate-bounce-custom"></i>
                        </div>
                    </div>
                    
                    <span class="text-8xl sm:text-9xl font-black text-slate-900 tracking-tighter leading-none opacity-90">{details.digitR}</span>
                </div>

                <!-- Headline & friendly descriptions -->
                <div class="space-y-3">
                    <!-- Status bubble pill -->
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest bg-slate-900 text-white shadow-xs">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        Kesalahan Status {status}
                    </span>
                    
                    <h2 class="font-outfit font-black text-slate-900 text-2xl sm:text-3xl tracking-tight leading-none pt-2">
                        {details.title}
                    </h2>
                    
                    <p class="text-xs sm:text-sm text-slate-500 font-bold leading-relaxed max-w-md mx-auto">
                        {details.subtitle}
                    </p>
                </div>

                <div class="w-full h-px bg-slate-100/80"></div>

                <!-- Dual Action buttons -->
                <div class="flex flex-col sm:flex-row gap-3 items-stretch justify-center max-w-sm mx-auto">
                    <button 
                        onclick={goBack} 
                        class="flex-1 px-6 py-3.5 rounded-2xl border-2 border-slate-200 bg-white font-black text-xs text-slate-700 hover:bg-slate-50 hover:border-slate-350 active:scale-95 shadow-xs transition duration-200 flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <i class="ti ti-arrow-left text-sm"></i>
                        Kembali Ke Halaman
                    </button>
                    <Link 
                        href="/" 
                        class="flex-1 px-6 py-3.5 rounded-2xl font-black text-xs text-white transition active:scale-95 shadow-lg shadow-slate-950/5 flex items-center justify-center gap-2 cursor-pointer"
                        style="background: linear-gradient(135deg, {primary}, {secondary});"
                    >
                        <i class="ti ti-home text-sm"></i>
                        Beranda Toko
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <!-- Elegant Footer branding -->
    <div class="absolute bottom-6 left-6 right-6 text-center text-[10px] font-black text-slate-400 tracking-widest uppercase">
        &copy; {new Date().getFullYear()} {(page.props as any).settings?.store_name || (page.props as any).storeName || 'Bizmate Premium Store'} &bull; Semua Hak Dilindungi
    </div>
</div>

<style>
    /* CSS Grid background pattern */
    .bg-grid {
        background-size: 32px 32px;
        background-image: 
            linear-gradient(to right, #cbd5e1 1px, transparent 1px),
            linear-gradient(to bottom, #cbd5e1 1px, transparent 1px);
        mask-image: radial-gradient(circle at center, black 40%, transparent 85%);
        -webkit-mask-image: radial-gradient(circle at center, black 40%, transparent 85%);
    }

    /* Floating blobs animation */
    @keyframes float-anim {
        0%, 100% { transform: translateY(0px) scale(1); }
        50% { transform: translateY(-20px) scale(1.05); }
    }
    .animate-float {
        animation: float-anim 10s ease-in-out infinite;
    }
    .animate-float-delayed {
        animation: float-anim 12s ease-in-out infinite;
        animation-delay: 2s;
    }

    /* Ambient floating particles */
    .particle {
        position: absolute;
        border-radius: 50%;
        background-color: #cbd5e1;
        opacity: 0.45;
        pointer-events: none;
    }
    .p1 { width: 8px; height: 8px; top: 15%; left: 10%; animation: particle-float 15s linear infinite; }
    .p2 { width: 12px; height: 12px; top: 75%; left: 20%; animation: particle-float-delayed 18s linear infinite; }
    .p3 { width: 6px; height: 6px; top: 30%; right: 15%; animation: particle-float 14s linear infinite; }
    .p4 { width: 10px; height: 10px; top: 80%; right: 25%; animation: particle-float-delayed 16s linear infinite; }
    .p5 { width: 8px; height: 8px; top: 50%; left: 85%; animation: particle-float 17s linear infinite; }

    @keyframes particle-float {
        0%, 100% { transform: translateY(0) translateX(0); }
        50% { transform: translateY(-40px) translateX(20px); }
    }
    @keyframes particle-float-delayed {
        0%, 100% { transform: translateY(0) translateX(0); }
        50% { transform: translateY(-30px) translateX(-15px); }
    }

    /* Custom core portal animation */
    .duration-5000 {
        animation-duration: 5s;
    }
    @keyframes bounce-custom {
        0%, 100% { transform: translateY(0) scale(1); }
        50% { transform: translateY(-5px) scale(1.03); }
    }
    .animate-bounce-custom {
        animation: bounce-custom 2.5s ease-in-out infinite;
    }
</style>
