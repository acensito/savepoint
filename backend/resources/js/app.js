const SIDEBAR_STORAGE_KEY = 'sp:sidebarCollapsed';

function initSidebarToggle() {
    const toggle = document.getElementById('sidebar-toggle');

    if (!toggle) return;

    const syncAria = (collapsed) => {
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
    };

    syncAria(document.documentElement.classList.contains('sidebar-collapsed'));

    toggle.addEventListener('click', () => {
        const collapsed = !document.documentElement.classList.contains('sidebar-collapsed');

        document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
        syncAria(collapsed);

        try {
            localStorage.setItem(SIDEBAR_STORAGE_KEY, collapsed ? '1' : '0');
        } catch (e) {}
    });
}

initSidebarToggle();

const MOBILE_DRAWER_CLASS = 'mobile-drawer-open';

function initMobileDrawer() {
    const openButton = document.getElementById('sidebar-mobile-toggle');
    const closeButton = document.getElementById('sidebar-mobile-close');
    const backdrop = document.getElementById('sidebar-backdrop');
    const sidebar = document.getElementById('sidebar');

    if (!openButton || !sidebar) return;

    const setOpen = (open) => {
        document.documentElement.classList.toggle(MOBILE_DRAWER_CLASS, open);
        openButton.setAttribute('aria-expanded', String(open));
    };

    openButton.addEventListener('click', () => setOpen(true));
    closeButton?.addEventListener('click', () => setOpen(false));
    backdrop?.addEventListener('click', () => setOpen(false));

    // Cada enlace del menú navega a otra página, así que basta con cerrar el
    // drawer al pulsarlo (no hace falta gestionar el estado tras volver).
    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    // Si la ventana pasa a tamaño de escritorio con el drawer abierto, lo
    // cerramos para no dejar rastro del overlay al volver a estrechar.
    window.matchMedia('(min-width: 768px)').addEventListener('change', (e) => {
        if (e.matches) setOpen(false);
    });
}

initMobileDrawer();