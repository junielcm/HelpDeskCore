<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de autenticación.
 *
 * Es la puerta de entrada del API: valida credenciales, emite los tokens
 * de acceso (Sanctum) que el SPA reutiliza en cada petición y gestiona el
 * cierre de sesión. También expone el perfil del usuario autenticado.
 */
class AuthController extends Controller
{
    /**
     * Inicia sesión y devuelve un token Bearer junto con los datos del usuario.
     *
     * Ante credenciales incorrectas se responde 401 sin indicar cuál de los
     * dos campos falló, para no facilitar ataques de enumeración.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        // Cargamos rol y departamento porque el frontend los necesita desde
        // el primer momento para decidir a qué secciones tiene acceso.
        $user = Auth::user()->load(['role', 'department']);

        // El token es un acceso autónomo: el frontend lo envía en cada
        // petición mediante el header Authorization: Bearer <token>.
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'role' => $user->role,
            'department' => $user->department,
        ]);
    }

    /**
     * Registra una cuenta nueva de tipo cliente y la deja con sesión iniciada.
     *
     * El rol SIEMPRE se fuerza a client: cualquier intento de escalar a otro
     * rol (agent/supervisor/admin) se ignora en el servidor. El usuario queda
     * activo por defecto (DB default) y, como en el login, se emite un token
     * Sanctum para que el SPA entre directo sin pasos extra.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $clientRole = Role::where('name', 'client')->firstOrFail();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => $clientRole->id,
        ]);

        $user->load(['role', 'department']);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'role' => $user->role,
            'department' => $user->department,
        ], 201);
    }

    /**
     * Cierra la sesión invalidando únicamente el token del dispositivo.
     *
     * Se borra solo el token actual para no romper sesiones iniciadas en
     * otros dispositivos con el mismo usuario.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Devuelve los datos del usuario autenticado.
     *
     * Lo consume el frontend al recargar la página para restaurar el estado
     * de sesión sin pedir las credenciales de nuevo.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'department']);

        return response()->json([
            'user' => $user,
            'role' => $user->role,
            'department' => $user->department,
        ]);
    }
}
