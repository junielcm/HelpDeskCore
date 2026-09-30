<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $agentRole;

    private Role $supervisorRole;

    private Role $adminRole;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientRole = Role::create(['name' => 'client', 'description' => 'Solicitante']);
        $this->agentRole = Role::create(['name' => 'agent', 'description' => 'Técnico']);
        $this->supervisorRole = Role::create(['name' => 'supervisor', 'description' => 'Gestión operativa']);
        $this->adminRole = Role::create(['name' => 'admin', 'description' => 'Administrador']);
        $this->department = Department::create(['name' => 'Soporte Técnico', 'is_active' => true]);
    }

    public function test_guest_can_register_client_account(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Nuevo Cliente',
            'email' => 'cliente@correo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertCreated();

        $response->assertJsonPath('user.role.name', 'client');
        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('users', [
            'email' => 'cliente@correo.com',
            'role_id' => $this->clientRole->id,
            'department_id' => null,
            'is_active' => true,
        ]);

        $this->assertTrue(Hash::check('secreto123', User::where('email', 'cliente@correo.com')->first()->password));
    }

    public function test_registration_always_forces_client_role(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Intruso',
            'email' => 'intruso@correo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
            'role_id' => $this->adminRole->id,
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'intruso@correo.com',
            'role_id' => $this->clientRole->id,
        ]);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'duplicado@correo.com',
            'role_id' => $this->clientRole->id,
        ]);

        $this->postJson('/api/register', [
            'name' => 'Duplicado',
            'email' => 'duplicado@correo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertUnprocessable();
    }

    public function test_registration_requires_matching_confirmation(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sin Confirmar',
            'email' => 'sinconfirmar@correo.com',
            'password' => 'secreto123',
            'password_confirmation' => 'otraclave123',
        ])->assertUnprocessable();
    }

    public function test_registration_requires_secure_password(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Clave Corta',
            'email' => 'clavecorta@correo.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable();
    }
}
