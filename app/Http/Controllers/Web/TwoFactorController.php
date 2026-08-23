<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Desafío de 2FA compartido entre AuthController::login() y
 * RegisterController::register(): ambos, si la cuenta tiene 2FA activo,
 * dejan al usuario "a medias" (credenciales/alta correctas pero sin sesión
 * real todavía) guardando en sesión `two_factor.user_id`/`remember` y
 * redirigiendo aquí.
 */
class TwoFactorController extends Controller
{
    /**
     * Muestra el formulario para introducir el código, o manda de vuelta al
     * login si no hay ningún desafío pendiente en la sesión (acceso directo
     * a la URL sin haber pasado por login/registro).
     */
    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', ['email' => $this->maskEmail($user->email)]);
    }

    /**
     * Verifica el código y, si es correcto, completa el login que login()/
     * register() dejaron pendiente.
     */
    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $user->verifyTwoFactorCode($request->string('code')->trim()->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Código incorrecto o caducado.',
            ]);
        }

        $remember = (bool) $request->session()->get('two_factor.remember', false);

        $request->session()->forget(['two_factor.user_id', 'two_factor.remember']);

        Auth::login($user, $remember);

        // Evita el session fixation: nuevo ID de sesión tras autenticarse.
        $request->session()->regenerate();

        if ($request->boolean('trust_device')) {
            $token = TwoFactorTrustedDevice::issueFor($user, $request->ip(), $request->userAgent());

            Cookie::queue(Cookie::make(
                TwoFactorTrustedDevice::COOKIE_NAME,
                $token,
                60 * 24 * 30, // 30 días, en minutos
                httpOnly: true,
            ));
        }

        return redirect()->intended(route('web.games.index'));
    }

    /**
     * Genera un código nuevo (invalida el anterior, ver
     * User::generateTwoFactorCode) y lo reenvía.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $user->notify(new TwoFactorCodeNotification($user->generateTwoFactorCode()));

        return back()->with('success', 'Te hemos enviado un código nuevo.');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('two_factor.user_id');

        return $userId ? User::find($userId) : null;
    }

    /**
     * "ana@dominio.com" -> "a**@dominio.com", solo para que el usuario sepa
     * a qué buzón mirar sin reimprimir el email entero.
     */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        $masked = mb_substr($local, 0, 1).str_repeat('*', max(mb_strlen($local) - 1, 1));

        return $masked.'@'.$domain;
    }
}
