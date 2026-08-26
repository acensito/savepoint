<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    /**
     * Actualiza nombre, email y avatar de la cuenta.
     */
    public function updateInfo(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,gif,webp', 'max:2048'],
        ]);

        $avatarPath = $user->avatar_path;
        $previousAvatar = $user->avatar_path;

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store("avatars/$user->id", 'public');
            if ($avatarPath === false) {
                return redirect()->route('web.profile.edit')->with('error', 'No se pudo guardar la imagen de avatar.');
            }
        } elseif ($request->boolean('remove_avatar')) {
            $avatarPath = null;
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'avatar_path' => $avatarPath,
        ]);

        // Si el avatar cambió o se eliminó y existía uno anterior, limpiar el archivo previo.
        if ($previousAvatar && $previousAvatar !== $avatarPath) {
            Storage::disk('public')->delete($previousAvatar);
        }

        return redirect()->route('web.profile.edit')->with('success', 'Datos actualizados correctamente.');
    }

    /**
     * Cambia la contraseña, exigiendo la actual para confirmarla.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Un token de la app móvil robado no debe seguir sirviendo tras
        // esto: cambiar la contraseña es la respuesta estándar ante sospecha
        // de robo, así que tiene que cortar también el acceso ya conseguido
        // con un token filtrado, no solo bloquear logins nuevos. La sesión
        // web (guard 'web', por cookie) no usa esta tabla, así que esto no
        // te desloguea a ti mismo.
        $request->user()->tokens()->delete();

        return redirect()->route('web.profile.edit')->with('success', 'Contraseña actualizada correctamente.');
    }

    /**
     * Borra la cuenta del usuario autenticado con todos sus datos —
     * incluidas las carátulas subidas, no solo las filas de la base de
     * datos. A diferencia de UserController::destroy() (borrado de otra
     * cuenta por un admin, que bloquea si el usuario todavía tiene
     * juegos): aquí el propio dueño de los datos decide borrarlos, así
     * que en vez de bloquear se borra todo en cascada — juegos (incluidos
     * los ya en la papelera), sus carátulas, el avatar y los tokens de la
     * app móvil. games.user_id tiene ON DELETE CASCADE a nivel de base de
     * datos, pero eso solo borraría las filas: los ficheros de carátula
     * en disco quedarían huérfanos para siempre si no se limpian aquí
     * antes de borrar al usuario.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        // Cerrar sesión ANTES de borrar, no después: Auth::logout() refresca
        // el remember_token guardando el modelo del usuario ($user->save()),
        // y si esa fila ya no existe Eloquent lo trata como "no existe
        // todavía" y hace un INSERT en vez de un UPDATE — resucitando la
        // cuenta justo después de borrarla. $user sigue siendo un objeto
        // PHP válido tras esto, así que las operaciones de abajo funcionan
        // igual.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Game::withTrashed()->where('user_id', $user->id)->get()->each(function (Game $game) {
            if ($game->cover) {
                Storage::disk('public')->delete($game->cover);
            }

            $game->forceDelete();
        });

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('login')->with('success', 'Tu cuenta y todos tus datos se han eliminado correctamente.');
    }
}
