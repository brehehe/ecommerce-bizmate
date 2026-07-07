const recentToasts = new Map<string, number>();

export function showToast(
    message: string,
    type: 'success' | 'error' = 'success',
    position: 'top' | 'bottom' = 'bottom',
) {
    const normalized = message.toLowerCase().replace(/[^a-z0-9]/g, '');
    const now = Date.now();
    const lastTime = recentToasts.get(normalized);

    if (lastTime && now - lastTime < 1000) {
        // Skip duplicate toasts triggered within 1 second
        return;
    }

    recentToasts.set(normalized, now);

    // Clean up old entries to prevent memory leaks
    if (recentToasts.size > 100) {
        for (const [key, val] of recentToasts.entries()) {
            if (now - val > 5000) {
                recentToasts.delete(key);
            }
        }
    }

    const containerId = `toast-container-${position}`;
    let container = document.getElementById(containerId);

    // Create container if it doesn't exist
    if (!container) {
        container = document.createElement('div');
        container.id = containerId;
        container.className =
            position === 'top'
                ? 'fixed top-4 left-1/2 -translate-x-1/2 md:left-auto md:right-4 md:translate-x-0 z-[9999] flex flex-col gap-3 items-center md:items-end'
                : 'fixed bottom-4 left-1/2 -translate-x-1/2 md:left-auto md:right-4 md:translate-x-0 z-[9999] flex flex-col gap-3 items-center md:items-end';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const icon =
        type === 'success'
            ? 'ti-circle-check-filled text-emerald-500'
            : 'ti-circle-x-filled text-rose-500';

    const initTransform =
        position === 'top' ? '-translate-y-4' : 'translate-y-4';
    const endTransform =
        position === 'top' ? '-translate-y-2' : 'translate-y-2';

    toast.className = `flex items-center gap-3 bg-white border border-slate-200 shadow-xl px-4 py-3 rounded-2xl min-w-[260px] max-w-sm transition-all duration-300 ${initTransform} opacity-0`;
    toast.innerHTML = `
        <i class="ti ${icon} text-xl shrink-0"></i>
        <p class="text-xs font-bold text-slate-800 flex-grow">${message}</p>
        <button class="text-slate-400 hover:text-slate-600 transition shrink-0" onclick="this.parentElement.remove()" aria-label="Tutup"><i class="ti ti-x text-sm"></i></button>
    `;

    // If top position, prepend to make newer toasts appear on top
    if (position === 'top') {
        container.insertBefore(toast, container.firstChild);
    } else {
        container.appendChild(toast);
    }

    // Animate in
    setTimeout(() => toast.classList.remove(initTransform, 'opacity-0'), 50);

    // Animate out and remove
    setTimeout(() => {
        toast.classList.add(endTransform, 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}
