<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Controlador del perfil del propio usuario autenticado.
 *
 * Permite que cualquier usuario (cliente, agente o gestor) revise y actualice
 * ÚNICAMENTE sus datos seguros (nombre y correo) y cambie su contraseña. El
 * rol, el departamento y el estatus de cuenta nunca son editables por el
 * propio usuario: los administra el personal autorizado.
 */
class ProfileController extends Controller
{
    /**
     * Actualiza los campos editables del perfil autenticado.
     *
     * Solo se aplican los campos presentes (name/email) y se ignora cualquier
     * otro campo que intente enviar el cliente, así que intentos de escalar
     * privilegios con role_id o department_id simplemente no surten efecto.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $payload = [];

        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        $user->update($payload);

        return response()->json(['user' => $user->fresh(['role', 'department'])]);
    }

    /**
     * Cambia la contraseña del propio usuario autenticado.
     *
     * Exige la contraseña actual (regla current_password, que verifica el hash
     * del usuario conectado) y una nueva con confirmación.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => $request->string('password')->toString()]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
        ]);
    }
}
