<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de catálogo de departamentos.
 *
 * Expone a cualquier usuario autenticado la lista de departamentos activos
 * para poder poblar los formularios (p. ej. el modal de creación de tickets).
 */
class DepartmentController extends Controller
{
    /**
     * Lista los departamentos activos, ordenados alfabéticamente.
     */
    public function index(): JsonResponse
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['departments' => $departments]);
    }
}
