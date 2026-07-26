<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y emitir Token
     */
    public function login(Request $request)
    {
        // 1. Validar que nos envían email y contraseña
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Comprobar las credenciales
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // 3. Buscar al usuario y generar su llave
        $user = User::where('email', $request->email)->firstOrFail();
        
        // Creamos un token llamado 'MobileApp' (puedes llamarlo como quieras)
        $token = $user->createToken('MobileApp')->plainTextToken;

        // 4. Devolver el token a la aplicación
        return response()->json([
            'message' => 'Login exitoso',
            'access_token' => $token,
            'token_type' => 'Bearer',
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
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}