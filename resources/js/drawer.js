// Bottom-sheet drawer used by the catalog filters on phones. See resources/views/components/drawer.blade.php.

export function setDrawerOpen(drawer, open, opener = null) {
    if (open) {
        drawer.dataset.open = '';
        document.body.classList.add('overflow-hidden');
        drawer.querySelector('input, select, button:not([data-drawer-close])')?.focus();
    } else {
        delete drawer.dataset.open;
        document.body.classList.remove('overflow-hidden');
        opener?.focus();
    }
}

export function initDrawers(root = document) {
    let opener = null;

    root.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-drawer-open]');
        if (openButton) {
            opener = openButton;
            const drawer = root.getElementById(openButton.dataset.drawerOpen);
            if (drawer) setDrawerOpen(drawer, true);
            return;
        }

        if (event.target.closest('[data-drawer-close]')) {
            const drawer = event.target.closest('[data-drawer]');
            if (drawer) setDrawerOpen(drawer, false, opener);
        }
    });

    root.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const drawer = root.querySelector('[data-drawer][data-open]');
        if (drawer) setDrawerOpen(drawer, false, opener);
    });
}
