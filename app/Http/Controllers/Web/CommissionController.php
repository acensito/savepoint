<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommissionController extends Controller
{
    /**
     * Encargos del usuario (juegos que le deben o que él debe, ver
     * Commission::DIRECTIONS), pendientes primero. Sin paginar, como
     * /sales: no se espera un volumen alto.
     */
    public function index(Request $request)
    {
        $direction = (string) $request->input('direction', '');

        $commissions = Commission::where('user_id', auth()->id())
            ->with(['platform:id,name,label,bg_color,text_color,border_color,manufacturer_id', 'platform.manufacturer:id,bg_color,text_color,border_color'])
            ->when($direction !== '', fn ($q) => $q->where('direction', $direction))
            ->orderByRaw('resolved_at IS NOT NULL')
            ->orderByDesc('created_at')
            ->get();

        return view('commissions.index', compact('commissions', 'direction'));
    }

    public function create()
    {
        $platforms = Platform::orderBy('name')->get();

        return view('commissions.create', compact('platforms'));
    }

    public function store(Request $request): RedirectResponse
    {
        Commission::create([
            ...$this->validated($request),
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('web.commissions.index')->with('success', 'Encargo añadido.');
    }

    public function edit(Commission $commission)
    {
        Gate::authorize('update', $commission);

        $platforms = Platform::orderBy('name')->get();

        return view('commissions.edit', compact('commission', 'platforms'));
    }

    public function update(Request $request, Commission $commission): RedirectResponse
    {
        Gate::authorize('update', $commission);

        $commission->update($this->validated($request));

        return redirect()->route('web.commissions.index')->with('success', 'Encargo actualizado.');
    }

    public function destroy(Commission $commission): RedirectResponse
    {
        Gate::authorize('delete', $commission);

        $commission->delete();

        return redirect()->route('web.commissions.index')->with('success', 'Encargo eliminado.');
    }

    /**
     * Acción central: marca el encargo como resuelto. En 'owed_by_me' (yo
     * debo el juego) solo anota la fecha de envío. En 'owed_to_me' (me lo
     * deben) además da de alta el Game de verdad en la colección y redirige
     * a su edición para completar lo que el encargo no recoge (EAN,
     * condición, manual...) — mismo espíritu que "Pasar a la colección"
     * desde la wishlist. El encargo nunca se borra ni cambia de tabla aquí:
     * sigue listado como histórico, con game_id enlazando a la ficha nueva
     * si se creó una.
     */
    public function resolve(Request $request, Commission $commission): RedirectResponse
    {
        Gate::authorize('update', $commission);

        $validated = $request->validate([
            'resolved_at' => 'nullable|date',
        ]);
        $resolvedAt = $validated['resolved_at'] ?? now()->toDateString();

        if ($commission->direction === Commission::DIRECTION_OWED_BY_ME) {
            $commission->update(['resolved_at' => $resolvedAt]);

            return redirect()->route('web.commissions.index')->with('success', "«{$commission->title}» marcado como enviado.");
        }

        $game = Game::create([
            'user_id' => auth()->id(),
            'title' => $commission->title,
            'platform_id' => $commission->platform_id,
            'price_paid' => $commission->price,
            'purchase_date' => $commission->purchased_at ?? $resolvedAt,
            'status' => 'owned',
            'play_status' => 'pending',
        ]);

        $commission->update(['resolved_at' => $resolvedAt, 'game_id' => $game->id]);

        return redirect()->route('web.games.edit', $game->id)
            ->with('success', "«{$commission->title}» recibido: completa el resto de datos.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'platform_id' => 'nullable|exists:platforms,id',
            'counterparty_name' => 'required|string|max:255',
            'direction' => 'required|string|in:' . implode(',', Commission::DIRECTIONS),
            'price' => 'nullable|numeric|min:0',
            'purchased_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
    }
}
