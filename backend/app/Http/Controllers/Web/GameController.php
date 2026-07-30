<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class GameController extends Controller
{

    // Colección del usuario, con búsqueda opcional por título o EAN (?q=)
    public function index(Request $request)
    {
        $query = trim($request->input('q', ''));

        $games = Game::where('user_id', auth()->id())
            ->with('platform.manufacturer')
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('title', 'ILIKE', '%' . $query . '%')
                        ->orWhere('ean', $query);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('games.index', compact('games', 'query'));
    }

    // Muestra el formulario de alta
    public function create(Request $request)
    {
        $platforms = Platform::orderBy('name')->get();
        $editions = Edition::with('platforms')->orderBy('name')->get();

        return view('games.create', compact('platforms', 'editions'));
    }

    // Guarda el juego en la base de datos
    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $validated['cover'] = $request->hasFile('cover')
            ? $request->file('cover')->store('covers', 'public')
            : null;

        $validated['user_id'] = auth()->id();

        Game::create($validated);

        return redirect()->route('web.games.index')->with('success', 'Juego añadido correctamente.');
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

        $game->delete();

        return redirect()->route('web.games.index')->with('success', 'Juego enviado a la papelera.');
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
            'condition'      => 'nullable|string|in:mint,good,fair,poor',
            'rating'         => 'nullable|integer|min:1|max:5',
            'price_paid'     => 'nullable|numeric|min:0',
            'purchase_place' => 'nullable|string|max:255',
            'purchase_date'  => 'nullable|date',
            'manual_status'  => 'nullable|string|in:included,missing',
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
