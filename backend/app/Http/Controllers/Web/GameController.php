<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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
        $platforms = \App\Models\Platform::orderBy('name')->get();

        return view('games.create', compact('platforms'));
    }

    // Guarda el juego en la base de datos
    public function store(Request $request)
    {
        
        // 1. Validamos los datos que llegan del formulario
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'platform_id' => 'nullable|exists:platforms,id',
            'play_status' => 'required|string|in:pending,playing,finished',
            'rating'      => 'nullable|integer|min:1|max:5',
        ]);

        $validated['user_id'] = auth()->id();

        // 2. Creamos el juego en la base de datos
        Game::create($validated);

        // 3. Redirigimos al inicio
        return redirect()->route('web.games.index');
    }

    /**
     * Muestra el formulario para editar un juego existente.
     */
    public function edit(Game $game)
    {
        Gate::authorize('update', $game);

        $platforms = \App\Models\Platform::all();
        return view('games.edit', compact('game', 'platforms'));
    }

    /**
     * Actualiza el juego en la base de datos.
     */
    public function update(Request $request, Game $game)
    {
        Gate::authorize('update', $game);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'platform_id' => 'nullable|exists:platforms,id',
            'play_status' => 'required|string|in:pending,playing,finished',
            'rating'      => 'nullable|integer|min:1|max:5',
        ]);

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
}