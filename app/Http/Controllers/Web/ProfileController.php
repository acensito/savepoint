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

        if ($request->hasFile('avatar')) {
            // Eliminar el avatar anterior si existe antes de guardar el nuevo.
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }
            $avatarPath = $request->file('avatar')->store("avatars/{$user->id}", 'public');
        } elseif ($request->boolean('remove_avatar')) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }
            $avatarPath = null;
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'avatar_path' => $avatarPath,
        ]);

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

        return redirect()->route('web.profile.edit')->with('success', 'Contraseña actualizada correctamente.');
    }
}
