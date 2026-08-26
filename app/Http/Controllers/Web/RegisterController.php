<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\SendsTwoFactorCode;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegisterController extends Controller
{
    use SendsTwoFactorCode;

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
            'password' => ['required', 'string', User::passwordComplexityRule(), 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'is_admin' => false,
            'two_factor_enabled' => true,
        ]);

        // Si el email no llega a salir (SMTP caído, credenciales mal puestas...),
        // la cuenta recién creada quedaría huérfana: activa el 2FA pero sin
        // ningún código nunca enviado, así que jamás se podría completar el
        // login. Se borra y se avisa en vez de dejarla a medias — el usuario
        // puede simplemente volver a intentar el registro cuando se arregle.
        if (! $this->sendTwoFactorCode($user)) {
            $user->delete();

            return back()->withInput($request->except('password', 'password_confirmation'))->with(
                'error',
                'Error. Por favor, inténtalo más tarde y, si el problema persiste, comunícaselo al administrador.'
            );
        }

        event(new Registered($user));

        $request->session()->put('two_factor.user_id', $user->id);
        $request->session()->put('two_factor.remember', false);

        return redirect()->route('two-factor.challenge');
    }

    private function registrationClosedRedirect(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('error', 'El registro está cerrado. Contacta con un administrador para que te dé de alta.');
    }
}
