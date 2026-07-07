import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

export default {
    preprocess: vitePreprocess(),
    compilerOptions: {
        warningFilter: (warning) => {
            // Suppress noisy accessibility and unused CSS compiler warning logs globally
            if (warning.code.startsWith('a11y_')) return false;
            if (warning.code === 'css_unused_selector') return false;
            return true;
        }
    }
};
