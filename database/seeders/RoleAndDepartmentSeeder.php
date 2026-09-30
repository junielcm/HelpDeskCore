<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed de catálogos base del sistema.
 *
 * Crea los cuatro roles definidos en el modelo de negocio (client, agent,
 * supervisor y admin) y un departamento inicial de pruebas. Usa firstOrCreate
 * para que el seeder sea idempotente y pueda ejecutarse varias veces.
 */
class RoleAndDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        // Roles del sistema. El nombre es el identificador lógico que los
        // controladores usan para decidir permisos.
        $roles = [
            ['name' => 'client', 'description' => 'Usuario final o solicitante de soporte'],
            ['name' => 'agent', 'description' => 'Técnico o especialista encargado de resolver incidencias'],
            ['name' => 'supervisor', 'description' => 'Gestión operativa, reasignación y creación de usuarios base'],
            ['name' => 'admin', 'description' => 'Gobierno total, catálogos y acceso a la bitácora de auditoría'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }

        // Departamento de prueba para poder asignar agentes y enrutar tickets.
        Department::firstOrCreate(
            ['name' => 'Soporte Técnico'],
            ['description' => 'Departamento general de atención a incidencias de TI', 'is_active' => true]
        );
    }
}
