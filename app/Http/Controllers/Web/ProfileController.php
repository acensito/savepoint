<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
