<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\MatchGameWithIgdb;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use App\Services\GameLookup\IgdbGameMatcher;
use App\Services\GameLookup\IgdbLookupService;
use App\Services\Games\GameCollectionQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class GameController extends Controller
{
    public function __construct(
        private readonly GameCollectionQuery $collectionQuery,
        private readonly IgdbLookupService $igdbLookup,
        private readonly IgdbGameMatcher $igdbMatcher,
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

    // Colección del usuario, con búsqueda por título/EAN, filtros por plataforma/estado
    // (?q=, ?platform_id=, ?play_status=, ?status=), orden (?sort=, ?dir=) y
    // tamaño de página (?per_page=)
    public function index(Request $request)
    {
        // ConvertEmptyStringsToNull (middleware por defecto) transforma los campos
        // vacíos del formulario en null, así que hay que castear a string antes de
        // comparar con '' o whereNull() saldría disparado sin querer.
        $query = trim((string) $request->input('q', ''));
        $platformId = (string) $request->input('platform_id', '');
        $playStatus = (string) $request->input('play_status', '');
        $status = (string) $request->input('status', '');
        $forSale = (string) $request->input('for_sale', '');
        [$sort, $dir] = $this->collectionQuery->resolveSort($request);
        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE_OPTIONS, true)
            ? (int) $request->input('per_page')
            : (int) (auth()->user()->default_per_page ?: 20);

        // Calculados aquí (no en la vista) porque los necesitan tanto la página
        // completa como el fragmento que se devuelve por AJAX al buscar en vivo.
        $hasActiveFilters = $query !== '' || $platformId !== '' || $playStatus !== '' || $status !== '' || $forSale !== '';
        $hasAdvancedFilters = $platformId !== '' || $playStatus !== '' || $status !== '' || $forSale !== '';
        $activeFilterCount = collect([$query !== '', $platformId !== '', $playStatus !== '', $status !== '', $forSale !== ''])->filter()->count();

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

        $platforms = Platform::orderBy('name')->get();

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
            'games', 'query', 'platforms', 'platformId', 'playStatus', 'status', 'forSale', 'sort', 'dir', 'perPage',
            'collectionTotals', 'hasActiveFilters', 'hasAdvancedFilters', 'activeFilterCount',
        ));
    }

    /**
     * Muestra el formulario de alta. Acepta ?ean= y/o ?title= para
     * prellenar esos campos: los usa la búsqueda rápida (Ctrl+K) cuando un
     * código escaneado o buscado no coincide con ningún juego ya registrado,
     * para no tener que volver a teclearlo aquí.
     */
    public function create(Request $request)
    {
        $platforms = Platform::orderBy('name')->get();
        $editions = Edition::with('platforms')->orderBy('name')->get();

        $prefill = [
            'ean' => $request->query('ean'),
            'title' => $request->query('title'),
            // Llega desde la ficha de comprobación de una sugerencia externa
            // (CEX) en la búsqueda rápida: la carátula no se descarga hasta
            // que se guarda el formulario (ver store()), aquí solo se
            // previsualiza.
            'cover_url' => $request->query('cover_url'),
        ];

        return view('games.create', compact('platforms', 'editions', 'prefill'));
    }

    // Guarda el juego en la base de datos
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        if ($duplicate = $this->duplicateEan($request, $validated, null)) {
            return back()->withInput()->withErrors([
                'ean' => "Ya tienes «{$duplicate->title}» registrado con este EAN.",
            ]);
        }

        if ($request->hasFile('cover')) {
            $validated['cover'] = $request->file('cover')->store('covers', 'public');
        } elseif (! $request->boolean('remove_cover') && $request->filled('cover_url')) {
            // Carátula sugerida desde la ficha de comprobación de una
            // búsqueda externa (CEX): se descarga aquí, no antes, para no
            // dejar ficheros huérfanos si el usuario nunca llega a guardar.
            $validated['cover'] = $this->downloadExternalCover((string) $request->input('cover_url'));
        } else {
            $validated['cover'] = null;
        }

        $validated['user_id'] = auth()->id();

        $game = Game::create($validated);

        if (auth()->user()->auto_igdb_background) {
            $this->autoAssignIgdbBackground($game);
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
    public function show(Game $game)
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
     * Ajuste "Fondo automático desde IGDB" (ver PanelController::settings):
     * si el juego recién dado de alta se identifica en IGDB y tiene arte
     * disponible, fija el primero como fondo, sin esperar a que el usuario
     * lo elija a mano. Nunca bloquea el alta si IGDB falla o no encuentra
     * nada — IgdbLookupService ya devuelve vacío/null en ese caso, nunca
     * lanza una excepción.
     *
     * Síncrono a propósito, a diferencia del match de show() (ver
     * MatchGameWithIgdb): el alta ya es una petición POST del usuario, no
     * bloquea la lectura de una ficha, y este flujo necesita el resultado en
     * el momento (igdb_id) para poder pedir a continuación el arte.
     */
    private function autoAssignIgdbBackground(Game $game): void
    {
        $this->igdbMatcher->matchIfNeeded($game);

        if ($game->igdb_id === null) {
            return;
        }

        $artworkId = $this->igdbLookup->artworks($game->igdb_id)[0] ?? null;

        if ($artworkId !== null) {
            $game->update(['igdb_background' => $artworkId]);
        }
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
    public function quickUpdate(Request $request, Game $game)
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
     * Muestra el formulario para editar un juego existente.
     */
    public function edit(Request $request, Game $game)
    {
        Gate::authorize('update', $game);

        $platforms = Platform::orderBy('name')->get();
        $editions = Edition::with('platforms')->orderBy('name')->get();

        // Llega en ?convert_to_owned=1 desde la acción "Pasar a la colección"
        // de la wishlist: mismo formulario de edición de siempre, con todos
        // los datos ya insertados, pero con Propiedad y fecha de compra
        // preseleccionadas para no tener que cambiarlas a mano.
        $convertToOwned = $request->boolean('convert_to_owned');

        return view('games.edit', compact('game', 'platforms', 'editions', 'convertToOwned'));
    }

    /**
     * Actualiza el juego en la base de datos.
     */
    public function update(Request $request, Game $game)
    {
        Gate::authorize('update', $game);

        $validated = $this->validated($request);

        if ($duplicate = $this->duplicateEan($request, $validated, $game)) {
            return back()->withInput()->withErrors([
                'ean' => "Ya tienes «{$duplicate->title}» registrado con este EAN.",
            ]);
        }

        if ($request->hasFile('cover')) {
            if ($game->cover) {
                Storage::disk('public')->delete($game->cover);
            }
            $validated['cover'] = $request->file('cover')->store('covers', 'public');
        } elseif ($request->boolean('remove_cover')) {
            if ($game->cover) {
                Storage::disk('public')->delete($game->cover);
            }
            $validated['cover'] = null;
        } elseif ($request->filled('cover_url')) {
            // Carátula elegida desde "Buscar carátula en CEX" (ver
            // GameCoverLookupController::coverLookup()): mismo tratamiento
            // que en el alta, se descarga aquí y se borra la anterior si había.
            $downloaded = $this->downloadExternalCover((string) $request->input('cover_url'));
            if ($downloaded !== null) {
                if ($game->cover) {
                    Storage::disk('public')->delete($game->cover);
                }
                $validated['cover'] = $downloaded;
            } else {
                unset($validated['cover']);
            }
        } else {
            unset($validated['cover']);
        }

        $game->update($validated);

        return redirect()->route('web.games.index')->with('success', 'Juego actualizado correctamente.');
    }

    /**
     * Elimina (Soft Delete) un juego.
     */
    public function destroy(Game $game)
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
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'ean' => 'nullable|string|max:50',
            'developer' => 'nullable|string|max:255',
            'platform_id' => 'nullable|exists:platforms,id',
            'edition_id' => 'nullable|exists:editions,id',
            'release_date' => 'nullable|date',
            'genres' => 'nullable|string|max:500',
            // 'sold' no es un valor asignable aquí: requiere precio/fecha de
            // venta, se marca desde SalesController::markAsSold().
            'status' => ['nullable', 'string', Rule::in(Game::STATUSES)],
            'wishlist_priority' => 'nullable|integer|min:1|max:3',
            'wishlist_estimated_price' => 'nullable|numeric|min:0',
            'wishlist_store' => 'nullable|string|max:255',
            'play_status' => ['required', 'string', Rule::in(Game::PLAY_STATUSES)],
            'rating' => ['nullable', 'integer', 'min:'.Game::RATING_MIN, 'max:'.Game::RATING_MAX],
            'playtime_hours' => 'nullable|numeric|min:0',
            'price_paid' => 'nullable|numeric|min:0',
            'purchase_place' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'manual_status' => ['nullable', 'string', Rule::in(Game::MANUAL_STATUSES)],
            'region_select' => 'nullable|string|max:20',
            'region_other' => 'required_if:region_select,other|nullable|string|max:50',
            'age_rating' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:2000',
            'cover' => 'nullable|image|max:1024',
        ]);

        $validated['genres'] = $this->parseGenres($request->input('genres'));
        $validated['region'] = $this->resolveRegion($validated);
        // Checkbox HTML: si no está marcado, el navegador ni siquiera manda el
        // campo, así que no puede tratarse como "sometimes" o desmarcarlo en
        // la edición nunca se guardaría.
        $validated['for_sale'] = $request->boolean('for_sale');

        unset($validated['region_select'], $validated['region_other']);

        return $validated;
    }

    /**
     * Descarga la carátula sugerida por una búsqueda externa (CEX) cuando el
     * usuario confirma el alta desde su ficha de comprobación, en vez de
     * subir un fichero a mano. El campo llega como texto en el propio
     * formulario (input oculto), así que se valida contra una lista de hosts
     * permitidos (config('services.cex.image_hosts')) antes de pedirlo: sin
     * eso, cualquiera podría manipular ese campo para convertir el alta de
     * un juego en un proxy hacia una URL interna (SSRF). Si algo falla (host
     * no permitido, timeout, no es una imagen...) se devuelve null en vez de
     * lanzar: el juego se crea igualmente, solo sin carátula.
     */
    private function downloadExternalCover(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $allowedHosts = config('services.cex.image_hosts', []);

        if ($scheme !== 'https' || $host === null || ! in_array($host, $allowedHosts, true)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->withOptions(['allow_redirects' => false])->get($url);
        } catch (Throwable $e) {
            return null;
        }

        if (! $response->ok() || strlen($response->body()) > 3 * 1024 * 1024) {
            return null;
        }

        $extension = match (true) {
            str_starts_with((string) $response->header('Content-Type'), 'image/jpeg') => 'jpg',
            str_starts_with((string) $response->header('Content-Type'), 'image/png') => 'png',
            str_starts_with((string) $response->header('Content-Type'), 'image/webp') => 'webp',
            default => null,
        };

        if ($extension === null) {
            return null;
        }

        $path = 'covers/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    /**
     * Busca otro juego del usuario con el mismo EAN (para avisar antes de
     * duplicar sin querer). Muchos juegos antiguos no tienen EAN, así que
     * nunca se compara cuando viene vacío: dos juegos sin EAN no son
     * "duplicados" entre sí. El aviso se puede saltar mandando
     * confirm_duplicate=1 (checkbox "Guardar de todos modos" en el formulario),
     * para permitir el caso legítimo de tener dos copias físicas del mismo juego.
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
     * "Acción, Aventura, RPG" -> ['Acción', 'Aventura', 'RPG']
     */
    private function parseGenres(?string $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * El desplegable de región manda un valor fijo (PAL-ES, NTSC-U...) o "other",
     * en cuyo caso el valor real viene del campo de texto libre 'region_other'.
     */
    private function resolveRegion(array $validated): ?string
    {
        $region = $validated['region_select'] ?? null;

        if ($region === 'other') {
            $region = trim((string) ($validated['region_other'] ?? '')) ?: null;
        }

        return $region;
    }
}
