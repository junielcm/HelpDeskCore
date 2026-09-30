<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder principal de la aplicación.
 *
 * Orquesta todos los seeds de datos iniciales: primero los catálogos (roles
 * y departamentos) y después las cuentas de usuario de demostración.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndDepartmentSeeder::class,
            UsersSeeder::class,
        ]);
    }
}
