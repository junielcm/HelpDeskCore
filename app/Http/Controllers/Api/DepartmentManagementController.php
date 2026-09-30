<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de gestión de departamentos del panel de administración.
 *
 * Permite al rol de administrador listar todos los departamentos (activos e
 * inactivos), crear nuevas áreas técnicas y actualizar su información o
 * estatus operativo. Todo el bloque está restringido exclusivamente al rol
 * admin, a diferencia del catálogo público de departamentos (que solo muestra
 * los activos y está disponible para cualquier usuario autenticado).
 */
class DepartmentManagementController extends Controller
{
    /**
     * Lista todos los departamentos (activos e inactivos). Solo administrador.
     */
    public function index(): JsonResponse
    {
        $this->authorizeAdmin(request()->user());

        $departments = Department::withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json(['departments' => $departments]);
    }

    /**
     * Crea un nuevo departamento de atención. Solo administrador.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $department = Department::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['department' => $department->fresh()], 201);
    }

    /**
     * Actualiza un departamento existente. Solo administrador.
     *
     * La regla de unicidad en el nombre ignora el propio registro para poder
     * guardar sin que la validación falle contra sí mismo.
     */
    public function update(Request $request, Department $department): JsonResponse
    {
        $this->authorizeAdmin($request->user());

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:departments,name,'.$department->id],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $department->update($data);

        return response()->json(['department' => $department->fresh()]);
    }

    /**
     * Restringe el acceso al rol de administrador.
     */
    private function authorizeAdmin($user): void
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        abort_unless($user->role->name === 'admin', 403);
    }
}
