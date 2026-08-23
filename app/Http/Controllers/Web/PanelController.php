<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PanelController extends Controller
{
    /**
     * Punto de entrada único para tareas que antes vivían sueltas por el
     * sidebar (importar, papelera): importar/exportar la colección, la
     * papelera de reciclaje y el perfil del usuario.
     */
    public function index()
    {
        $trashedCount = Game::onlyTrashed()->where('user_id', auth()->id())->count();

        return view('panel.index', compact('trashedCount'));
    }

    /**
     * Ajustes de comportamiento de la app para el usuario logueado (no hay
     * concepto de instancia/admin en esta app, ver GameController: todo
     * cuelga de auth()->id()).
     */
    public function settings(): View
    {
        $editions = Edition::orderBy('name')->get();

        return view('panel.settings', ['user' => auth()->user(), 'editions' => $editions]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_sort' => ['nullable', Rule::in(array_keys(GameController::SORTABLE_COLUMNS))],
            'default_dir' => 'nullable|in:asc,desc',
            'default_per_page' => ['nullable', Rule::in(GameController::PER_PAGE_OPTIONS)],
            'default_region' => ['nullable', Rule::in(GameController::REGION_PRESETS)],
            'default_edition_id' => 'nullable|exists:editions,id',
            'igdb_client_id' => 'nullable|string|max:255',
            'igdb_client_secret' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        // Los selects "vacíos" (Más recientes primero / Sin especificar /
        // Ninguna) llegan como '', que ConvertEmptyStringsToNull ya vuelve
        // null antes de aquí.
        $user->update([
            'default_sort' => $validated['default_sort'] ?? null,
            'default_dir' => $validated['default_dir'] ?? 'desc',
            'default_per_page' => $validated['default_per_page'] ?? 20,
            'default_region' => $validated['default_region'] ?? null,
            'default_edition_id' => $validated['default_edition_id'] ?? null,
            // Checkboxes: si no llegan en el POST, es que estaban desmarcados.
            'auto_igdb_background' => $request->boolean('auto_igdb_background'),
            'quick_search_exclude_wishlist' => $request->boolean('quick_search_exclude_wishlist'),
            'hide_for_sale_from_collection' => $request->boolean('hide_for_sale_from_collection'),
            'igdb_enabled' => $request->boolean('igdb_enabled'),
            'two_factor_enabled' => $request->boolean('two_factor_enabled'),
            'igdb_client_id' => $validated['igdb_client_id'] ?? null,
            // El campo llega siempre en blanco desde la vista (nunca se
            // reimprime un secreto ya guardado, ver settings.blade.php): en
            // blanco significa "no tocar", no "borrar la que ya había".
            'igdb_client_secret' => filled($validated['igdb_client_secret'] ?? null)
                ? $validated['igdb_client_secret']
                : $user->igdb_client_secret,
        ]);

        return redirect()->route('web.panel.settings')->with('success', 'Ajustes actualizados.');
    }

    /**
     * AJAX fire-and-forget desde el icono de tema y los botones de vista de
     * la colección (ver initThemeToggle/initGamesViewToggle en app.js): no
     * pasan por el formulario de Ajustes de arriba, ya tienen su propio
     * control en el header/la colección.
     */
    public function updateDisplay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => 'sometimes|in:dark,light',
            'games_view' => 'sometimes|in:list,compact,grid',
        ]);

        $request->user()->update($validated);

        return response()->json(['ok' => true]);
    }
}
