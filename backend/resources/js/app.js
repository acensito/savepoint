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

/**
 * Toasts: aviso flotante que aparece y se desvanece solo, en vez del banner
 * fijo que antes había que repetir (y a veces se olvidaba) en cada vista.
 * Expuesto en window porque lo dispara un <script> normal (no módulo) desde
 * el layout cuando hay un mensaje flash en la sesión.
 */
function createToastContainer() {
    return document.getElementById('toast-container');
}

window.showToast = function (message, type = 'success') {
    const container = createToastContainer();
    if (!container || !message) return;

    const palette = type === 'error'
        ? ['bg-red-500/10', 'border-red-500/20', 'text-red-400']
        : ['bg-emerald-500/10', 'border-emerald-500/20', 'text-emerald-400'];

    const toast = document.createElement('div');
    toast.className = [
        'flex items-start gap-2 border rounded-lg px-4 py-2.5 text-sm shadow-lg shadow-black/30',
        'opacity-0 -translate-y-1 transition-all duration-200 pointer-events-auto',
        ...palette,
    ].join(' ');
    toast.setAttribute('role', 'status');

    const icon = document.createElement('span');
    icon.className = 'material-symbols-outlined text-[18px] flex-shrink-0';
    icon.textContent = type === 'error' ? 'error' : 'check_circle';

    // textContent (no innerHTML): el mensaje puede incluir un título de
    // juego/edición/etc. metido por el usuario, así que nunca se interpreta
    // como HTML.
    const text = document.createElement('span');
    text.className = 'flex-1';
    text.textContent = message;

    toast.append(icon, text);
    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('opacity-0', '-translate-y-1');
    });

    setTimeout(() => {
        toast.classList.add('opacity-0');
        setTimeout(() => toast.remove(), 200);
    }, 4000);
};

/**
 * Confirmación de borrado: intercepta el submit de cualquier <form
 * class="js-confirm-delete" data-confirm-title="..." data-confirm-message="...">
 * y muestra el <dialog> compartido del layout en vez del confirm() nativo.
 */
function initConfirmDialogs() {
    const dialog = document.getElementById('confirm-dialog');
    if (!dialog) return;

    const titleEl = document.getElementById('confirm-dialog-title');
    const messageEl = document.getElementById('confirm-dialog-message');
    const cancelBtn = document.getElementById('confirm-dialog-cancel');
    const acceptBtn = document.getElementById('confirm-dialog-accept');

    let pendingForm = null;

    document.addEventListener('submit', (e) => {
        const form = e.target.closest('.js-confirm-delete');
        if (!form || form.dataset.confirmed === '1') return;

        e.preventDefault();
        pendingForm = form;

        titleEl.textContent = form.dataset.confirmTitle || '¿Seguro?';
        messageEl.textContent = form.dataset.confirmMessage || 'Esta acción no se puede deshacer.';
        acceptBtn.textContent = form.dataset.confirmAccept || 'Confirmar';

        dialog.showModal();
    });

    cancelBtn.addEventListener('click', () => dialog.close());

    acceptBtn.addEventListener('click', () => {
        if (!pendingForm) return;

        // Marcamos el form para que el listener de arriba no lo vuelva a
        // interceptar en este segundo submit (el de verdad).
        pendingForm.dataset.confirmed = '1';
        const form = pendingForm;
        dialog.close();
        form.requestSubmit();
        delete form.dataset.confirmed;
    });

    dialog.addEventListener('close', () => { pendingForm = null; });
}

initConfirmDialogs();