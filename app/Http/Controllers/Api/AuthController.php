<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Concerns\SendsTwoFactorCode;
use App\Http\Controllers\Concerns\ThrottlesLogins;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use SendsTwoFactorCode, ThrottlesLogins;

    /**
     * Prefijo de las claves de caché que enlazan un login de API a medias
     * (credenciales correctas, 2FA pendiente) con el usuario al que
     * pertenece. Diez minutos, igual que la caducidad del código
     * (User::generateTwoFactorCode()) — pasado eso ya no sirve para nada.
     */
    private const TWO_FACTOR_CACHE_PREFIX = 'api-two-factor-challenge:';

    private const TWO_FACTOR_CACHE_TTL_MINUTES = 10;

    /**
     * Iniciar sesión y emitir Token.
     *
     * Si la cuenta tiene 2FA activo, no emite token todavía: manda el código
     * por email y devuelve un "two_factor_token" de un solo uso que hay que
     * canjear en verifyTwoFactor() junto con el código. Sin esto, el login de
     * la API se saltaba el 2FA por completo (a diferencia del login web,
     * Web\AuthController::login()) y bastaban email+contraseña para entrar.
     */
    public function login(Request $request)
    {
        // 1. Validar que nos envían email y contraseña
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $this->ensureLoginIsNotThrottled($request);

        // 2. Comprobar las credenciales
        if (! Auth::attempt($request->only('email', 'password'))) {
            $this->incrementLoginAttempts($request);

            throw new ApiException(ApiErrorCode::INVALID_CREDENTIALS, 401, 'Credenciales incorrectas');
        }

        $this->clearLoginAttempts($request);

        // 3. Buscar al usuario
        $user = User::where('email', $request->email)->firstOrFail();

        // No dejamos ninguna sesión 'web' colgada: la API es sin estado y
        // solo debe autenticar por token.
        Auth::guard('web')->logout();

        if ($user->two_factor_enabled) {
            return $this->beginTwoFactorChallenge($user);
        }

        return $this->issueTokenResponse($user);
    }

    /**
     * Canjea el "two_factor_token" de un login a medias junto con el código
     * recibido por email para completar el login y emitir el token de acceso.
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'two_factor_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $user = $this->pendingUser($request->string('two_factor_token')->toString());

        if (! $user) {
            throw new ApiException(
                ApiErrorCode::TWO_FACTOR_CHALLENGE_EXPIRED,
                401,
                'La verificación ha caducado o no es válida. Vuelve a iniciar sesión.',
            );
        }

        if (! $user->verifyTwoFactorCode($request->string('code')->trim()->toString())) {
            throw new ApiException(ApiErrorCode::INVALID_TWO_FACTOR_CODE, 401, 'Código incorrecto o caducado.');
        }

        Cache::forget(self::TWO_FACTOR_CACHE_PREFIX.$request->input('two_factor_token'));

        return $this->issueTokenResponse($user);
    }

    /**
     * Genera un código nuevo (invalida el anterior) y lo reenvía, para el
     * mismo desafío de 2FA pendiente.
     */
    public function resendTwoFactor(Request $request)
    {
        $request->validate([
            'two_factor_token' => ['required', 'string'],
        ]);

        $user = $this->pendingUser($request->string('two_factor_token')->toString());

        if (! $user) {
            throw new ApiException(
                ApiErrorCode::TWO_FACTOR_CHALLENGE_EXPIRED,
                401,
                'La verificación ha caducado o no es válida. Vuelve a iniciar sesión.',
            );
        }

        if (! $this->sendTwoFactorCode($user)) {
            throw new ApiException(
                ApiErrorCode::SERVICE_UNAVAILABLE,
                503,
                'Error. Por favor, inténtalo más tarde y, si el problema persiste, comunícaselo al administrador.',
            );
        }

        // Refrescamos el TTL: diez minutos más desde este reenvío.
        Cache::put(
            self::TWO_FACTOR_CACHE_PREFIX.$request->input('two_factor_token'),
            $user->id,
            now()->addMinutes(self::TWO_FACTOR_CACHE_TTL_MINUTES),
        );

        return response()->json([
            'message' => 'Te hemos enviado un código nuevo.',
        ]);
    }

    /**
     * Cerrar sesión y destruir el Token
     */
    public function logout(Request $request)
    {
        // Revocamos el token específico que se ha usado para esta petición
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    private function beginTwoFactorChallenge(User $user)
    {
        if (! $this->sendTwoFactorCode($user)) {
            throw new ApiException(
                ApiErrorCode::SERVICE_UNAVAILABLE,
                503,
                'Error. Por favor, inténtalo más tarde y, si el problema persiste, comunícaselo al administrador.',
            );
        }

        $challengeToken = Str::random(64);

        Cache::put(
            self::TWO_FACTOR_CACHE_PREFIX.$challengeToken,
            $user->id,
            now()->addMinutes(self::TWO_FACTOR_CACHE_TTL_MINUTES),
        );

        return response()->json([
            'message' => 'Te hemos enviado un código de verificación por email.',
            'two_factor_required' => true,
            'two_factor_token' => $challengeToken,
        ]);
    }

    /**
     * Usuario (si lo hay) al que pertenece un "two_factor_token" pendiente.
     * Público y estático para que AppServiceProvider pueda usarlo también al
     * definir los RateLimiter de verify/resend-2fa: el límite de intentos
     * debe ir por usuario, no por token (uno nuevo se emite en cada llamada a
     * login() mientras la cuenta siga sin verificar el código), así que la
     * clave del limiter necesita resolver el mismo user_id que aquí.
     */
    public static function pendingChallengeUserId(mixed $challengeToken): ?int
    {
        if (! is_string($challengeToken) || $challengeToken === '') {
            return null;
        }

        return Cache::get(self::TWO_FACTOR_CACHE_PREFIX.$challengeToken);
    }

    private function pendingUser(string $challengeToken): ?User
    {
        $userId = self::pendingChallengeUserId($challengeToken);

        return $userId ? User::find($userId) : null;
    }

    private function issueTokenResponse(User $user)
    {
        // Creamos un token llamado 'MobileApp' (puedes llamarlo como quieras)
        $token = $user->createToken('MobileApp')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
