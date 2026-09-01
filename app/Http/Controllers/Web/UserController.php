<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\Users\AbandonedAccountPruner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Listado de todas las cuentas de la plataforma (solo admin, ver
     * UserPolicy): a diferencia del resto de listados de la app, sin
     * buscador/paginación — la escala aquí es de un puñado de cuentas.
     * Admins primero (son quienes más importa tener localizados) y, dentro
     * de cada grupo, más recientes primero — antes alfabético por nombre,
     * sin ninguna forma de distinguir de un vistazo una cuenta recién
     * llegada (issue #10).
     */
    public function index(AbandonedAccountPruner $pruner): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::withCount('games')
            ->orderByDesc('is_admin')
            ->orderByDesc('created_at')
            ->get();
        $appSetting = AppSetting::current();
        $abandonedCount = $pruner->pendingCount();

        return view('panel.users.index', compact('users', 'appSetting', 'abandonedCount'));
    }

    /**
     * Botón manual "Purgar cuentas abandonadas" (issue #10): sin job
     * programado, es la única forma de limpiarlas — ver
     * App\Services\Users\AbandonedAccountPruner para el criterio.
     */
    public function pruneAbandoned(AbandonedAccountPruner $pruner): RedirectResponse
    {
        Gate::authorize('viewAny', User::class);

        $count = $pruner->prune();

        $message = $count === 0
            ? 'No había ninguna cuenta abandonada que purgar.'
            : ($count === 1 ? 'Se ha purgado 1 cuenta abandonada.' : "Se han purgado {$count} cuentas abandonadas.");

        return redirect()->route('web.panel.users.index')->with('success', $message);
    }

    /**
     * Activa/desactiva el registro público (/register): toggle switch de
     * efecto/persistencia inmediatos (ver x-toggle e initSettingsToggles en
     * app.js), mismo patrón AJAX que PanelController::updateToggle. Reutiliza
     * la misma autorización que el resto de esta página ('viewAny' de
     * UserPolicy, "es admin"): un solo ajuste de instancia no justifica
     * todavía una policy propia aparte.
     */
    public function updateRegistration(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(['registration_enabled'])],
            'value' => ['required', 'boolean'],
        ]);

        AppSetting::current()->update(['registration_enabled' => $validated['value']]);

        return response()->json(['ok' => true]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('panel.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'string', User::passwordComplexityRule(), 'confirmed'],
            'is_admin' => 'boolean',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $request->boolean('is_admin'),
        ]);

        return redirect()->route('web.panel.users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('panel.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', User::passwordComplexityRule(), 'confirmed'],
            'is_admin' => 'boolean',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        $passwordChanged = ! empty($validated['password']);

        if ($passwordChanged) {
            $user->password = Hash::make($validated['password']);
        }

        // No puedes quitarte el rol admin a ti mismo desde este formulario:
        // sin esto, sería fácil dejarte fuera del propio panel de gestión sin querer.
        if ($user->id !== auth()->id()) {
            $user->is_admin = $request->boolean('is_admin');
        }

        $user->save();

        if ($passwordChanged) {
            // Ver ProfileController::updatePassword(): un admin cambiándole
            // la contraseña a otro usuario debe cortar también sus tokens de
            // la app móvil ya emitidos, no solo bloquear logins nuevos.
            $user->tokens()->delete();
        }

        return redirect()->route('web.panel.users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Borra una cuenta. Bloqueado si todavía tiene juegos: games.user_id es
     * NOT NULL con ON DELETE CASCADE a nivel de base de datos, así que
     * borrar el usuario borraría también su colección entera de forma
     * permanente y sin pasar por la papelera.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $gamesCount = $user->games()->count();
        if ($gamesCount > 0) {
            return back()->with('error', "No se puede borrar «{$user->name}»: todavía tiene {$gamesCount} juego(s) en su colección. Bórralos o reasígnalos primero.");
        }

        $user->delete();

        return redirect()->route('web.panel.users.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
