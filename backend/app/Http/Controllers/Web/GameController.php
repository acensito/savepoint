<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GameController extends Controller
{
    /**
     * Columnas por las que se puede ordenar el listado desde ?sort=, mapeadas
     * a la columna real (evita pasar un nombre de columna arbitrario del
     * usuario directamente a orderBy()).
     */
    private const SORTABLE_COLUMNS = [
        'title' => 'title',
        'price_paid' => 'price_paid',
        'rating' => 'rating',
        'purchase_date' => 'purchase_date',
    ];

    /**
     * Tamaños de página permitidos desde ?per_page= en el listado web (a
     * diferencia de la API, aquí se restringe a un puñado de valores fijos
     * pensados para un selector, no un número libre).
     */
    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

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
        $sort = (string) $request->input('sort', '');
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $sortColumn = self::SORTABLE_COLUMNS[$sort] ?? null;
        $perPage = in_array((int) $request->input('per_page'), self::PER_PAGE_OPTIONS, true)
            ? (int) $request->input('per_page')
            : 20;

        // Calculados aquí (no en la vista) porque los necesitan tanto la página
        // completa como el fragmento que se devuelve por AJAX al buscar en vivo.
        $hasActiveFilters = $query !== '' || $platformId !== '' || $playStatus !== '' || $status !== '';
        $hasAdvancedFilters = $platformId !== '' || $playStatus !== '' || $status !== '';
        $activeFilterCount = collect([$query !== '', $platformId !== '', $playStatus !== '', $status !== ''])->filter()->count();

        $games = Game::where('user_id', auth()->id())
            // Solo las columnas que pinta el listado: notes/data/genres/etc. serían
            // peso muerto en una tabla paginada y no se usan aquí.
            ->select([
                'id', 'title', 'cover', 'platform_id', 'edition_id',
                'play_status', 'status', 'rating', 'price_paid', 'purchase_date',
                'region', 'manual_status', 'created_at',
            ])
            // Relaciones acotadas a las columnas que realmente pinta el chip de
            // plataforma y el nombre de la edición, para no arrastrar el resto.
            ->with([
                'platform:id,name,label,bg_color,text_color,border_color,manufacturer_id',
                'platform.manufacturer:id,bg_color,text_color,border_color',
                'edition:id,name',
            ])
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->whereLike('title', '%' . $query . '%', caseSensitive: false)
                        ->orWhere('ean', $query);
                });
            })
            ->when($platformId !== '', fn ($q) => $q->where('platform_id', $platformId))
            ->when($playStatus !== '', fn ($q) => $q->where('play_status', $playStatus))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when(
                $sortColumn !== null,
                fn ($q) => $q->orderBy($sortColumn, $dir)->orderByDesc('id'),
                fn ($q) => $q->latest(),
            )
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
        // para la barra de estado discreta al pie del listado.
        $collectionTotals = [
            'count' => Game::where('user_id', auth()->id())->count(),
            'spent' => (float) Game::where('user_id', auth()->id())->sum('price_paid'),
        ];

        return view('games.index', compact(
            'games', 'query', 'platforms', 'platformId', 'playStatus', 'status', 'sort', 'dir', 'perPage',
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

        $validated['cover'] = $request->hasFile('cover')
            ? $request->file('cover')->store('covers', 'public')
            : null;

        $validated['user_id'] = auth()->id();

        Game::create($validated);

        return redirect()->route('web.games.index')->with('success', 'Juego añadido correctamente.');
    }

    /**
     * Ficha de solo lectura de un juego: toda la información del modelo sin
     * abrir el formulario de edición, para "solo mirar" un juego concreto.
     */
    public function show(Game $game)
    {
        Gate::authorize('view', $game);

        $game->load(['platform.manufacturer', 'edition']);

        return view('games.show', compact('game'));
    }

    /**
     * Edición rápida de valoración y/o estado de juego desde la propia fila
     * del listado (tabla, tarjetas o estantería), sin pasar por el
     * formulario completo. Se valida y actualiza solo el campo que llega.
     */
    public function quickUpdate(Request $request, Game $game)
    {
        Gate::authorize('update', $game);

        $validated = $request->validate([
            'rating' => 'sometimes|nullable|integer|min:1|max:5',
            'play_status' => 'sometimes|required|string|in:pending,playing,finished',
        ]);

        if ($validated === []) {
            return response()->json(['message' => 'Nada que actualizar.'], 422);
        }

        $game->update($validated);

        return response()->json([
            'rating' => $game->rating,
            'play_status' => $game->play_status,
        ]);
    }

    /**
     * Muestra el formulario para editar un juego existente.
     */
    public function edit(Game $game)
    {
        Gate::authorize('update', $game);

        $platforms = Platform::orderBy('name')->get();
        $editions = Edition::with('platforms')->orderBy('name')->get();

        return view('games.edit', compact('game', 'platforms', 'editions'));
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
     * Envía a la papelera de golpe todos los juegos seleccionados en el listado.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $this->ownedSelectedIds($request);

        Game::whereIn('id', $ids)->delete();

        return redirect()->route('web.games.index')->with(
            'success',
            count($ids) . ' ' . Str::plural('juego', count($ids)) . ' ' . (count($ids) === 1 ? 'enviado' : 'enviados') . ' a la papelera.'
        );
    }

    /**
     * Cambia el estado de juego (pendiente/jugando/terminado) de golpe a
     * todos los juegos seleccionados en el listado.
     */
    public function bulkUpdatePlayStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'play_status' => 'required|string|in:pending,playing,finished',
        ]);

        $ids = $this->ownedSelectedIds($request);

        Game::whereIn('id', $ids)->update(['play_status' => $validated['play_status']]);

        return redirect()->route('web.games.index')->with(
            'success',
            'Estado actualizado en ' . count($ids) . ' ' . Str::plural('juego', count($ids)) . '.'
        );
    }

    /**
     * IDs seleccionados en el formulario, acotados a los que de verdad
     * pertenecen al usuario autenticado (evita que alguien manipule el HTML
     * y mande el ID de un juego ajeno).
     */
    private function ownedSelectedIds(Request $request): array
    {
        $request->validate([
            'game_ids' => 'required|array|min:1',
            'game_ids.*' => 'integer',
        ]);

        return Game::where('user_id', auth()->id())
            ->whereIn('id', $request->input('game_ids'))
            ->pluck('id')
            ->all();
    }

    /**
     * Papelera: juegos borrados (soft delete) del usuario autenticado, con
     * búsqueda por título/EAN y filtro por plataforma (?q=, ?platform_id=),
     * igual que el listado principal.
     */
    public function trash(Request $request)
    {
        $query = trim((string) $request->input('q', ''));
        $platformId = (string) $request->input('platform_id', '');

        $games = Game::onlyTrashed()
            ->where('user_id', auth()->id())
            ->with([
                'platform:id,name,label,bg_color,text_color,border_color,manufacturer_id',
                'platform.manufacturer:id,bg_color,text_color,border_color',
            ])
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->whereLike('title', '%' . $query . '%', caseSensitive: false)
                        ->orWhere('ean', $query);
                });
            })
            ->when($platformId !== '', fn ($q) => $q->where('platform_id', $platformId))
            ->orderByDesc('deleted_at')
            ->paginate(20)
            ->withQueryString();

        $platforms = Platform::orderBy('name')->get();

        return view('games.trash', compact('games', 'query', 'platformId', 'platforms'));
    }

    /**
     * Restaura un juego de la papelera.
     */
    public function restore(int $id)
    {
        $game = Game::onlyTrashed()->findOrFail($id);

        Gate::authorize('restore', $game);

        $game->restore();

        return redirect()->route('web.games.trash')->with('success', 'Juego restaurado correctamente.');
    }

    /**
     * Elimina un juego definitivamente, saltándose la papelera.
     */
    public function forceDelete(int $id)
    {
        $game = Game::onlyTrashed()->findOrFail($id);

        Gate::authorize('forceDelete', $game);

        if ($game->cover) {
            Storage::disk('public')->delete($game->cover);
        }

        $game->forceDelete();

        return redirect()->route('web.games.trash')->with('success', 'Juego eliminado definitivamente.');
    }

    /**
     * Reglas comunes al alta y la edición. El campo 'cover' se valida aquí
     * (para que @error('cover') funcione) pero el valor final que se guarda
     * se decide en store()/update(), no el que devuelve validate().
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'ean'            => 'nullable|string|max:50',
            'developer'      => 'nullable|string|max:255',
            'platform_id'    => 'nullable|exists:platforms,id',
            'edition_id'     => 'nullable|exists:editions,id',
            'release_date'   => 'nullable|date',
            'genres'         => 'nullable|string|max:500',
            'status'         => 'nullable|string|in:owned,wishlist,sold',
            'play_status'    => 'required|string|in:pending,playing,finished',
            'rating'         => 'nullable|integer|min:1|max:5',
            'price_paid'     => 'nullable|numeric|min:0',
            'purchase_place' => 'nullable|string|max:255',
            'purchase_date'  => 'nullable|date',
            'manual_status'  => 'nullable|string|in:included,missing,booklet',
            'region_select'  => 'nullable|string|max:20',
            'region_other'   => 'required_if:region_select,other|nullable|string|max:50',
            'age_rating'     => 'nullable|string|max:20',
            'notes'          => 'nullable|string|max:2000',
            'cover'          => 'nullable|image|max:1024',
        ]);

        $validated['genres'] = $this->parseGenres($request->input('genres'));
        $validated['region'] = $this->resolveRegion($validated);

        unset($validated['region_select'], $validated['region_other']);

        return $validated;
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
