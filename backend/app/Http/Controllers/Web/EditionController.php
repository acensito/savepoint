<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Platform;
use Illuminate\Http\Request;

class EditionController extends Controller
{
    public function index()
    {
        $editions = Edition::with('platforms')->withCount('games')->orderBy('name')->get();

        return view('editions.index', compact('editions'));
    }

    public function create()
    {
        $platforms = Platform::orderBy('name')->get();

        return view('editions.create', compact('platforms'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $edition = Edition::create(['name' => $validated['name']]);
        $edition->platforms()->sync($validated['platform_ids'] ?? []);

        return redirect()->route('web.editions.index')->with('success', 'Edición creada correctamente.');
    }

    public function edit(Edition $edition)
    {
        $platforms = Platform::orderBy('name')->get();
        $edition->load('platforms');

        return view('editions.edit', compact('edition', 'platforms'));
    }

    public function update(Request $request, Edition $edition)
    {
        $validated = $this->validated($request);

        $edition->update(['name' => $validated['name']]);
        $edition->platforms()->sync($validated['platform_ids'] ?? []);

        return redirect()->route('web.editions.index')->with('success', 'Edición actualizada correctamente.');
    }

    public function destroy(Edition $edition)
    {
        // Los juegos con esta edición no se borran: edition_id pasa a null (ver migración de games).
        $edition->delete();

        return redirect()->route('web.editions.index')->with('success', 'Edición eliminada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'           => 'required|string|max:255',
            'platform_ids'   => 'nullable|array',
            'platform_ids.*' => 'exists:platforms,id',
        ]);
    }
}
