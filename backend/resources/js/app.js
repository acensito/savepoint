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

const THEME_STORAGE_KEY = 'sp:theme';

/**
 * Alterna entre tema claro y oscuro. El cambio de color en sí lo hace
 * app.css (redefine las variables de Tailwind cuando <html> lleva la clase
 * 'light'); aquí solo se gestiona esa clase y su persistencia. El estado
 * inicial ya lo aplica el script bloqueante del <head> de cada página antes
 * del primer pintado, así que no hay parpadeo al cargar.
 */
function initThemeToggle() {
    document.querySelectorAll('.js-theme-toggle').forEach((toggle) => {
        const syncIcon = (isLight) => {
            const icon = toggle.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = isLight ? 'dark_mode' : 'light_mode';
            toggle.setAttribute('aria-label', isLight ? 'Cambiar a tema oscuro' : 'Cambiar a tema claro');
        };

        syncIcon(document.documentElement.classList.contains('light'));

        toggle.addEventListener('click', () => {
            const isLight = !document.documentElement.classList.contains('light');

            document.documentElement.classList.toggle('light', isLight);
            syncIcon(isLight);

            try {
                localStorage.setItem(THEME_STORAGE_KEY, isLight ? 'light' : 'dark');
            } catch (e) {}
        });
    });
}

initThemeToggle();

/**
 * Atajo "/" para enfocar el buscador de la colección (games/index.blade.php),
 * como en GitHub o Gmail. El buscador está duplicado en el markup (una copia
 * dentro del <details> colapsable de móvil, otra en el panel fijo de
 * escritorio); solo una de las dos es visible según el ancho de pantalla, así
 * que hay que localizar esa antes de enfocarla.
 */
function initGameSearchShortcut() {
    const inputs = document.querySelectorAll('.js-game-search');
    if (!inputs.length) return;

    document.addEventListener('keydown', (e) => {
        if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;

        const active = document.activeElement;
        const isTyping = active && (
            active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable
        );
        if (isTyping) return;

        // En móvil el buscador vive dentro de un <details> colapsado: si su
        // envoltorio está en pantalla (estamos en móvil) pero cerrado, lo
        // abrimos antes de comprobar qué instancia enfocar.
        inputs.forEach((input) => {
            const details = input.closest('details');
            if (details && !details.open && details.offsetParent !== null) {
                details.open = true;
            }
        });

        const visible = Array.from(inputs).find((input) => input.offsetParent !== null);
        if (!visible) return;

        e.preventDefault();
        visible.focus();
        visible.select();
    });
}

initGameSearchShortcut();

/**
 * Acciones en bloque en la colección (games/index.blade.php): las casillas
 * de cada fila/tarjeta no viven dentro de un <form> propio (irían anidadas
 * dentro del <form> individual de "Borrar" de esa misma fila, lo cual es
 * HTML inválido); en vez de eso se asocian al <form id="bulk-form"> de la
 * barra de acciones mediante el atributo form="bulk-form", estén donde
 * estén en el documento.
 */
function initBulkActions() {
    const checkboxes = Array.from(document.querySelectorAll('.js-bulk-checkbox'));
    if (!checkboxes.length) return;

    const bar = document.getElementById('bulk-bar');
    const countEl = document.getElementById('bulk-count');
    const selectAll = document.getElementById('bulk-select-all');

    // La colección se pinta dos veces (tarjetas en móvil, tabla en
    // escritorio) y solo una de las dos copias está visible según el ancho
    // de pantalla; sin filtrar por visibilidad, "seleccionar todo" y el
    // contador duplicarían cada juego (una vez por copia).
    const visibleCheckboxes = () => checkboxes.filter((cb) => cb.offsetParent !== null);

    const sync = () => {
        const visible = visibleCheckboxes();
        const count = visible.filter((cb) => cb.checked).length;

        bar?.classList.toggle('hidden', count === 0);
        if (countEl) {
            countEl.textContent = `${count} ${count === 1 ? 'juego seleccionado' : 'juegos seleccionados'}`;
        }

        if (selectAll) {
            selectAll.checked = count > 0 && count === visible.length;
            selectAll.indeterminate = count > 0 && count < visible.length;
        }
    };

    checkboxes.forEach((cb) => cb.addEventListener('change', sync));

    selectAll?.addEventListener('change', () => {
        visibleCheckboxes().forEach((cb) => { cb.checked = selectAll.checked; });
        sync();
    });

    // Tras una acción en bloque se vuelve a esta misma página (redirect), así
    // que conviene partir siempre de "nada seleccionado" en vez de arrastrar
    // el estado de las casillas si el navegador restaura el formulario.
    checkboxes.forEach((cb) => { cb.checked = false; });
    sync();
}

initBulkActions();

const GAMES_VIEW_STORAGE_KEY = 'sp:gamesView';

/**
 * Alterna la colección entre la vista habitual (tarjetas en móvil, tabla en
 * escritorio) y la de estantería (grid de carátulas grandes). Igual que el
 * tema, el cambio de vista en sí lo hace app.css según la clase
 * 'games-grid-view' en <html>; aquí solo se gestiona esa clase y su
 * persistencia, y el estado inicial ya lo aplica el script bloqueante del
 * <head> antes del primer pintado.
 */
function initGamesViewToggle() {
    const listBtn = document.getElementById('games-view-list-btn');
    const gridBtn = document.getElementById('games-view-grid-btn');
    if (!listBtn || !gridBtn) return;

    const syncButtons = (isGrid) => {
        listBtn.classList.toggle('text-indigo-400', !isGrid);
        listBtn.classList.toggle('text-slate-500', isGrid);
        gridBtn.classList.toggle('text-indigo-400', isGrid);
        gridBtn.classList.toggle('text-slate-500', !isGrid);
        listBtn.setAttribute('aria-pressed', String(!isGrid));
        gridBtn.setAttribute('aria-pressed', String(isGrid));
    };

    const setView = (isGrid) => {
        document.documentElement.classList.toggle('games-grid-view', isGrid);
        syncButtons(isGrid);

        try {
            localStorage.setItem(GAMES_VIEW_STORAGE_KEY, isGrid ? 'grid' : 'list');
        } catch (e) {}
    };

    syncButtons(document.documentElement.classList.contains('games-grid-view'));

    listBtn.addEventListener('click', () => setView(false));
    gridBtn.addEventListener('click', () => setView(true));
}

initGamesViewToggle();

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
 * Confirmación de borrado: intercepta el submit de cualquier formulario cuyo
 * botón de envío (o el propio <form>, si el botón no lleva la marca) tenga
 * class="js-confirm-delete" data-confirm-title="..." data-confirm-message="...">,
 * y muestra el <dialog> compartido del layout en vez del confirm() nativo.
 * Se busca primero en el "submitter" (no solo en el <form>) para poder tener
 * varios botones con distinto formaction asociados al mismo formulario (p.
 * ej. las acciones en bloque de la colección), cada uno con su propia
 * confirmación.
 */
function initConfirmDialogs() {
    const dialog = document.getElementById('confirm-dialog');
    if (!dialog) return;

    const titleEl = document.getElementById('confirm-dialog-title');
    const messageEl = document.getElementById('confirm-dialog-message');
    const cancelBtn = document.getElementById('confirm-dialog-cancel');
    const acceptBtn = document.getElementById('confirm-dialog-accept');

    let pendingForm = null;
    let pendingSubmitter = null;

    document.addEventListener('submit', (e) => {
        const trigger = e.submitter || e.target;
        const configEl = trigger.closest('.js-confirm-delete');
        if (!configEl || configEl.dataset.confirmed === '1') return;

        e.preventDefault();
        pendingForm = e.target;
        pendingSubmitter = e.submitter || null;

        titleEl.textContent = configEl.dataset.confirmTitle || '¿Seguro?';
        messageEl.textContent = configEl.dataset.confirmMessage || 'Esta acción no se puede deshacer.';
        acceptBtn.textContent = configEl.dataset.confirmAccept || 'Confirmar';

        dialog.showModal();
    });

    cancelBtn.addEventListener('click', () => dialog.close());

    acceptBtn.addEventListener('click', () => {
        if (!pendingForm) return;

        const form = pendingForm;
        const submitter = pendingSubmitter;
        const configEl = (submitter || form).closest('.js-confirm-delete');

        // Marcamos el elemento para que el listener de arriba no lo vuelva a
        // interceptar en este segundo submit (el de verdad).
        if (configEl) configEl.dataset.confirmed = '1';

        dialog.close();
        form.requestSubmit(submitter || undefined);

        if (configEl) delete configEl.dataset.confirmed;
    });

    dialog.addEventListener('close', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });
}

initConfirmDialogs();