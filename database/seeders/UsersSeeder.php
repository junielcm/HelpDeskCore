<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed de usuarios de demostración.
 *
 * Puebla el sistema con cuentas de cada rol (client, agent, supervisor y
 * admin) para poder probar el flujo completo de tickets desde el primer día.
 * Usa firstOrCreate por email para ser idempotente.
 */
class UsersSeeder extends Seeder
{
    /**
     * Contraseña común para todas las cuentas de demostración.
     */
    private const DEFAULT_PASSWORD = '123456';

    public function run(): void
    {
        // Catálogos necesarios para asociar cada usuario a su rol y departamento.
        $adminRole = Role::where('name', 'admin')->first();
        $supervisorRole = Role::where('name', 'supervisor')->first();
        $agentRole = Role::where('name', 'agent')->first();
        $clientRole = Role::where('name', 'client')->first();

        $supportDepartment = Department::where('name', 'Soporte Técnico')->first();

        $users = [
            // --- Personal de soporte ---
            ['name' => 'Administrador del Sistema', 'email' => 'admin@helpdesk.com', 'role' => $adminRole, 'department' => $supportDepartment],
            ['name' => 'Supervisora de Soporte', 'email' => 'supervisor@helpdesk.com', 'role' => $supervisorRole, 'department' => $supportDepartment],
            ['name' => 'Agente Luciana', 'email' => 'agente.luciana@helpdesk.com', 'role' => $agentRole, 'department' => $supportDepartment],
            ['name' => 'Agente Miguel', 'email' => 'agente.miguel@helpdesk.com', 'role' => $agentRole, 'department' => $supportDepartment],

            // --- Clientes ---
            ['name' => 'Cliente Andrea', 'email' => 'cliente.andrea@helpdesk.com', 'role' => $clientRole, 'department' => null],
            ['name' => 'Cliente Bruno', 'email' => 'cliente.bruno@helpdesk.com', 'role' => $clientRole, 'department' => null],
        ];

        foreach ($users as $user) {
            // El password se pasa en claro; el cast 'hashed' del modelo lo cifra
            // automáticamente antes de persistirlo.
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => self::DEFAULT_PASSWORD,
                    'role_id' => $user['role']?->id,
                    'department_id' => $user['department']?->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
