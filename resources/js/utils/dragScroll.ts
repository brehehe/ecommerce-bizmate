export function dragScroll(node: HTMLElement) {
    let isDown = false;
    let startX = 0;
    let scrollLeft = 0;

    function handleMouseDown(e: MouseEvent) {
        if (e.button !== 0) {
            return; // Only drag on left click
        }
        const target = e.target as HTMLElement;
        if (
            target.closest('a, button, input, select, label, [role="button"]')
        ) {
            return;
        }
        isDown = true;
        node.classList.add('cursor-grabbing', 'select-none');
        node.classList.remove('cursor-grab');
        startX = e.pageX - node.offsetLeft;
        scrollLeft = node.scrollLeft;
    }

    function handleMouseLeave() {
        if (!isDown) {
            return;
        }
        isDown = false;
        node.classList.remove('cursor-grabbing', 'select-none');
        node.classList.add('cursor-grab');
    }

    function handleMouseUp() {
        if (!isDown) {
            return;
        }
        isDown = false;
        node.classList.remove('cursor-grabbing', 'select-none');
        node.classList.add('cursor-grab');
    }

    function handleMouseMove(e: MouseEvent) {
        if (!isDown) {
            return;
        }
        e.preventDefault();
        const x = e.pageX - node.offsetLeft;
        const walk = (x - startX) * 1.5; // Scroll speed modifier
        node.scrollLeft = scrollLeft - walk;
    }

    node.classList.add('cursor-grab');
    node.addEventListener('mousedown', handleMouseDown);
    node.addEventListener('mouseleave', handleMouseLeave);
    node.addEventListener('mouseup', handleMouseUp);
    node.addEventListener('mousemove', handleMouseMove);

    return {
        destroy() {
            node.removeEventListener('mousedown', handleMouseDown);
            node.removeEventListener('mouseleave', handleMouseLeave);
            node.removeEventListener('mouseup', handleMouseUp);
            node.removeEventListener('mousemove', handleMouseMove);
        },
    };
}
