<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
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
     */
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        $users = User::withCount('games')->orderBy('name')->get();
        $appSetting = AppSetting::current();

        return view('panel.users.index', compact('users', 'appSetting'));
    }

    /**
     * Activa/desactiva el registro público (/register). Reutiliza la misma
     * autorización que el resto de esta página ('viewAny' de UserPolicy,
     * "es admin"): un solo ajuste de instancia no justifica todavía una
     * policy propia aparte.
     */
    public function updateRegistration(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', User::class);

        AppSetting::current()->update([
            'registration_enabled' => $request->boolean('registration_enabled'),
        ]);

        return redirect()->route('web.panel.users.index')->with('success', 'Ajuste de registro actualizado.');
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
            'password' => 'required|string|min:8|confirmed',
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
            'password' => 'nullable|string|min:8|confirmed',
            'is_admin' => 'boolean',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        // No puedes quitarte el rol admin a ti mismo desde este formulario:
        // sin esto, sería fácil dejarte fuera del propio panel de gestión sin querer.
        if ($user->id !== auth()->id()) {
            $user->is_admin = $request->boolean('is_admin');
        }

        $user->save();

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
