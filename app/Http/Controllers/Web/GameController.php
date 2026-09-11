<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\MatchGameWithIgdb;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Services\GameLookup\ExternalCoverDownloader;
use App\Services\Games\GameCollectionQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GameController extends Controller
{
    public function __construct(
        private readonly GameCollectionQuery $collectionQuery,
        private readonly ExternalCoverDownloader $coverDownloader,
    ) {}

    /**
     * Columnas por las que se puede ordenar el listado desde ?sort=, mapeadas
     * a la columna real (evita pasar un nombre de columna arbitrario del
     * usuario directamente a orderBy()).
     */
    public const SORTABLE_COLUMNS = [
        'title' => 'title',
        'price_paid' => 'price_paid',
        'rating' => 'rating',
        'purchase_date' => 'purchase_date',
    ];

    /**
     * Tamaños de página permitidos desde ?per_page= en el listado web (a
     * diferencia de la API, aquí se restringe a un puñado de valores fijos
     * pensados para un selector, no un número libre).
     *
     * Ambas constantes son public: PanelController las reutiliza para
     * validar los ajustes de orden/paginación por defecto sin duplicar la
     * lista (ver PanelController::updateSettings()).
     */
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    /**
     * Presets de región del formulario de alta/edición (games/_form.blade.php)
     * y del ajuste "Región por defecto" en Ajustes (PanelController): una
     * sola lista para las dos, evita que se desincronicen.
     */
    public const REGION_PRESETS = ['PAL-ES', 'PAL-EU', 'PAL-UK', 'PAL-FR', 'PAL-DE', 'PAL-IT', 'NTSC-U', 'NTSC-J'];

    /**
     * Filtros del listado que se recuerdan en sesión (issue #182 seguimiento,
     * 2026-09-10: "que se quede el filtro establecido hasta que lo quite, por
     * mucho que cambie de pantalla") — no incluye sort/dir/per_page, que ya
     * tienen su propio mecanismo de valor por defecto (default_sort/
     * default_per_page del usuario, ver PanelController).
     */
    private const REMEMBERED_FILTER_KEYS = ['q', 'platform_id', 'play_status', 'for_sale', 'rating', 'cover'];

    private const FILTERS_SESSION_KEY = 'games.filters';

    // Colección del usuario, con búsqueda por título/EAN, filtros por plataforma/estado
    // (?q=, ?platform_id=, ?play_status=, ?status=), orden (?sort=, ?dir=) y
    // tamaño de página (?per_page=)
    public function index(Request $request): View|RedirectResponse
    {
        // "Limpiar" (ver games/_filters.blade.php) manda ?clear=1 en vez de
        // navegar a una URL sin parámetros a secas: sin esta señal explícita,
        // sería indistinguible de llegar aquí desde cualquier otro sitio sin
        // haber tocado los filtros, y el bloque de abajo restauraría los
        // filtros recordados en vez de dejarlos limpios de verdad.
        if ($request->boolean('clear')) {
            session()->forget(self::FILTERS_SESSION_KEY);

            return redirect()->route('web.games.index');
        }

        // Ninguno de los filtros recordados viene en esta petición (llegada
        // "en limpio": menú, recargar la pestaña, volver de otra sección...):
        // si había unos guardados de una visita anterior, se restauran
        // redirigiendo a la misma URL con ellos ya puestos, para que tanto la
        // consulta como el propio formulario de filtros los reflejen igual
        // que si el usuario los hubiera vuelto a escribir. Sin esto último
        // (fuera del ajax, que nunca es una "llegada" nueva de por sí) cada
        // fetch de la búsqueda en vivo redirigiría también.
        if (! $request->ajax() && collect(self::REMEMBERED_FILTER_KEYS)->every(fn ($key) => ! $request->has($key))) {
            $remembered = session(self::FILTERS_SESSION_KEY, []);

            if ($remembered !== []) {
                return redirect()->route('web.games.index', $remembered);
            }
        } else {
            session([self::FILTERS_SESSION_KEY => $request->only(self::REMEMBERED_FILTER_KEYS)]);
        }

        // ConvertEmptyStringsToNull (middleware por defecto) transforma los campos
        // vacíos del formulario en null, así que hay que castear a string antes de
        // comparar con '' o whereNull() saldría disparado sin querer.
        $query = trim((string) $request->input('q', ''));
        $platformId = (string) $request->input('platform_id', '');
        $playStatus = (string) $request->input('play_status', '');
        $forSale = (string) $request->input('for_sale', '');
        $rating = (string) $request->input('rating', '');
        $cover = (string) $request->input('cover', '');
        [$sort, $dir] = $this->collectionQuery->resolveSort($request);
        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE_OPTIONS, true)
            ? (int) $request->input('per_page')
            : (int) (auth()->user()->default_per_page ?: 20);

        // Calculados aquí (no en la vista) porque los necesitan tanto la página
        // completa como el fragmento que se devuelve por AJAX al buscar en vivo.
        $hasActiveFilters = $query !== '' || $platformId !== '' || $playStatus !== '' || $forSale !== '' || $rating !== '' || $cover !== '';
        $hasAdvancedFilters = $platformId !== '' || $playStatus !== '' || $forSale !== '' || $rating !== '' || $cover !== '';
        $activeFilterCount = collect([$query !== '', $platformId !== '', $playStatus !== '', $forSale !== '', $rating !== '', $cover !== ''])->filter()->count();

        $games = $this->collectionQuery->query($request)
            // Solo las columnas que pinta el listado: notes/data/genres/etc. serían
            // peso muerto en una tabla paginada y no se usan aquí.
            ->select([
                'id', 'title', 'cover', 'platform_id', 'edition_id',
                'play_status', 'status', 'for_sale', 'rating', 'price_paid', 'purchase_date',
                'region', 'manual_status', 'created_at',
            ])
            // Relaciones acotadas a las columnas que realmente pinta el chip de
            // plataforma y el nombre de la edición, para no arrastrar el resto.
            ->with([
                'platform:id,name,label,bg_color,text_color,border_color,manufacturer_id',
                'platform.manufacturer:id,bg_color,text_color,border_color',
                'edition:id,name,format',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $platforms = Platform::where('user_id', auth()->id())->orderBy('name')->get();

        // El buscador simple filtra en vivo (ver initGamesLiveSearch en app.js):
        // en vez de la página completa, solo hace falta el fragmento con el
        // listado/paginación, sin recalcular los totales de toda la colección.
        if ($request->ajax()) {
            return view('games._results', compact('games', 'hasActiveFilters'));
        }

        // Totales de TODA la colección (no del resultado filtrado/paginado actual),
        // para la barra de estado discreta al pie del listado. Igual que arriba,
        // la wishlist no cuenta: todavía no es parte de la colección.
        $collectionTotals = [
            'count' => Game::where('user_id', auth()->id())->where('status', '!=', 'wishlist')->count(),
            'spent' => (float) Game::where('user_id', auth()->id())->where('status', '!=', 'wishlist')->sum('price_paid'),
        ];

        return view('games.index', compact(
            'games', 'query', 'platforms', 'platformId', 'playStatus', 'forSale', 'rating', 'cover', 'sort', 'dir', 'perPage',
            'collectionTotals', 'hasActiveFilters', 'hasAdvancedFilters', 'activeFilterCount',
        ));
    }

    /**
     * Muestra el formulario de alta. Acepta ?ean= y/o ?title= para
     * prellenar esos campos: los usa la búsqueda rápida (Ctrl+K) cuando un
     * código escaneado o buscado no coincide con ningún juego ya registrado,
     * para no tener que volver a teclearlo aquí.
     */
    public function create(Request $request): View
    {
        $platforms = Platform::where('user_id', auth()->id())->orderBy('name')->get();
        $editions = Edition::where('user_id', auth()->id())->with('platforms')->orderBy('name')->get();
        $availableGenres = $this->availableGenres();

        $prefill = [
            'ean' => $request->query('ean'),
            'title' => $request->query('title'),
            // Llega desde la ficha de comprobación de una sugerencia externa
            // (CEX) en la búsqueda rápida: la carátula no se descarga hasta
            // que se guarda el formulario (ver store()), aquí solo se
            // previsualiza.
            'cover_url' => $request->query('cover_url'),
            // El resto llega desde "Guardar y añadir otro" (issue #181, ver
            // store()): campos que suelen repetirse dentro de un mismo lote
            // (varios juegos de la misma plataforma/edición/región comprados
            // juntos), arrastrados por query string en vez de sesión para no
            // interferir con el flujo normal de alta si se navega a este
            // formulario por cualquier otra vía.
            'platform_id' => $request->query('platform_id'),
            'edition_id' => $request->query('edition_id'),
            'region_select' => $request->query('region_select'),
            'purchase_place' => $request->query('purchase_place'),
            'purchase_date' => $request->query('purchase_date'),
            'rating' => $request->query('rating'),
        ];

        return view('games.create', compact('platforms', 'editions', 'prefill', 'availableGenres'));
    }

    /**
     * Géneros ya escritos por el usuario en el resto de su colección, para
     * sugerirlos como autocompletado en el campo de texto libre 'genres' del
     * formulario (ver games/_form.blade.php) sin necesidad de un catálogo
     * propio ni de tocar el modelo de datos (issue #24).
     *
     * @return array<int, string>
     */
    private function availableGenres(): array
    {
        return Game::where('user_id', auth()->id())
            ->whereNotNull('genres')
            ->pluck('genres')
            ->flatten()
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    // Guarda el juego en la base de datos
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($errors = $this->duplicateWarnings($request, $validated, null)) {
            return back()->withInput()->withErrors($errors);
        }

        if ($request->hasFile('cover')) {
            $validated['cover'] = $request->file('cover')->store('covers', 'public');
        } elseif (! $request->boolean('remove_cover') && $request->filled('cover_url')) {
            // Carátula sugerida desde la ficha de comprobación de una
            // búsqueda externa (CEX): se descarga aquí, no antes, para no
            // dejar ficheros huérfanos si el usuario nunca llega a guardar.
            $validated['cover'] = $this->coverDownloader->download((string) $request->input('cover_url'));
        } else {
            $validated['cover'] = null;
        }

        $validated += $this->readCoverDimensions($validated['cover']);

        $validated['user_id'] = auth()->id();

        $game = Game::create($validated);

        if (auth()->user()->auto_igdb_background) {
            // En cola, no síncrono: la búsqueda + timeToBeat + artworks de
            // IGDB (hasta 4s de timeout cada una) podían dejar hasta ~16s
            // de latencia visible al guardar (auditoría de rendimiento del
            // 2026-09-10, issue #180) — el fondo aparece unos segundos
            // después en vez de retrasar el propio guardado.
            MatchGameWithIgdb::dispatch($game->id, assignBackground: true);
        }

        // "Guardar y añadir otro" (issue #181): catalogar un lote de golpe
        // son, si no, tantos ciclos completos de listado → alta → guardar →
        // listado como juegos tenga el lote. Solo se arrastran los campos
        // que de verdad suelen repetirse dentro de un mismo lote (plataforma,
        // edición, región, lugar/fecha de compra, conservación) — título,
        // EAN, carátula y género quedan siempre en blanco.
        if ($request->boolean('add_another')) {
            $carry = array_filter([
                'platform_id' => $validated['platform_id'] ?? null,
                'edition_id' => $validated['edition_id'] ?? null,
                'region_select' => $validated['region'] ?? null,
                'purchase_place' => $validated['purchase_place'] ?? null,
                'purchase_date' => $validated['purchase_date'] ?? null,
                'rating' => $validated['rating'] ?? null,
            ], fn ($value) => $value !== null);

            return redirect()->route('web.games.create', $carry)
                ->with('success', "«{$game->title}» añadido. Continúa con el siguiente.");
        }

        return redirect()->route('web.games.index')->with('success', 'Juego añadido correctamente.');
    }

    /**
     * Ficha de solo lectura de un juego: toda la información del modelo sin
     * abrir el formulario de edición, para "solo mirar" un juego concreto.
     * De paso, si todavía no se ha intentado enlazar con IGDB, lo dispara
     * (ver MatchGameWithIgdb): es la única vía de entrada al enriquecimiento
     * automático, no hace falta ninguna acción del usuario.
     */
    public function show(Game $game): View
    {
        Gate::authorize('view', $game);

        $game->load(['platform.manufacturer', 'edition']);

        // Antes se emparejaba con IGDB aquí mismo, en línea: una llamada
        // HTTP externa síncrona bloqueaba la primera carga de cada ficha. Se
        // despacha al worker de cola (MatchGameWithIgdb) en vez de esperarla
        // — la ficha se sirve ya, y el match se ve la siguiente vez que se
        // visite (o al recargar). Se comprueba aquí, no dentro del job, para
        // no encolar un job de sobra en cada visita a un juego ya emparejado.
        if ($game->igdb_matched_at === null) {
            MatchGameWithIgdb::dispatch($game->id);
        }

        return view('games.show', compact('game'));
    }

    /**
     * Edición rápida de estado de juego y/o "en venta" sin pasar por el
     * formulario completo. Se valida y actualiza solo el campo que llega.
     *
     * La conservación (rating) ya no se acepta aquí: se editaba al vuelo
     * tocando una estrella en el listado (tarjetas/estantería), pero eso se
     * quitó (ver CHANGELOG) para no arriesgar cambios sin querer al hacer
     * scroll — ahora solo se cambia desde la ficha de edición completa.
     */
    public function quickUpdate(Request $request, Game $game): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $game);

        $validated = $request->validate([
            'play_status' => ['sometimes', 'required', 'string', Rule::in(Game::PLAY_STATUSES)],
            'for_sale' => 'sometimes|boolean',
        ]);

        if ($validated === []) {
            return response()->json(['message' => 'Nada que actualizar.'], 422);
        }

        $game->update($validated);

        // El listado usa fetch con Accept: application/json (ver quickUpdate en
        // resources/js/app.js) para poder actualizar la fila/tarjeta sin
        // navegar; el botón "En venta" de la ficha de detalle, en cambio, es
        // un <form> normal sin JS, así que aquí conviene un redirect en vez
        // de JSON.
        if (! $request->wantsJson()) {
            return back()->with('success', $game->for_sale ? 'Marcado como en venta.' : 'Quitado de en venta.');
        }

        return response()->json([
            'play_status' => $game->play_status,
            'for_sale' => $game->for_sale,
        ]);
    }

    /**
     * Muestra el formulario para editar un juego existente. Acepta
     * ?redirect_to= (issue #182): llega desde el lápiz de edición del
     * listado, con la URL completa de esa página (filtros, orden y página
     * incluidos) para poder volver exactamente ahí al guardar en vez de caer
     * siempre en el listado sin filtrar — editar varios juegos seguidos
     * desde un listado filtrado devolvía a la página 1 sin filtros cada vez.
     */
    public function edit(Request $request, Game $game): View
    {
        Gate::authorize('update', $game);

        $platforms = Platform::where('user_id', auth()->id())->orderBy('name')->get();
        $editions = Edition::where('user_id', auth()->id())->with('platforms')->orderBy('name')->get();
        $availableGenres = $this->availableGenres();

        // Llega en ?convert_to_owned=1 desde la acción "Pasar a la colección"
        // de la wishlist: mismo formulario de edición de siempre, con todos
        // los datos ya insertados, pero con Propiedad y fecha de compra
        // preseleccionadas para no tener que cambiarlas a mano.
        $convertToOwned = $request->boolean('convert_to_owned');

        $redirectTo = $this->safeInternalRedirect($request->query('redirect_to'));

        return view('games.edit', compact('game', 'platforms', 'editions', 'convertToOwned', 'availableGenres', 'redirectTo'));
    }

    /**
     * Actualiza el juego en la base de datos.
     */
    public function update(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        $validated = $this->validated($request);

        if ($errors = $this->duplicateWarnings($request, $validated, $game)) {
            return back()->withInput()->withErrors($errors);
        }

        if ($request->hasFile('cover')) {
            if ($game->cover) {
                Storage::disk('public')->delete($game->cover);
            }
            $validated['cover'] = $request->file('cover')->store('covers', 'public');
            $validated += $this->readCoverDimensions($validated['cover']);
        } elseif ($request->boolean('remove_cover')) {
            if ($game->cover) {
                Storage::disk('public')->delete($game->cover);
            }
            $validated['cover'] = null;
            $validated += $this->readCoverDimensions(null);
        } elseif ($request->filled('cover_url')) {
            // Carátula elegida desde "Buscar carátula en CEX" (ver
            // GameCoverLookupController::coverLookup()): mismo tratamiento
            // que en el alta, se descarga aquí y se borra la anterior si había.
            $downloaded = $this->coverDownloader->download((string) $request->input('cover_url'));
            if ($downloaded !== null) {
                if ($game->cover) {
                    Storage::disk('public')->delete($game->cover);
                }
                $validated['cover'] = $downloaded;
                $validated += $this->readCoverDimensions($downloaded);
            } else {
                unset($validated['cover']);
            }
        } else {
            unset($validated['cover']);
        }

        $game->update($validated);

        $redirectTo = $this->safeInternalRedirect($request->input('redirect_to'));

        return redirect()->to($redirectTo ?? route('web.games.index'))->with('success', 'Juego actualizado correctamente.');
    }

    /**
     * Elimina (Soft Delete) un juego.
     */
    public function destroy(Game $game): RedirectResponse
    {
        Gate::authorize('delete', $game);

        $id = $game->id;
        $title = $game->title;

        $game->delete();

        return redirect()->route('web.games.index')
            ->with('success', "«{$title}» enviado a la papelera.")
            // El toast lee esto para mostrar un botón "Deshacer" que llama a
            // esta URL en vez de tener que ir a la papelera a restaurarlo.
            ->with('undoUrl', route('web.games.restore', $id));
    }

    /**
     * Reglas comunes al alta y la edición. El campo 'cover' se valida aquí
     * (para que @error('cover') funcione) pero el valor final que se guarda
     * se decide en store()/update(), no el que devuelve validate().
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'ean' => 'nullable|string|max:50',
            'developer' => 'nullable|string|max:255',
            // Rule::exists(...)->where(...), no 'exists:platforms,id' a
            // secas: catálogo por cuenta (issue #175) — sin esto, un juego
            // podría enlazarse a la plataforma/edición de otro usuario.
            'platform_id' => ['nullable', Rule::exists('platforms', 'id')->where('user_id', auth()->id())],
            'edition_id' => ['nullable', Rule::exists('editions', 'id')->where('user_id', auth()->id())],
            'release_date' => 'nullable|date',
            'genres' => 'nullable|string|max:500',
            // 'sold' no es un valor asignable aquí: requiere precio/fecha de
            // venta, se marca desde SalesController::markAsSold().
            'status' => ['nullable', 'string', Rule::in(Game::STATUSES)],
            'wishlist_priority' => 'nullable|integer|min:1|max:3',
            // max:99999999.99: mismo motivo que price_paid más abajo, tope
            // real de la columna decimal(10,2).
            'wishlist_estimated_price' => 'nullable|numeric|min:0|max:99999999.99',
            'wishlist_store' => 'nullable|string|max:255',
            'play_status' => ['required', 'string', Rule::in(Game::PLAY_STATUSES)],
            'rating' => ['nullable', 'integer', 'min:'.Game::RATING_MIN, 'max:'.Game::RATING_MAX],
            // max:99999.9: tope real de la columna decimal(6,1) — sin esto,
            // un valor fuera de rango pasa la validación de Laravel y revienta
            // como excepción de base de datos (overflow) en vez de un aviso
            // normal del formulario.
            'playtime_hours' => 'nullable|numeric|min:0|max:99999.9',
            // max:99999999.99: mismo motivo, tope real de la columna decimal(10,2).
            'price_paid' => 'nullable|numeric|min:0|max:99999999.99',
            'purchase_place' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'manual_status' => ['nullable', 'string', Rule::in(Game::MANUAL_STATUSES)],
            'region_select' => 'nullable|string|max:20',
            'region_other' => 'required_if:region_select,other|nullable|string|max:50',
            'age_rating_select' => 'nullable|string|max:20',
            'age_rating_other' => 'required_if:age_rating_select,other|nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
            // 512KB (antes 1MB, #116): límite razonable para una carátula de
            // caja de videojuego sin perder calidad perceptible.
            'cover' => 'nullable|image|max:512',
        ]);

        $validated['genres'] = $this->parseGenres($request->input('genres'));
        $validated['region'] = $this->resolveRegion($validated);
        $validated['age_rating'] = $this->resolveAgeRating($validated);
        // Checkbox HTML: si no está marcado, el navegador ni siquiera manda el
        // campo, así que no puede tratarse como "sometimes" o desmarcarlo en
        // la edición nunca se guardaría.
        $validated['for_sale'] = $request->boolean('for_sale');

        unset($validated['region_select'], $validated['region_other'], $validated['age_rating_select'], $validated['age_rating_other']);

        return $validated;
    }

    /**
     * Agrupa los dos avisos de posible duplicado (EAN exacto y título
     * parecido, issue #25) en una sola respuesta: si el usuario corrige o
     * confirma uno pero no ha visto todavía el otro, mejor enseñar ambos de
     * golpe que ir descubriéndolos uno a uno en sucesivos reenvíos.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function duplicateWarnings(Request $request, array $validated, ?Game $ignore): array
    {
        $errors = [];

        if ($duplicate = $this->duplicateEan($request, $validated, $ignore)) {
            $errors['ean'] = "Ya tienes «{$duplicate->title}» registrado con este EAN.";
        }

        if ($similar = $this->similarTitle($request, $validated, $ignore)) {
            $errors['title_similar'] = "Ya tienes un juego con un título parecido: «{$similar->title}».";
        }

        return $errors;
    }

    /**
     * Busca otro juego del usuario con el mismo EAN (para avisar antes de
     * duplicar sin querer). Muchos juegos antiguos no tienen EAN, así que
     * nunca se compara cuando viene vacío: dos juegos sin EAN no son
     * "duplicados" entre sí. El aviso se puede saltar mandando
     * confirm_duplicate=1 (botón "Guardar igualmente" del propio aviso, ver
     * games/_form.blade.php), para permitir el caso legítimo de tener dos
     * copias físicas del mismo juego.
     *
     * @param  array<string, mixed>  $validated
     */
    private function duplicateEan(Request $request, array $validated, ?Game $ignore): ?Game
    {
        if (blank($validated['ean'] ?? null) || $request->boolean('confirm_duplicate')) {
            return null;
        }

        return Game::where('user_id', auth()->id())
            ->where('ean', $validated['ean'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->first();
    }

    /**
     * Busca otro juego del usuario con un título parecido (issue #25): el
     * EAN exacto de duplicateEan() no avisa de nada si el EAN no coincide o
     * viene en blanco, aunque el título sea prácticamente el mismo — p. ej.
     * dar de alta "Hollow Knight: Voidheart Edition" no avisaba si ya se
     * tenía "Hollow Knight". Criterio de similitud: subcadena sin distinguir
     * mayúsculas en cualquiera de los dos sentidos (uno de los títulos
     * contiene al otro), sin acotar por plataforma — dos ediciones del mismo
     * juego en plataformas distintas son legítimamente juegos distintos,
     * pero se prefiere avisar igual y dejar decidir al usuario. El aviso se
     * puede saltar mandando confirm_similar_title=1 (mismo patrón "Guardar
     * igualmente" que duplicateEan(), ver games/_form.blade.php), sin
     * bloquear el alta/edición en ningún caso.
     *
     * @param  array<string, mixed>  $validated
     */
    private function similarTitle(Request $request, array $validated, ?Game $ignore): ?Game
    {
        $title = trim((string) ($validated['title'] ?? ''));

        if ($title === '' || $request->boolean('confirm_similar_title')) {
            return null;
        }

        return Game::where('user_id', auth()->id())
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->where(function ($query) use ($title) {
                $query->whereRaw("LOWER(title) LIKE LOWER(CONCAT('%', ?, '%'))", [$title])
                    ->orWhereRaw("LOWER(?) LIKE LOWER(CONCAT('%', title, '%'))", [$title]);
            })
            ->first();
    }

    /**
     * Dimensiones reales de la carátula recién guardada, para poder fijar
     * width/height en <img> y evitar el salto de layout (#116).
     * getimagesize() solo lee la cabecera del fichero, no lo decodifica ni
     * redimensiona — coste insignificante frente a generar una miniatura.
     *
     * @return array{cover_width: int|null, cover_height: int|null}
     */
    private function readCoverDimensions(?string $path): array
    {
        if ($path === null) {
            return ['cover_width' => null, 'cover_height' => null];
        }

        $size = @getimagesize(Storage::disk('public')->path($path));

        return ['cover_width' => $size[0] ?? null, 'cover_height' => $size[1] ?? null];
    }

    /**
     * "Acción, Aventura, RPG" -> ['Acción', 'Aventura', 'RPG']
     *
     * @return array<int, string>|null
     */
    private function parseGenres(?string $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Valida que redirect_to (issue #182) sea una ruta interna segura antes
     * de usarla en un redirect()->to() — sin esto, alguien podría manipular
     * el campo oculto del formulario para mandar a un usuario autenticado a
     * un dominio externo (open redirect). Solo se acepta una ruta relativa
     * que empiece por una sola barra.
     */
    private function safeInternalRedirect(?string $url): ?string
    {
        if (blank($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//') || str_contains($url, '://')) {
            return null;
        }

        return $url;
    }

    /**
     * El desplegable de región manda un valor fijo (PAL-ES, NTSC-U...) o "other",
     * en cuyo caso el valor real viene del campo de texto libre 'region_other'.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveRegion(array $validated): ?string
    {
        $region = $validated['region_select'] ?? null;

        if ($region === 'other') {
            $region = trim((string) ($validated['region_other'] ?? '')) ?: null;
        }

        return $region;
    }

    /**
     * Mismo patrón que resolveRegion(): el desplegable manda "PEGI-12" (o
     * "other", con el texto libre real en age_rating_other) — se guarda como
     * "PEGI 12", el mismo formato canónico que escribe IgdbGameMatcher/
     * IgdbController::apply() (ver issue #46, Game::ageRatingBadge()).
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveAgeRating(array $validated): ?string
    {
        $ageRating = $validated['age_rating_select'] ?? null;

        if ($ageRating === 'other') {
            return trim((string) ($validated['age_rating_other'] ?? '')) ?: null;
        }

        return $ageRating !== null ? str_replace('-', ' ', $ageRating) : null;
    }
}
