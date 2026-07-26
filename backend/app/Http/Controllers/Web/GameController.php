<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\Request;

class GameController extends Controller
{

    public function index()
    {
        $games = Game::with('platform')->latest()->get();
        return view('games.index', compact('games'));
    }

    // Muestra el formulario
    public function create(Request $request)
    {
        $platforms = \App\Models\Platform::orderBy('name')->get();
        $prefilledTitle = $request->input('title', '');

        return view('games.create-manual', compact('platforms', 'prefilledTitle'));
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

        //asignamos al usuario de momento como el temporal
        $validated['user_id'] = \App\Models\User::first()->id ?? 1;
        // $validated['user_id'] = auth()->id();

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
        $platforms = \App\Models\Platform::all();
        return view('games.edit', compact('game', 'platforms'));
    }

    /**
     * Actualiza el juego en la base de datos.
     */
    public function update(Request $request, Game $game)
    {
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
        $game->delete();

        return redirect()->route('web.games.index')->with('success', 'Juego enviado a la papelera.');
    }

    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));

        $localGames = Game::with('platform')
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('title', 'ILIKE', '%' . $query . '%')
                        ->orWhere('ean', $query);
                });
            })
            ->paginate(20)
            ->withQueryString();

        return view('games.search', compact('localGames', 'query'));
    }

    // Muestra el formulario manual si el usuario pulsa en "+ Introducir datos manualmente"
    public function createManual(Request $request)
    {
        $platforms = \App\Models\Platform::orderBy('name')->get();
        $prefilledTitle = $request->input('title', '');

        return view('games.create-manual', compact('platforms', 'prefilledTitle'));
    }
}