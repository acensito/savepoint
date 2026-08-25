<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Formulario para pedir el email donde recibir el enlace de reset.
     */
    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Envía (si el email existe) un enlace de reset de un solo uso.
     * Mismo mensaje de éxito exista o no la cuenta, para no revelar qué
     * emails están registrados.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Si ese email está registrado, te hemos enviado un enlace para restablecer la contraseña.');
    }

    /**
     * Formulario para fijar una nueva contraseña, enlazado desde el email.
     */
    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Consume el token y guarda la nueva contraseña.
     */
    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function ($user) use ($validated) {
                $user->forceFill([
                    'password' => Hash::make($validated['password']),
                ])->save();

                // Ver ProfileController::updatePassword(): un token de la
                // app móvil robado no debe seguir sirviendo tras un reset.
                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
        }

        return redirect()->route('login')->with('success', 'Contraseña restablecida correctamente. Ya puedes acceder.');
    }
}
