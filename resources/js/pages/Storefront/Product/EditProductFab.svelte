<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    let { canEditProduct, product, primary, secondary, isAdmin }: any =
        $props();
</script>

<!-- ── FLOATING ACTION BUTTON: Edit Produk (Super Admin, Admin, & Owner Seller) ── -->
{#if canEditProduct}
    <aside
        aria-label="Aksi Cepat Pemilik Produk"
        class="fixed z-40 flex items-center gap-2 bottom-20 right-4 md:bottom-24 md:right-6 transition-all duration-300 pointer-events-auto"
    >
        <Link
            href="/admin/products/{product.id}/edit"
            class="flex items-center gap-2.5 px-4 py-2.5 rounded-full text-white shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105 active:scale-95 border-2 border-white/40 backdrop-blur-md cursor-pointer group"
            style="background: linear-gradient(135deg, {primary}, {secondary});"
            title="Bypass Edit Produk Ini"
        >
            <div
                class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center shrink-0 group-hover:rotate-12 transition-transform duration-300"
            >
                <i class="ti ti-pencil text-sm text-white"></i>
            </div>
            <div class="flex flex-col text-left pr-1">
                <span class="text-xs font-black leading-tight tracking-tight"
                    >Edit Produk</span
                >
                <span
                    class="text-[9px] text-white/80 font-semibold leading-none"
                >
                    {isAdmin ? 'Akses Admin' : 'Pemilik Toko'}
                </span>
            </div>
        </Link>
    </aside>
{/if}
