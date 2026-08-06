<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ManufacturerController extends Controller
{
    public function index()
    {
        $manufacturers = Manufacturer::withCount('platforms')->orderBy('name')->get();

        return view('manufacturers.index', compact('manufacturers'));
    }

    public function create()
    {
        return view('manufacturers.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        Manufacturer::create($validated);

        return redirect()->route('web.manufacturers.index')->with('success', 'Fabricante creado correctamente.');
    }

    public function edit(Manufacturer $manufacturer)
    {
        return view('manufacturers.edit', compact('manufacturer'));
    }

    public function update(Request $request, Manufacturer $manufacturer)
    {
        $validated = $this->validated($request);

        if ($validated['name'] !== $manufacturer->name) {
            $validated['slug'] = $this->uniqueSlug($validated['name'], $manufacturer->id);
        }

        $manufacturer->update($validated);

        return redirect()->route('web.manufacturers.index')->with('success', 'Fabricante actualizado correctamente.');
    }

    public function destroy(Manufacturer $manufacturer)
    {
        // Las plataformas de este fabricante no se borran: manufacturer_id pasa a null (ver migración).
        $manufacturer->delete();

        return redirect()->route('web.manufacturers.index')->with('success', 'Fabricante eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('manufacturers', 'name')->ignore($request->route('manufacturer')),
            ],
            'bg_color'     => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'text_color'   => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'border_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Manufacturer::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
