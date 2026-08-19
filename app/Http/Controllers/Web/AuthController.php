<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Concerns\ThrottlesLogins;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    use ThrottlesLogins;

    /**
     * Muestra el formulario de acceso.
     */
    public function showLogin(): View
    {
        return view('auth.login');
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

        if (! Auth::attempt($credentials, $remember)) {
            $this->incrementLoginAttempts($request);

            // Un único mensaje genérico: no revelamos si el email existe o no.
            throw ValidationException::withMessages([
                'email' => 'Esas credenciales no coinciden con ninguna cuenta.',
            ]);
        }

        $this->clearLoginAttempts($request);

        // Evita el session fixation: nuevo ID de sesión tras autenticarse.
        $request->session()->regenerate();

        return redirect()->intended(route('web.games.index'));
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
