if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
        });
    });
}

/**
 * Escapa texto para insertarlo con seguridad en HTML (incluido dentro de
 * atributos como src="...") al construir markup con template literals a
 * partir de datos que no vienen de nuestro propio backend ya escapado por
 * Blade -- las búsquedas en IGDB y CEX (ver games/show.blade.php y
 * games/_form.blade.php) insertan su respuesta directamente en innerHTML, así
 * que sin esto un título/URL con `<`, `"` u otros caracteres especiales
 * podría romper fuera del nodo/atributo esperado e inyectar HTML/JS.
 * Expuesta en window por el mismo motivo que showToast: la usan scripts
 * embebidos en las vistas, no módulos que puedan importarla.
 */
window.escapeHtml = function escapeHtml(value) {
    const ENTITIES = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'};

    return String(value ?? '').replace(/[&<>"']/g, (char) => ENTITIES[char]);
};

const SIDEBAR_STORAGE_KEY = 'sp:sidebarCollapsed';
const THEME_STORAGE_KEY = 'sp:theme';
const THEME_PENDING_KEY = 'sp:themePending';
const VALID_THEMES = ['dark', 'light'];

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
        } catch (e) {
        }
    });
}

initSidebarToggle();

/**
 * Persiste un ajuste de tema/vista en la cuenta (ver
 * PanelController::updateDisplay): fire-and-forget, la clase ya se ha
 * aplicado al <html> de forma síncrona antes de llamar a esto, así que un
 * fallo de red no bloquea ni deshace el cambio visible, solo no sobrevive a
 * la próxima carga de página.
 */
function saveDisplayPreference(payload) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    fetch('/panel/settings/display', {
        method: 'PATCH', credentials: 'same-origin', headers: {
            'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json',
        }, body: JSON.stringify(payload),
    }).catch(() => {
    });
}

/**
 * Alterna entre tema claro y oscuro. El cambio de color en sí lo hace
 * app.css (redefine las variables de Tailwind cuando <html> lleva la clase
 * 'light'); aquí solo se gestiona esa clase y su persistencia. El estado
 * inicial ya lo pinta el propio servidor en la clase de <html> (ver
 * layouts/app.blade.php), a partir del ajuste de cuenta, así que no hay
 * parpadeo al cargar ni falta que decidirlo aquí en JS.
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

            const theme = isLight ? 'light' : 'dark';

            document.documentElement.classList.toggle('light', isLight);
            syncIcon(isLight);

            try {
                localStorage.setItem(THEME_STORAGE_KEY, theme);
                if (!document.querySelector('meta[name="csrf-token"]')) {
                    sessionStorage.setItem(THEME_PENDING_KEY, theme);
                }
            } catch (e) {
            }

            if (document.querySelector('meta[name="csrf-token"]')) {
                saveDisplayPreference({theme});
            }
        });
    });
}

initThemeToggle();

/**
 * Toggle switches con guardado al vuelo (ver x-toggle): cada uno persiste su
 * propia columna en el endpoint de su data-url (PanelController::updateToggle
 * para ajustes de cuenta, UserController::updateRegistration para el registro
 * público), sin esperar a ningún botón "Guardar" aparte. Mismo fire-and-forget
 * que saveDisplayPreference: el cambio visual ya lo hace el propio checkbox
 * (:checked del CSS), un fallo de red solo hace que no sobreviva a la
 * próxima carga de página.
 */
function initSettingsToggles() {
    document.querySelectorAll('.js-setting-toggle').forEach((toggle) => {
        toggle.addEventListener('change', () => {
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            fetch(toggle.dataset.url, {
                method: 'PATCH', credentials: 'same-origin', headers: {
                    'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', 'Accept': 'application/json',
                }, body: JSON.stringify({field: toggle.dataset.field, value: toggle.checked}),
            }).catch(() => {
            });
        });
    });
}

initSettingsToggles();

function initThemeBoundaryForms() {
    document.querySelectorAll('.js-theme-boundary-form').forEach((form) => {
        form.addEventListener('submit', () => {
            try {
                const pendingTheme = sessionStorage.getItem(THEME_PENDING_KEY);
                const theme = localStorage.getItem(THEME_STORAGE_KEY);
                const input = form.querySelector('input[name="pending_theme"]');

                if (input && pendingTheme && pendingTheme === theme && VALID_THEMES.includes(theme)) {
                    input.value = theme;
                }
            } catch (e) {
            }
        });
    });
}

initThemeBoundaryForms();

/**
 * Desplegable del menú de usuario del header (avatar/email → perfil, panel
 * de control, salir). Se cierra al clicar fuera, con Escape, o al elegir
 * una opción (navegación normal de <a>/submit de formulario, no hace falta
 * gestionarlo aquí); el propio botón vuelve a abrir/cerrar si se clica de
 * nuevo estando ya abierto.
 */
function initUserMenu() {
    const trigger = document.querySelector('.js-user-menu-trigger');
    const menu = document.querySelector('.js-user-menu');
    if (!trigger || !menu) return;

    const isOpen = () => !menu.classList.contains('hidden');

    const setOpen = (open) => {
        menu.classList.toggle('hidden', !open);
        trigger.setAttribute('aria-expanded', String(open));
    };

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        setOpen(!isOpen());
    });

    document.addEventListener('click', (e) => {
        if (isOpen() && !menu.contains(e.target)) setOpen(false);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen()) setOpen(false);
    });
}

initUserMenu();

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
 * que refreshGamesResults() lo refresca por AJAX, y con delegación no hace
 * falta volver a enganchar nada tras cada refresco.
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
            visibleCheckboxes().forEach((cb) => {
                cb.checked = e.target.checked;
            });
            sync();
        }
    });

    // Otros módulos (modo selección, refresco tras buscar/editar en vivo)
    // necesitan poder recalcular la barra sin duplicar esta lógica.
    window.__syncBulkActions = sync;

    sync();
}

initBulkActions();

const GAMES_VIEW_CLASSES = {compact: 'games-compact-view', grid: 'games-grid-view'};

/**
 * Alterna la colección entre tres vistas: la habitual (tarjetas en móvil,
 * tabla en escritorio), una tabla compacta (menos alto por fila) y la
 * estantería (grid de carátulas grandes). Igual que el tema, el cambio de
 * vista en sí lo hace app.css según la clase en <html>; aquí solo se
 * gestiona esa clase y su persistencia (ver saveDisplayPreference), y el
 * estado inicial ya lo pinta el servidor en la clase de <html> (ver
 * layouts/app.blade.php).
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

    let currentMode = document.documentElement.classList.contains('games-grid-view') ? 'grid' : document.documentElement.classList.contains('games-compact-view') ? 'compact' : 'list';

    const setMode = (mode) => {
        currentMode = mode;
        document.documentElement.classList.remove(...Object.values(GAMES_VIEW_CLASSES));
        if (GAMES_VIEW_CLASSES[mode]) {
            document.documentElement.classList.add(GAMES_VIEW_CLASSES[mode]);
        }
        syncButtons(mode);
        saveDisplayPreference({games_view: mode});
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
            // contenido de #games-results puede haberse reemplazado (edición
            // rápida) desde entonces.
            results.querySelectorAll('.js-bulk-checkbox').forEach((cb) => {
                cb.checked = false;
            });
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
 * contenido, sin tener que reimplementar en JS el mismo HTML que ya genera
 * Blade (ver GameController::index(), rama $request->ajax()).
 */
async function refreshGamesResults(url) {
    const results = document.getElementById('games-results');
    if (!results) return;

    try {
        const response = await fetch(url, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin',
        });
        if (!response.ok) return;

        results.innerHTML = await response.text();

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

/**
 * Tarjetas de la colección en móvil (games/_results.blade.php): tocar
 * cualquier parte de la tarjeta abre la ficha de detalle, salvo los
 * controles interactivos que ya tienen su propio comportamiento (casilla
 * de selección, cambio rápido de estado/valoración, enlace del título).
 * Delegado sobre #games-results por el mismo motivo que el resto de
 * acciones de esta vista.
 */
function initGameCardTap() {
    const results = document.getElementById('games-results');
    if (!results) return;

    results.addEventListener('click', (e) => {
        const card = e.target.closest('.js-game-card');
        if (!card) return;
        if (e.target.closest('a, button, input, .js-quick-rating')) return;

        window.location.href = card.dataset.href;
    });
}

initGameCardTap();

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
                headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'},
                body: formData,
            });

            const data = await response.json();
            wrapper.classList.remove('hidden');

            if (!response.ok) {
                errorEl.textContent = data.error || 'No se ha podido leer el fichero.';
                errorEl.classList.remove('hidden');
                return;
            }

            columnsEl.textContent = data.matchedColumns.length ? `Columnas reconocidas: ${data.matchedColumns.join(', ')}.` : 'No se ha reconocido ninguna columna conocida.';

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

// Una importación normal (sin llamadas de red por fila, ver GameCsvImporter)
// no debería tardar más de un par de minutos ni con la colección real
// (1000+ juegos, ver README) — pasado este margen, probablemente algo ha
// ido mal (worker de cola caído, servidor sobrecargado...), aunque el
// sondeo sigue igual por si de verdad solo va lento (issue #119).
const SLOW_IMPORT_WARNING_MS = 10 * 60 * 1000;

/**
 * Sondea el resultado de una importación en curso (games/import.blade.php,
 * ver GameImportController::store()/importStatus()): el CSV ya no se
 * procesa dentro de la propia petición (ver Jobs\ImportGamesFromCsv), así
 * que el resumen se pinta aquí en cuanto el job termina, en vez de venir ya
 * listo en la respuesta del formulario.
 */
function initImportStatusPolling() {
    const statusEl = document.getElementById('import-status');
    if (!statusEl) return;

    const statusUrl = statusEl.dataset.statusUrl;
    const pendingEl = document.getElementById('import-status-pending');
    const slowWarningEl = document.getElementById('import-status-slow-warning');
    const resultEl = document.getElementById('import-status-result');
    const startedAt = Date.now();

    function renderResult(data) {
        pendingEl.classList.add('hidden');
        slowWarningEl.classList.add('hidden');
        resultEl.classList.remove('hidden');
        resultEl.innerHTML = '';

        const summary = document.createElement('div');
        summary.className = 'flex items-center gap-2 text-emerald-400 font-semibold';
        summary.innerHTML = '<span class="material-symbols-outlined align-middle text-[20px]">check_circle</span>';
        summary.append(` ${data.imported} ${data.imported === 1 ? 'juego importado' : 'juegos importados'}.`);
        resultEl.appendChild(summary);

        if (data.createdPlatforms || data.createdEditions) {
            const created = document.createElement('p');
            created.className = 'text-sm text-slate-400 mt-2';
            created.textContent = `Creadas sobre la marcha: ${data.createdPlatforms} ` + (data.createdPlatforms === 1 ? 'plataforma' : 'plataformas') + ` y ${data.createdEditions} ` + (data.createdEditions === 1 ? 'edición' : 'ediciones') + '.';
            resultEl.appendChild(created);
        }

        if (data.errors && data.errors.length) {
            const wrapper = document.createElement('div');
            wrapper.className = 'mt-4 pt-4 border-t border-slate-800';

            const title = document.createElement('p');
            title.className = 'text-sm font-medium text-amber-400 mb-2';
            title.textContent = `${data.errors.length} ${data.errors.length === 1 ? 'fila' : 'filas'} con incidencias:`;
            wrapper.appendChild(title);

            const list = document.createElement('ul');
            list.className = 'text-sm text-slate-400 space-y-1 list-disc list-inside max-h-48 overflow-y-auto';
            data.errors.forEach((message) => {
                const li = document.createElement('li');
                li.textContent = message;
                list.appendChild(li);
            });
            wrapper.appendChild(list);

            resultEl.appendChild(wrapper);
        }
    }

    async function poll() {
        try {
            const response = await fetch(statusUrl, {headers: {'Accept': 'application/json'}});

            if (!response.ok) {
                pendingEl.textContent = 'No se ha podido consultar el estado de la importación.';
                return;
            }

            const data = await response.json();

            if (!data.done) {
                if (Date.now() - startedAt > SLOW_IMPORT_WARNING_MS) {
                    slowWarningEl.classList.remove('hidden');
                }
                setTimeout(poll, 1500);
                return;
            }

            renderResult(data);
        } catch (e) {
            pendingEl.textContent = 'No se ha podido consultar el estado de la importación.';
        }
    }

    poll().then(r => r);
}

initImportStatusPolling();

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
            method: 'POST', credentials: 'same-origin', headers: {
                'X-CSRF-TOKEN': token, 'Accept': 'text/html',
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
}

window.showToast = function (message, type = 'success', undoUrl = null) {
    const container = createToastContainer();
    if (!container || !message) return;

    // Fondo sólido (no bg-*-500/10) a propósito: el toast flota sobre contenido
    // de la propia página (título, tarjetas...) y con fondo translúcido ese
    // contenido se transparentaba a través del aviso, volviéndolo ilegible.
    const palette = type === 'error' ? ['bg-slate-900', 'border-red-500/30', 'text-red-400'] : ['bg-slate-900', 'border-emerald-500/30', 'text-emerald-400'];

    const toast = document.createElement('div');
    toast.className = ['flex items-center gap-2 border rounded-lg px-4 py-2.5 text-sm shadow-lg shadow-black/30', 'opacity-0 -translate-y-1 transition-all duration-200 pointer-events-auto', ...palette,].join(' ');
    toast.setAttribute('role', 'status');

    const icon = document.createElement('span');
    icon.className = 'material-symbols-outlined text-[18px] shrink-0';
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
        undoBtn.className = 'shrink-0 font-semibold underline hover:no-underline';
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

const QUICK_SEARCH_DEBOUNCE_MS = 200;

/**
 * Filas de relleno con la misma silueta que una fila real de
 * _quick-search-results.blade.php (carátula + dos líneas de texto), para
 * mostrar mientras se espera la respuesta del servidor en vez de dejar los
 * resultados anteriores congelados y saltar de golpe a los nuevos.
 */
function quickSearchSkeleton() {
    const row = `
        <li class="flex items-center gap-3 px-4 py-2.5">
            <div class="w-10 h-10 rounded-lg bg-slate-800 shrink-0"></div>
            <div class="flex-1 min-w-0 space-y-2">
                <div class="h-3 w-3/5 rounded bg-slate-800"></div>
                <div class="h-2.5 w-2/5 rounded bg-slate-800"></div>
            </div>
        </li>`;

    return `<ul class="py-2 animate-pulse" aria-hidden="true">${row.repeat(4)}</ul>`;
}

/**
 * Búsqueda rápida global (Ctrl+K / Cmd+K, también "/" y el botón-buscador de
 * la colección): abre un <dialog> centrado con un campo de texto y filtros
 * opcionales de plataforma/estado que buscan en la colección según se
 * cambian. Es el único buscador de texto de la app. El servidor devuelve el
 * fragmento ya renderizado (mismo patrón que refreshGamesResults más arriba)
 * para reutilizar los componentes de carátula/chip/estrellas; los resultados
 * son enlaces normales a la ficha del juego, sin navegación por JS.
 */
function initQuickSearch() {
    const dialog = document.getElementById('quick-search-dialog');
    const input = document.getElementById('quick-search-input');
    const results = document.getElementById('quick-search-results');
    // Único buscador de texto de la app: el icono del header y el "buscador"
    // de la colección (games/_filters.blade.php, ahora un botón, no un
    // input) comparten esta misma clase para abrir el mismo diálogo.
    const triggers = document.querySelectorAll('.js-quick-search-trigger');
    const platformFilter = document.getElementById('quick-search-platform');
    const playStatusFilter = document.getElementById('quick-search-play-status');
    const statusFilter = document.getElementById('quick-search-status');
    const url = dialog?.dataset.url;

    if (!dialog || !input || !results || !url) return;

    let debounceTimer = null;

    const runSearch = async () => {
        const params = new URLSearchParams({q: input.value.trim()});
        if (platformFilter?.value) params.set('platform_id', platformFilter.value);
        if (playStatusFilter?.value) params.set('play_status', playStatusFilter.value);
        if (statusFilter?.value) params.set('status', statusFilter.value);

        results.innerHTML = quickSearchSkeleton();

        try {
            const response = await fetch(`${url}?${params.toString()}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}, credentials: 'same-origin',
            });
            if (!response.ok) return;

            results.innerHTML = await response.text();
        } catch (e) {
            // Sin conexión o similar: se deja el resultado anterior en pantalla.
        }
    };

    // El trigger pulsado puede traer una búsqueda ya escrita (el "buscador"
    // de la colección precarga la suya vía data-prefill-query); el resto
    // (icono del header, atajos de teclado) siempre abre en blanco. Los
    // filtros se reinician en cada apertura para no arrastrar una selección
    // de una búsqueda anterior sin que se note.
    const open = (trigger) => {
        input.value = trigger?.dataset.prefillQuery || '';
        if (platformFilter) platformFilter.value = '';
        if (playStatusFilter) playStatusFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        dialog.showModal();
        input.focus();
        input.select();
        runSearch();
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => open(trigger));
    });

    // Atajo global: funciona aunque el foco esté en otro campo de texto, como
    // en cualquier paleta de comandos. preventDefault() también evita que el
    // navegador enfoque la barra de direcciones con este mismo atajo.
    document.addEventListener('keydown', (e) => {
        if (!(e.metaKey || e.ctrlKey) || e.key.toLowerCase() !== 'k') return;

        e.preventDefault();
        dialog.open ? dialog.close() : open();
    });

    // Atajo "/", como en GitHub o Gmail: solo cuando no se está ya
    // escribiendo en otro campo, a diferencia de Ctrl+K de arriba.
    document.addEventListener('keydown', (e) => {
        if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;

        const active = document.activeElement;
        const isTyping = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.tagName === 'SELECT' || active.isContentEditable);
        if (isTyping) return;

        e.preventDefault();
        open();
    });

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runSearch, QUICK_SEARCH_DEBOUNCE_MS);
    });

    [platformFilter, playStatusFilter, statusFilter].forEach((select) => {
        select?.addEventListener('change', runSearch);
    });

    // Enter navega directamente al primer resultado, sin tener que soltar el
    // teclado para hacer click.
    input.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        results.querySelector('a')?.click();
    });

    // Clic en el fondo (backdrop) cierra el diálogo: el <dialog> no tiene
    // padding propio (todo el contenido vive en hijos que lo cubren entero),
    // así que un click cuyo target sea el propio <dialog> solo puede venir
    // del backdrop.
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) dialog.close();
    });
}

initQuickSearch();

/**
 * Ficha de comprobación de una sugerencia externa (CEX) dentro de la
 * búsqueda rápida (ver _quick-search-results.blade.php): los datos ya viven
 * en el data-* del botón pulsado, así que mostrarla es solo alternar qué
 * bloque se ve, sin otra petición al servidor. Delegado sobre
 * #quick-search-results (nunca se destruye, solo se le reemplaza el
 * innerHTML en cada búsqueda) por el mismo motivo que el resto de listeners
 * delegados de la app.
 */
function initExternalResultPreview() {
    const results = document.getElementById('quick-search-results');
    if (!results) return;

    results.addEventListener('click', (e) => {
        const resultBtn = e.target.closest('.js-cex-result');
        if (resultBtn) {
            const list = document.getElementById('cex-results-list');
            const preview = document.getElementById('cex-preview');
            if (!preview) return;

            const {title, ean, cover, platform} = resultBtn.dataset;

            preview.querySelector('#cex-preview-title').textContent = title || '';
            preview.querySelector('#cex-preview-ean').textContent = ean ? `EAN ${ean}` : '';

            const platformBadge = preview.querySelector('#cex-preview-platform');
            platformBadge.textContent = platform || '';
            platformBadge.classList.toggle('hidden', !platform);

            const img = preview.querySelector('#cex-preview-cover');
            if (cover) {
                img.src = cover;
                img.classList.remove('hidden');
            } else {
                img.classList.add('hidden');
            }

            const params = new URLSearchParams();
            if (title) params.set('title', title);
            if (ean) params.set('ean', ean);
            if (cover) params.set('cover_url', cover);
            preview.querySelector('#cex-preview-add-link').href = `${preview.dataset.createUrl}?${params.toString()}`;

            list?.classList.add('hidden');
            preview.classList.remove('hidden');
            return;
        }

        if (e.target.closest('.js-cex-preview-back')) {
            document.getElementById('cex-preview')?.classList.add('hidden');
            document.getElementById('cex-results-list')?.classList.remove('hidden');
        }
    });
}

initExternalResultPreview();

/**
 * Escaneo de código de barras (EAN) con la cámara, para no tener que
 * teclearlo en la búsqueda rápida. Usa @zxing/library, cargada solo al
 * pulsar el botón (import() dinámico) para no meter esa dependencia en el
 * bundle inicial de todo el mundo, la mayoría de veces sin usarla.
 */
function initBarcodeScanner() {
    const trigger = document.getElementById('barcode-scan-trigger');
    const dialog = document.getElementById('barcode-scan-dialog');
    const video = document.getElementById('barcode-scan-video');
    const cancelBtn = document.getElementById('barcode-scan-cancel');
    const errorEl = document.getElementById('barcode-scan-error');
    const searchInput = document.getElementById('quick-search-input');

    if (!trigger || !dialog || !video || !searchInput) return;

    let reader = null;

    const showError = (message) => {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    };

    const stop = () => {
        reader?.reset();
        reader = null;
    };

    const close = () => {
        stop();
        if (dialog.open) dialog.close();
    };

    trigger.addEventListener('click', async () => {
        errorEl.classList.add('hidden');
        dialog.showModal();

        try {
            const {BrowserMultiFormatReader} = await import('@zxing/library');
            reader = new BrowserMultiFormatReader();

            await reader.decodeFromVideoDevice(undefined, video, (result) => {
                if (!result) return; // Sin detección en este frame: no es un error, sigue intentando.

                const code = result.getText();
                close();
                searchInput.value = code;
                searchInput.dispatchEvent(new Event('input'));
            });
        } catch (e) {
            showError('No se ha podido acceder a la cámara. Comprueba los permisos del navegador.');
        }
    });

    cancelBtn.addEventListener('click', close);
    dialog.addEventListener('close', stop);

    // Mismo truco que en quick-search-dialog: sin padding propio, un click
    // cuyo target sea el <dialog> en sí solo puede venir del backdrop.
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog) close();
    });
}

initBarcodeScanner();
