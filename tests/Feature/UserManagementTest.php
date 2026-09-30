<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    private function makeUser(string $role, ?Department $department = null): User
    {
        return User::factory()->create([
            'role_id' => match ($role) {
                'client' => $this->clientRole->id,
                'agent' => $this->agentRole->id,
                'supervisor' => $this->supervisorRole->id,
                'admin' => $this->adminRole->id,
            },
            'department_id' => $department?->id,
        ]);
    }

    public function test_manager_can_list_users(): void
    {
        $manager = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);

        $response = $this->actingAs($manager)
            ->getJson('/api/users')
            ->assertOk();

        // El listado ordena por nombre, que es un dato aleatorio del factory;
        // se verifica el mismo conjunto de usuarios sin depender del orden.
        $this->assertEqualsCanonicalizing(
            [$agent->id, $manager->id],
            $response->json('users.*.id'),
        );
    }

    public function test_agent_cannot_access_user_management(): void
    {
        $agent = $this->makeUser('agent', $this->department);

        $this->actingAs($agent)
            ->getJson('/api/users')
            ->assertForbidden();

        $this->actingAs($agent)
            ->postJson('/api/users', [])
            ->assertForbidden();
    }

    public function test_supervisor_can_create_agent(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $otherDepartment = Department::create(['name' => 'Redes', 'is_active' => true]);

        $response = $this->actingAs($supervisor)
            ->postJson('/api/users', [
                'name' => 'Nuevo Agente',
                'email' => 'nuevo@empresa.com',
                'password' => 'secreto123',
                'role_id' => $this->agentRole->id,
                'department_id' => $otherDepartment->id,
            ])
            ->assertCreated();

        $response->assertJsonPath('user.role.name', 'agent');
        $this->assertDatabaseHas('users', [
            'name' => 'Nuevo Agente',
            'department_id' => $otherDepartment->id,
        ]);
    }

    public function test_supervisor_cannot_create_supervisor_or_admin(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);

        $this->actingAs($supervisor)
            ->postJson('/api/users', [
                'name' => 'Nuevo Jefe',
                'email' => 'jefe@empresa.com',
                'password' => 'secreto123',
                'role_id' => $this->supervisorRole->id,
                'department_id' => $this->department->id,
            ])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->postJson('/api/users', [
                'name' => 'Nuevo Admin',
                'email' => 'admin2@empresa.com',
                'password' => 'secreto123',
                'role_id' => $this->adminRole->id,
                'department_id' => $this->department->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'jefe@empresa.com',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'admin2@empresa.com',
        ]);
    }

    public function test_admin_can_create_supervisor(): void
    {
        $admin = $this->makeUser('admin', $this->department);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Nuevo Supervisor',
                'email' => 'super2@empresa.com',
                'password' => 'secreto123',
                'role_id' => $this->supervisorRole->id,
                'department_id' => $this->department->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'super2@empresa.com',
            'role_id' => $this->supervisorRole->id,
        ]);
    }

    public function test_client_can_be_created_without_department(): void
    {
        $admin = $this->makeUser('admin', $this->department);

        $this->actingAs($admin)
            ->postJson('/api/users', [
                'name' => 'Nuevo Cliente',
                'email' => 'cliente@empresa.com',
                'password' => 'secreto123',
                'role_id' => $this->clientRole->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'cliente@empresa.com',
            'department_id' => null,
        ]);
    }

    public function test_manager_can_update_user_info_and_status(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);

        $response = $this->actingAs($supervisor)
            ->putJson("/api/users/{$agent->id}", [
                'name' => 'Agente Renombrado',
                'is_active' => false,
            ])
            ->assertOk();

        $response->assertJsonPath('user.name', 'Agente Renombrado');
        $response->assertJsonPath('user.is_active', false);
        $this->assertDatabaseHas('users', [
            'id' => $agent->id,
            'name' => 'Agente Renombrado',
            'is_active' => false,
        ]);
    }

    public function test_manager_can_reset_password_without_changing_email(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);
        $originalEmail = $agent->email;

        $this->actingAs($supervisor)
            ->putJson("/api/users/{$agent->id}", ['password' => 'nueva12345'])
            ->assertOk();

        $agent->refresh();

        $this->assertSame($originalEmail, $agent->email);
        $this->assertTrue(Hash::check('nueva12345', $agent->password));
    }

    public function test_supervisor_cannot_manage_other_supervisors_or_admins(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $otherSupervisor = $this->makeUser('supervisor', $this->department);

        $this->actingAs($supervisor)
            ->putJson("/api/users/{$otherSupervisor->id}", ['is_active' => false])
            ->assertForbidden();
    }

    public function test_supervisor_cannot_upgrade_agent_to_supervisor(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);

        $this->actingAs($supervisor)
            ->putJson("/api/users/{$agent->id}", ['role_id' => $this->supervisorRole->id])
            ->assertForbidden();
    }

    public function test_search_filters_users_by_name_or_email(): void
    {
        $manager = $this->makeUser('supervisor', $this->department);
        $target = $this->makeUser('agent', $this->department);
        $target->update(['name' => 'Zara Única']);

        $response = $this->actingAs($manager)
            ->getJson('/api/users?search=Zara')
            ->assertOk();

        $response->assertJsonCount(1, 'users');
        $response->assertJsonPath('users.0.id', $target->id);
    }

    public function test_roles_catalog_requires_manager(): void
    {
        $agent = $this->makeUser('agent', $this->department);

        $this->actingAs($agent)
            ->getJson('/api/roles')
            ->assertForbidden();

        $manager = $this->makeUser('supervisor', $this->department);

        $this->actingAs($manager)
            ->getJson('/api/roles')
            ->assertOk()
            ->assertJsonCount(4, 'roles');
    }
}
