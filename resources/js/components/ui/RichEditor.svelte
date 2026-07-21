<script lang="ts">
    import { onMount } from 'svelte';
    import 'quill/dist/quill.snow.css';
    import '@enzedonline/quill-blot-formatter2/dist/css/quill-blot-formatter2.css';

    let {
        value = $bindable(''),
        id = '',
        label = '',
        placeholder = 'Tulis deskripsi di sini...',
        required = false,
        error = ''
    } = $props();

    let editorContainer = $state<HTMLDivElement | null>(null);
    let quillInstance = $state<any>(null);
    let isUpdatingFromInside = false;

    onMount(async () => {
        if (!editorContainer) return;

        // Dynamically import Quill and BlotFormatter to prevent SSR build issues
        const { default: Quill } = await import('quill');
        const { default: BlotFormatter } = await import('@enzedonline/quill-blot-formatter2');

        // Register the formatting/resizing module
        Quill.register('modules/blotFormatter', BlotFormatter);

        quillInstance = new Quill(editorContainer, {
            theme: 'snow',
            placeholder: placeholder,
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image', 'video'],
                    ['clean']
                ],
                blotFormatter: {}
            }
        });

        // Set initial content if exists
        if (value) {
            quillInstance.root.innerHTML = value;
        }

        // Sync Quill changes to bindable value
        quillInstance.on('text-change', () => {
            isUpdatingFromInside = true;
            const html = quillInstance.root.innerHTML;
            // If it's just an empty paragraph, treat it as empty
            value = html === '<p><br></p>' ? '' : html;
            isUpdatingFromInside = false;
        });

        // Listen for custom imperative updates
        editorContainer.addEventListener('update-html', (e: any) => {
            if (quillInstance) {
                isUpdatingFromInside = true;
                quillInstance.root.innerHTML = e.detail || '';
                value = e.detail || '';
                isUpdatingFromInside = false;
            }
        });
    });

    // Reactive update from parent (value changes externally)
    // Reactive update from parent (value changes externally)
    $effect(() => {
        console.log('RichEditor $effect triggered', {
            hasQuill: !!quillInstance,
            isUpdatingFromInside,
            value
        });
        if (quillInstance && !isUpdatingFromInside) {
            const currentHTML = quillInstance.root.innerHTML;
            const targetHTML = value || '';
            
            // Normalize empty states
            const isEmptyTarget = targetHTML === '' || targetHTML === '<p><br></p>';
            const isEmptyCurrent = currentHTML === '' || currentHTML === '<p><br></p>';

            console.log('RichEditor state comparison', {
                currentHTML,
                targetHTML,
                isEmptyTarget,
                isEmptyCurrent,
                shouldUpdate: targetHTML !== currentHTML && !(isEmptyTarget && isEmptyCurrent)
            });

            if (targetHTML !== currentHTML && !(isEmptyTarget && isEmptyCurrent)) {
                quillInstance.root.innerHTML = targetHTML;
            }
        }
    });
</script>

<div class="space-y-2 w-full">
    {#if label}
        <label for={id} class="text-xs font-bold text-slate-600 block">
            {label}
            {#if required}
                <span class="text-rose-500">*</span>
            {/if}
        </label>
    {/if}

    <div class="quill-editor-wrapper {error ? 'quill-error' : ''}">
        <div bind:this={editorContainer} {id}></div>
    </div>

    {#if error}
        <span class="text-xs text-rose-500 font-medium block mt-1">{error}</span>
    {/if}
</div>

<style>
    /* Premium styling to match dashboard UI style and border-radius */
    .quill-editor-wrapper :global(.ql-toolbar.ql-snow) {
        border-top-left-radius: 0.75rem;
        border-top-right-radius: 0.75rem;
        border-color: #cbd5e1; /* border-slate-300 */
        background-color: #f8fafc; /* bg-slate-50 */
        padding: 0.5rem 0.75rem;
        font-family: inherit;
    }

    .quill-editor-wrapper :global(.ql-container.ql-snow) {
        border-bottom-left-radius: 0.75rem;
        border-bottom-right-radius: 0.75rem;
        border-color: #cbd5e1; /* border-slate-300 */
        min-height: 250px;
        font-family: inherit;
        font-size: 0.875rem; /* text-sm */
        background-color: #ffffff;
    }

    /* Hover effect */
    .quill-editor-wrapper:not(.quill-error):hover :global(.ql-toolbar.ql-snow),
    .quill-editor-wrapper:not(.quill-error):hover :global(.ql-container.ql-snow) {
        border-color: #94a3b8; /* border-slate-400 */
    }

    /* Error state border colors */
    .quill-editor-wrapper.quill-error :global(.ql-toolbar.ql-snow),
    .quill-editor-wrapper.quill-error :global(.ql-container.ql-snow) {
        border-color: #f43f5e; /* border-rose-500 */
    }

    /* Focus styling (when active or clicking inside) */
    .quill-editor-wrapper :global(.ql-container.ql-snow:focus-within),
    .quill-editor-wrapper :global(.ql-editor:focus) {
        outline: none;
    }

    .quill-editor-wrapper :global(.ql-container.ql-snow:focus-within) {
        border-color: var(--color-brand-blueRoyal, #0c4cb4);
        box-shadow: 0 0 0 2px rgba(12, 76, 180, 0.15);
        z-index: 1;
    }

    .quill-editor-wrapper :global(.ql-toolbar.ql-snow:focus-within) {
        border-color: var(--color-brand-blueRoyal, #0c4cb4);
    }

    /* Placeholder text style */
    .quill-editor-wrapper :global(.ql-editor.ql-blank::before) {
        font-style: normal;
        color: #94a3b8; /* text-slate-400 */
        font-weight: 500;
        left: 15px;
    }

    .quill-editor-wrapper :global(.ql-editor) {
        padding: 0.75rem 1rem;
        min-height: 250px;
        line-height: 1.5;
    }

    /* Disable pointer-events on iframes (videos) during edit mode so resize handles are clickable */
    .quill-editor-wrapper :global(.ql-editor iframe) {
        pointer-events: none;
    }
</style>
