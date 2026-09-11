<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Catálogo por cuenta (issue #175): cada usuario gestiona sus propios
 * fabricantes, sin dato compartido con el resto.
 */
class ManufacturerController extends Controller
{
    use GeneratesUniqueSlug;

    public function index(): View
    {
        $manufacturers = Manufacturer::where('user_id', auth()->id())->withCount('platforms')->orderBy('name')->get();

        return view('manufacturers.index', compact('manufacturers'));
    }

    public function create(): View
    {
        return view('manufacturers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['user_id'] = auth()->id();
        $validated['slug'] = $this->uniqueSlug(Manufacturer::class, $validated['name'], auth()->id());

        Manufacturer::create($validated);

        return redirect()->route('web.manufacturers.index')->with('success', 'Fabricante creado correctamente.');
    }

    public function edit(Manufacturer $manufacturer): View
    {
        Gate::authorize('update', $manufacturer);

        return view('manufacturers.edit', compact('manufacturer'));
    }

    public function update(Request $request, Manufacturer $manufacturer): RedirectResponse
    {
        Gate::authorize('update', $manufacturer);

        $validated = $this->validated($request);

        if ($validated['name'] !== $manufacturer->name) {
            $validated['slug'] = $this->uniqueSlug(Manufacturer::class, $validated['name'], auth()->id(), $manufacturer->id);
        }

        $manufacturer->update($validated);

        return redirect()->route('web.manufacturers.index')->with('success', 'Fabricante actualizado correctamente.');
    }

    public function destroy(Manufacturer $manufacturer): RedirectResponse
    {
        Gate::authorize('delete', $manufacturer);

        // Las plataformas de este fabricante no se borran: manufacturer_id pasa a null (ver migración).
        $manufacturer->delete();

        return redirect()->route('web.manufacturers.index')->with('success', 'Fabricante eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('manufacturers', 'name')->where('user_id', auth()->id())->ignore($request->route('manufacturer')),
            ],
            'bg_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'border_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);
    }
}
