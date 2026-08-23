<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    /**
     * Muestra el formulario de registro de nuevo usuario, o manda a /login
     * si un admin lo ha cerrado (ver panel/users, AppSetting).
     */
    public function showRegister(): View|RedirectResponse
    {
        if (! AppSetting::current()->registration_enabled) {
            return $this->registrationClosedRedirect();
        }

        return view('auth.register');
    }

    /**
     * Procesa la solicitud de registro, crea el usuario y lo manda al
     * desafío de 2FA: toda cuenta nueva lo lleva activo de fábrica (ver
     * TwoFactorController), sin elección en el propio formulario — se puede
     * desactivar después desde Ajustes, una vez la cuenta ya existe.
     *
     * Comprueba el ajuste otra vez aquí, no solo en showRegister(): un POST
     * directo (sin pasar por el formulario) tiene que respetar el cierre
     * igual, no solo el enlace/vista.
     */
    public function register(Request $request): RedirectResponse
    {
        if (! AppSetting::current()->registration_enabled) {
            return $this->registrationClosedRedirect();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols()->mixedCase(), 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => false,
            'two_factor_enabled' => true,
        ]);

        event(new Registered($user));

        $request->session()->put('two_factor.user_id', $user->id);
        $request->session()->put('two_factor.remember', false);

        $user->notify(new TwoFactorCodeNotification($user->generateTwoFactorCode()));

        return redirect()->route('two-factor.challenge');
    }

    private function registrationClosedRedirect(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('error', 'El registro está cerrado. Contacta con un administrador para que te dé de alta.');
    }
}
