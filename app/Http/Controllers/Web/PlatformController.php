<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformController extends Controller
{
    use GeneratesUniqueSlug;

    public function index(): View
    {
        $platforms = Platform::with('manufacturer')->orderBy('name')->get();

        return view('platforms.index', compact('platforms'));
    }

    public function create(): View
    {
        $manufacturers = Manufacturer::orderBy('name')->get();

        return view('platforms.create', compact('manufacturers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug(Platform::class, $validated['name']);

        Platform::create($validated);

        return redirect()->route('web.platforms.index')->with('success', 'Plataforma creada correctamente.');
    }

    public function edit(Platform $platform): View
    {
        $manufacturers = Manufacturer::orderBy('name')->get();

        return view('platforms.edit', compact('platform', 'manufacturers'));
    }

    public function update(Request $request, Platform $platform): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($validated['name'] !== $platform->name) {
            $validated['slug'] = $this->uniqueSlug(Platform::class, $validated['name'], $platform->id);
        }

        $platform->update($validated);

        return redirect()->route('web.platforms.index')->with('success', 'Plataforma actualizada correctamente.');
    }

    public function destroy(Platform $platform): RedirectResponse
    {
        // Los juegos de esta plataforma no se borran: platform_id pasa a null (ver migración).
        $platform->delete();

        return redirect()->route('web.platforms.index')->with('success', 'Plataforma eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'label' => 'nullable|string|max:20',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'override_colors' => 'nullable|boolean',
            'bg_color' => 'required_if:override_colors,1|nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'required_if:override_colors,1|nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'border_color' => 'required_if:override_colors,1|nullable|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        // Sin override: la plataforma hereda los colores del fabricante (columnas a null).
        if (empty($validated['override_colors'])) {
            $validated['bg_color'] = null;
            $validated['text_color'] = null;
            $validated['border_color'] = null;
        }

        unset($validated['override_colors']);

        return $validated;
    }
}
