import { createInertiaApp } from '@inertiajs/svelte';
import './echo';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#0c4cb4',
    },
});
