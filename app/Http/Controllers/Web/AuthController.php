<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ThrottlesLogins;
use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    use ThrottlesLogins;

    /**
     * Muestra el formulario de acceso. Pasa si el registro público está
     * abierto para que la vista decida si enseña el enlace "Regístrate".
     */
    public function showLogin(): View
    {
        return view('auth.login', ['registrationEnabled' => AppSetting::current()->registration_enabled]);
    }

    /**
     * Inicia sesión usando el guard 'web' (cookie de sesión, no token).
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureLoginIsNotThrottled($request);

        $remember = $request->boolean('remember');

        // Auth::validate() comprueba las credenciales sin autenticar a nadie
        // (ni siquiera para esta petición, a diferencia de Auth::once()): si
        // hace falta 2FA, no queremos que el guard llegue a considerar al
        // usuario logueado en ningún momento antes de que lo verifique.
        if (! Auth::validate($credentials)) {
            $this->incrementLoginAttempts($request);

            // Un único mensaje genérico: no revelamos si el email existe o no.
            throw ValidationException::withMessages([
                'email' => 'Esas credenciales no coinciden con ninguna cuenta.',
            ]);
        }

        $this->clearLoginAttempts($request);

        $user = User::where('email', $credentials['email'])->firstOrFail();

        $trustedDevice = TwoFactorTrustedDevice::isTrusted($user, $request->cookie(TwoFactorTrustedDevice::COOKIE_NAME));

        if (! $user->two_factor_enabled || $trustedDevice) {
            Auth::login($user, $remember);

            // Evita el session fixation: nuevo ID de sesión tras autenticarse.
            $request->session()->regenerate();

            return redirect()->intended(route('web.games.index'));
        }

        $request->session()->put('two_factor.user_id', $user->id);
        $request->session()->put('two_factor.remember', $remember);

        $user->notify(new TwoFactorCodeNotification($user->generateTwoFactorCode()));

        return redirect()->route('two-factor.challenge');
    }

    /**
     * Cierra la sesión e invalida la cookie.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
