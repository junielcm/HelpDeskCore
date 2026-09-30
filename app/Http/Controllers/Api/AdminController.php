<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Controlador de funciones administrativas.
 *
 * Gestiona la creación y listado de usuarios operativos, así como el alta y
 * mantenimiento de departamentos. Todo el bloque está restringido a los roles
 * supervisor y admin, y con políticas adicionales: un supervisor no puede
 * crear roles de alto nivel (supervisor ni admin).
 */
class AdminController extends Controller
{
    /**
     * Roles que un supervisor tiene permitido crear (nunca alto nivel).
     */
    private const SUPERVISOR_ALLOWED_ROLES = ['client', 'agent'];

    /**
     * Crea una cuenta de usuario operativo. Solo supervisor o admin.
     *
     * El supervisor solo puede crear clientes y agentes; el admin es el único
     * con capacidad de crear supervisores y administradores. El departamento
     * es opcional: los clientes no pertenecen a uno.
     */
    public function storeUser(Request $request): JsonResponse
    {
        $this->authorizeManagement($request->user());

        $data = $this->validateUser($request, null);

        $role = Role::findOrFail($data['role_id']);

        $this->authorizeRoleCreation($request->user(), $role->name);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => $role->id,
            'department_id' => $data['department_id'] ?? null,
        ]);

        $user->load(['role', 'department']);

        return response()->json(['user' => $user], 201);
    }

    /**
     * Actualiza la información o el estatus de un usuario. Solo supervisor o
     * admin.
     *
     * Permite cambiar nombre, correo, rol, departamento y estado (is_active),
     * e incluso restablecer la contraseña. El supervisor no puede promover a
     * otros a roles de alto nivel y no puede modificar a usuarios de mayor
     * jerarquía que él.
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        $this->authorizeManagement($request->user());

        $this->authorizeTargetUser($request->user(), $user);

        $data = $this->validateUser($request, $user->id);

        if (isset($data['role_id'])) {
            $role = Role::findOrFail($data['role_id']);
            $this->authorizeRoleCreation($request->user(), $role->name);
            $data['role_id'] = $role->id;
        }

        // Solo se actualizan los campos presentes en la petición, usando
        // array_key_exists para no descartar valores booleanos como false.
        $payload = [];
        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if (array_key_exists('password', $data)) {
            $payload['password'] = Hash::make($data['password']);
        }

        if (array_key_exists('role_id', $data)) {
            $payload['role_id'] = $data['role_id'];
        }

        if (array_key_exists('department_id', $data)) {
            $payload['department_id'] = $data['department_id'];
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = $data['is_active'];
        }

        if (! $user->update($payload)) {
            abort(422, 'No se pudo actualizar el usuario');
        }

        return response()->json(['user' => $user->fresh(['role', 'department'])]);
    }

    /**
     * Catálogo de roles del sistema para poblar el formulario del panel.
     *
     * Expone todos los roles, aunque el frontend ya se encarga de ocultar
     * los de alto nivel cuando quien gestiona es un supervisor.
     */
    public function roles(): JsonResponse
    {
        $this->authorizeManagement(request()->user());

        $roles = Role::orderBy('name')->get(['id', 'name', 'description']);

        return response()->json(['roles' => $roles]);
    }

    /**
     * Lista los usuarios del sistema con sus roles y departamentos.
     *
     * Admite filtros opcionales por departamento y por rol para facilitar
     * la búsqueda desde el panel administrativo.
     */
    public function indexUsers(Request $request): JsonResponse
    {
        $this->authorizeManagement($request->user());

        $search = $request->string('search')->trim()->toString();

        $users = User::with(['role', 'department'])
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->when($request->filled('role_id'), fn ($query) => $query->where('role_id', $request->integer('role_id')))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")))
            ->orderBy('name')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Restringe el acceso a las funciones administrativas (supervisor y admin).
     */
    private function authorizeManagement($user): void
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        abort_unless(in_array($user->role->name, ['supervisor', 'admin']), 403);
    }

    /**
     * Impide que un supervisor asigne roles de alto nivel (supervisor/admin).
     */
    private function authorizeRoleCreation($user, string $roleName): void
    {
        if ($user->role->name === 'supervisor' && ! in_array($roleName, self::SUPERVISOR_ALLOWED_ROLES)) {
            abort(403, 'Un supervisor solo puede crear clientes o agentes');
        }
    }

    /**
     * Valida los campos de un usuario (los mismos en creación y edición).
     *
     * En edición, la unicidad del correo ignora el propio usuario y los
     * campos son opcionales (solo se actualizan los que viajan en la petición).
     */
    private function validateUser(Request $request, ?int $ignoreUserId): array
    {
        $uniqueEmail = 'unique:users,email'.($ignoreUserId ? ",{$ignoreUserId}" : '');

        return $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', $uniqueEmail],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'role_id' => ['sometimes', 'required', 'exists:roles,id'],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * Impide que un supervisor administre a usuarios de mayor o igual
     * jerarquía que él (supervisor o admin), evitando que se edite a sí
     * mismo o a otros gestores.
     */
    private function authorizeTargetUser($actor, User $target): void
    {
        if ($actor->role->name !== 'supervisor') {
            return;
        }

        if (! $target->relationLoaded('role')) {
            $target->load('role');
        }

        if (in_array($target->role->name, ['supervisor', 'admin'])) {
            abort(403, 'Un supervisor no puede gestionar a otros gestores');
        }
    }
}
