export function showToast(
    message: string,
    type: 'success' | 'error' = 'success',
) {
    let container = document.getElementById('toast-container');

    // Create container if it doesn't exist
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className =
            'fixed bottom-4 right-4 z-[9999] flex flex-col gap-3';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const icon =
        type === 'success'
            ? 'ti-circle-check-filled text-emerald-500'
            : 'ti-circle-x-filled text-rose-500';
    toast.className =
        'flex items-center gap-3 bg-white border border-slate-200 shadow-xl px-4 py-3 rounded-2xl min-w-[260px] max-w-sm transition-all duration-300 translate-y-4 opacity-0';
    toast.innerHTML = `
        <i class="ti ${icon} text-xl shrink-0"></i>
        <p class="text-xs font-bold text-slate-800 flex-grow">${message}</p>
        <button class="text-slate-400 hover:text-slate-600 transition shrink-0" onclick="this.parentElement.remove()" aria-label="Tutup"><i class="ti ti-x text-sm"></i></button>
    `;
    container.appendChild(toast);

    // Animate in
    setTimeout(() => toast.classList.remove('translate-y-4', 'opacity-0'), 50);

    // Animate out and remove
    setTimeout(() => {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}
