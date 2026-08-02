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
 *
 * Usa delegación de eventos sobre #games-results (no listeners atados a
 * cada casilla) a propósito: ese contenedor se reemplaza entero cada vez
 * que el buscador en vivo trae resultados nuevos (ver initGamesLiveSearch),
 * y con delegación no hace falta volver a enganchar nada tras cada refresco.
 */
function initBulkActions() {
    const results = document.getElementById('games-results');
    if (!results) return;

    const bar = document.getElementById('bulk-bar');
    const countEl = document.getElementById('bulk-count');

    // La colección se pinta dos veces (tarjetas en móvil, tabla en
    // escritorio) y solo una de las dos copias está visible según el ancho
    // de pantalla; sin filtrar por visibilidad, "seleccionar todo" y el
    // contador duplicarían cada juego (una vez por copia).
    const allCheckboxes = () => Array.from(results.querySelectorAll('.js-bulk-checkbox'));
    const visibleCheckboxes = () => allCheckboxes().filter((cb) => cb.offsetParent !== null);

    const sync = () => {
        const visible = visibleCheckboxes();
        const count = visible.filter((cb) => cb.checked).length;

        bar?.classList.toggle('hidden', count === 0);
        if (countEl) {
            countEl.textContent = `${count} ${count === 1 ? 'juego seleccionado' : 'juegos seleccionados'}`;
        }

        const selectAll = results.querySelector('#bulk-select-all');
        if (selectAll) {
            selectAll.checked = count > 0 && count === visible.length;
            selectAll.indeterminate = count > 0 && count < visible.length;
        }
    };

    results.addEventListener('change', (e) => {
        if (e.target.matches('.js-bulk-checkbox')) {
            sync();
        } else if (e.target.id === 'bulk-select-all') {
            visibleCheckboxes().forEach((cb) => { cb.checked = e.target.checked; });
            sync();
        }
    });

    // Otros módulos (modo selección, refresco tras buscar/editar en vivo)
    // necesitan poder recalcular la barra sin duplicar esta lógica.
    window.__syncBulkActions = sync;

    sync();
}

initBulkActions();

const GAMES_VIEW_STORAGE_KEY = 'sp:gamesView';
const GAMES_VIEW_CLASSES = { compact: 'games-compact-view', grid: 'games-grid-view' };

/**
 * Alterna la colección entre tres vistas: la habitual (tarjetas en móvil,
 * tabla en escritorio), una tabla compacta (menos alto por fila) y la
 * estantería (grid de carátulas grandes). Igual que el tema, el cambio de
 * vista en sí lo hace app.css según la clase en <html>; aquí solo se
 * gestiona esa clase y su persistencia, y el estado inicial ya lo aplica el
 * script bloqueante del <head> antes del primer pintado.
 */
function initGamesViewToggle() {
    const buttons = {
        list: document.getElementById('games-view-list-btn'),
        compact: document.getElementById('games-view-compact-btn'),
        grid: document.getElementById('games-view-grid-btn'),
    };
    if (!buttons.list && !buttons.compact && !buttons.grid) return;

    const syncButtons = (mode) => {
        Object.entries(buttons).forEach(([key, btn]) => {
            if (!btn) return;
            const active = key === mode;
            btn.classList.toggle('text-indigo-400', active);
            btn.classList.toggle('text-slate-500', !active);
            btn.setAttribute('aria-pressed', String(active));
        });
    };

    let currentMode = document.documentElement.classList.contains('games-grid-view')
        ? 'grid'
        : document.documentElement.classList.contains('games-compact-view')
            ? 'compact'
            : 'list';

    const setMode = (mode) => {
        currentMode = mode;
        document.documentElement.classList.remove(...Object.values(GAMES_VIEW_CLASSES));
        if (GAMES_VIEW_CLASSES[mode]) {
            document.documentElement.classList.add(GAMES_VIEW_CLASSES[mode]);
        }
        syncButtons(mode);

        try {
            localStorage.setItem(GAMES_VIEW_STORAGE_KEY, mode);
        } catch (e) {}
    };

    syncButtons(currentMode);

    buttons.list?.addEventListener('click', () => setMode('list'));
    buttons.compact?.addEventListener('click', () => setMode('compact'));
    // En escritorio hay botones separados para volver a lista/compacta, pero en
    // móvil el de estantería es el único visible (ver comentario más arriba),
    // así que tiene que servir también para volver: si ya está activo, alterna
    // de vuelta a la vista normal en vez de quedarse fijo en estantería.
    buttons.grid?.addEventListener('click', () => setMode(currentMode === 'grid' ? 'list' : 'grid'));
}

initGamesViewToggle();

const SELECTION_MODE_CLASS = 'selection-mode';

/**
 * "Modo selección" de la colección: las casillas de acciones en bloque
 * están ocultas (ver app.css) hasta que se activa este botón, para no
 * ocupar espacio permanentemente en cada fila/tarjeta. No se persiste
 * (siempre arranca desactivado al cargar la página, a diferencia del tema o
 * la vista): es un modo transitorio de la sesión de uso, no una preferencia.
 */
function initSelectionMode() {
    const toggle = document.getElementById('selection-mode-toggle');
    const results = document.getElementById('games-results');
    if (!toggle || !results) return;

    const setMode = (on) => {
        document.documentElement.classList.toggle(SELECTION_MODE_CLASS, on);
        toggle.classList.toggle('bg-indigo-600', on);
        toggle.classList.toggle('border-indigo-600', on);
        toggle.classList.toggle('text-white', on);
        toggle.classList.toggle('border-slate-700', !on);
        toggle.classList.toggle('text-slate-400', !on);
        toggle.setAttribute('aria-pressed', String(on));
        toggle.setAttribute('aria-label', on ? 'Salir del modo selección' : 'Seleccionar varios juegos');

        if (!on) {
            // Al salir del modo selección se limpia lo marcado, para no
            // arrastrar una selección "invisible" si se vuelve a entrar.
            // Se busca en vivo (no una lista capturada al iniciar) porque el
            // contenido de #games-results puede haberse reemplazado por el
            // buscador en vivo desde entonces.
            results.querySelectorAll('.js-bulk-checkbox').forEach((cb) => { cb.checked = false; });
            window.__syncBulkActions?.();
        }
    };

    toggle.addEventListener('click', () => {
        setMode(!document.documentElement.classList.contains(SELECTION_MODE_CLASS));
    });
}

initSelectionMode();

/**
 * Panel "Avanzado" del buscador de la colección (games/_filters.blade.php):
 * plataforma/estado/orden/paginación quedan plegados por defecto, y solo se
 * despliegan al pulsar el botón, en vez de ocupar sitio siempre. Cada copia
 * del formulario (móvil/escritorio) tiene su propio botón y panel.
 */
function initAdvancedFiltersToggle() {
    document.querySelectorAll('.js-advanced-toggle').forEach((toggle) => {
        const panel = toggle.closest('form')?.querySelector('.js-advanced-panel');
        if (!panel) return;

        toggle.addEventListener('click', () => {
            panel.classList.toggle('hidden');
        });
    });
}

initAdvancedFiltersToggle();

/**
 * Refresca #games-results por AJAX con la URL dada (filtros/orden/página
 * incluidos en la query string) y vuelve a sincronizar lo que dependa de su
 * contenido. Lo usan tanto el buscador en vivo como la edición rápida
 * (tras guardar, para que la fila/tarjeta muestre el valor ya actualizado
 * sin tener que reimplementar en JS el mismo HTML que ya genera Blade).
 */
async function refreshGamesResults(url) {
    const results = document.getElementById('games-results');
    if (!results) return;

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;

        const html = await response.text();
        results.innerHTML = html;

        window.__syncBulkActions?.();

        const meta = document.getElementById('games-results-meta');
        const total = meta ? parseInt(meta.dataset.total, 10) : null;
        if (total !== null && !Number.isNaN(total)) {
            document.querySelectorAll('.js-games-total').forEach((el) => {
                el.textContent = `${total} ${total === 1 ? 'juego registrado' : 'juegos registrados'}.`;
            });
        }
    } catch (e) {
        // Sin conexión o similar: se deja el listado como estaba: la próxima
        // acción (o recargar la página) ya lo pondrá al día.
    }
}

const GAMES_LIVE_SEARCH_DEBOUNCE_MS = 300;

/**
 * Buscador simple de la colección: filtra según se escribe (sin recargar la
 * página), conservando cualquier filtro "Avanzado" ya aplicado (van en el
 * mismo <form>, así que el FormData los incluye aunque el panel esté
 * plegado). El campo está duplicado (móvil/escritorio, ver
 * initGameSearchShortcut) y ambas copias se mantienen sincronizadas tras
 * cada búsqueda.
 */
function initGamesLiveSearch() {
    const inputs = Array.from(document.querySelectorAll('.js-live-search'));
    if (!inputs.length || !document.getElementById('games-results')) return;

    let debounceTimer = null;

    const runSearch = (sourceInput) => {
        const form = sourceInput.closest('form');
        if (!form) return;

        const params = new URLSearchParams(new FormData(form));
        const url = `${form.getAttribute('action')}?${params.toString()}`;

        refreshGamesResults(url).then(() => {
            history.replaceState(null, '', url);
        });

        // La copia del buscador que no se está usando (móvil o escritorio,
        // según desde dónde se escriba) se mantiene con el mismo texto.
        inputs.forEach((input) => {
            if (input !== sourceInput) input.value = sourceInput.value;
        });
    };

    inputs.forEach((input) => {
        input.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => runSearch(input), GAMES_LIVE_SEARCH_DEBOUNCE_MS);
        });
    });
}

initGamesLiveSearch();

/**
 * Edición rápida de valoración/estado de juego desde la propia fila/tarjeta
 * (tabla, tarjetas o estantería), sin abrir el formulario completo.
 * Delegado sobre #games-results por el mismo motivo que las acciones en
 * bloque: sobrevive a los refrescos del buscador en vivo sin tener que
 * volver a engancharse.
 */
function initQuickEdit() {
    const results = document.getElementById('games-results');
    if (!results) return;

    const NEXT_PLAY_STATUS = { pending: 'playing', playing: 'finished', finished: 'pending' };

    const quickUpdate = async (gameId, payload) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        try {
            const response = await fetch(`/games/${gameId}/quick-update`, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (response.ok) {
                // Se recarga el resultado por AJAX en vez de tocar el DOM a mano:
                // así la fila/tarjeta queda exactamente como la pintaría Blade,
                // sin arriesgarse a que el HTML generado en JS se desincronice
                // del real con el tiempo.
                refreshGamesResults(window.location.href);
            }
        } catch (e) {
            // Sin conexión o similar: el valor en pantalla no cambia, se puede reintentar.
        }
    };

    results.addEventListener('click', (e) => {
        const statusBtn = e.target.closest('.js-quick-play-status');
        if (statusBtn) {
            const next = NEXT_PLAY_STATUS[statusBtn.dataset.playStatus] || 'pending';
            quickUpdate(statusBtn.dataset.gameId, { play_status: next });
            return;
        }

        const star = e.target.closest('.material-symbols-outlined');
        const ratingWrapper = e.target.closest('.js-quick-rating');
        if (star && ratingWrapper) {
            const starsRoot = ratingWrapper.querySelector(':scope > div');
            const index = starsRoot ? Array.from(starsRoot.children).indexOf(star) + 1 : 0;
            if (index >= 1) {
                quickUpdate(ratingWrapper.dataset.gameId, { rating: index });
            }
        }
    });
}

initQuickEdit();

/**
 * Vista previa del CSV de importación (games/import.blade.php): al elegir un
 * fichero se manda a /games/import/preview (no importa nada, solo lo lee) y
 * se muestran las columnas reconocidas y las primeras filas, para poder
 * corregir el CSV antes de subirlo de verdad.
 */
function initImportPreview() {
    const fileInput = document.getElementById('file');
    const wrapper = document.getElementById('import-preview');
    if (!fileInput || !wrapper) return;

    const errorEl = document.getElementById('import-preview-error');
    const contentEl = document.getElementById('import-preview-content');
    const columnsEl = document.getElementById('import-preview-columns');
    const rowsEl = document.getElementById('import-preview-rows');
    const previewUrl = fileInput.dataset.previewUrl;

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];

        wrapper.classList.add('hidden');
        errorEl.classList.add('hidden');
        contentEl.classList.add('hidden');
        if (!file || !previewUrl) return;

        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: formData,
            });

            const data = await response.json();
            wrapper.classList.remove('hidden');

            if (!response.ok) {
                errorEl.textContent = data.error || 'No se ha podido leer el fichero.';
                errorEl.classList.remove('hidden');
                return;
            }

            columnsEl.textContent = data.matchedColumns.length
                ? `Columnas reconocidas: ${data.matchedColumns.join(', ')}.`
                : 'No se ha reconocido ninguna columna conocida.';

            rowsEl.innerHTML = '';
            data.rows.forEach((row) => {
                const tr = document.createElement('tr');
                ['titulo', 'plataforma', 'ean', 'precio pagado'].forEach((key, i) => {
                    const td = document.createElement('td');
                    td.className = i === 3 ? 'px-3 py-2 text-right whitespace-nowrap' : 'px-3 py-2 whitespace-nowrap';
                    td.textContent = row[key] || '—';
                    tr.appendChild(td);
                });
                rowsEl.appendChild(tr);
            });

            contentEl.classList.remove('hidden');
        } catch (e) {
            wrapper.classList.remove('hidden');
            errorEl.textContent = 'No se ha podido generar la vista previa.';
            errorEl.classList.remove('hidden');
        }
    });
}

initImportPreview();

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

/**
 * Deshacer un envío a la papelera desde el propio toast (sin navegar a
 * /games/trash): llama al POST de restaurar por fetch y, si sale bien,
 * recarga la página para que el juego reaparezca en el listado y se vea el
 * toast de confirmación que ya manda el propio restore().
 */
async function undoAction(url, button) {
    button.disabled = true;

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'text/html',
            },
        });

        if (response.ok) {
            window.location.reload();
            return;
        }
    } catch (e) {
        // sigue abajo para reactivar el botón
    }

    button.disabled = false;
};

window.showToast = function (message, type = 'success', undoUrl = null) {
    const container = createToastContainer();
    if (!container || !message) return;

    const palette = type === 'error'
        ? ['bg-red-500/10', 'border-red-500/20', 'text-red-400']
        : ['bg-emerald-500/10', 'border-emerald-500/20', 'text-emerald-400'];

    const toast = document.createElement('div');
    toast.className = [
        'flex items-center gap-2 border rounded-lg px-4 py-2.5 text-sm shadow-lg shadow-black/30',
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

    if (undoUrl) {
        const undoBtn = document.createElement('button');
        undoBtn.type = 'button';
        undoBtn.className = 'flex-shrink-0 font-semibold underline hover:no-underline';
        undoBtn.textContent = 'Deshacer';
        undoBtn.addEventListener('click', () => undoAction(undoUrl, undoBtn));
        toast.append(undoBtn);
    }

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