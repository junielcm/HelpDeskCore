<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private Role $role;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['client', 'agent', 'supervisor', 'admin'] as $name) {
            Role::create(['name' => $name]);
        }
        $this->role = Role::where('name', 'client')->first();
        $this->department = Department::create(['name' => 'Soporte Técnico', 'is_active' => true]);
    }

    private function makeUser(string $roleName): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $roleName)->value('id'),
        ]);
    }

    public function test_admin_can_list_all_departments_including_inactive(): void
    {
        $admin = $this->makeUser('admin');
        $inactive = Department::create(['name' => 'Redes', 'is_active' => false]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/departments')
            ->assertOk();

        $response->assertJsonCount(2, 'departments');
        $names = collect($response->json('departments'))->pluck('name');
        $this->assertTrue($names->contains(fn ($name) => $name === 'Soporte Técnico' || $name === 'Redes'));

        $this->assertNotEmpty(collect($response->json('departments'))->first(fn ($d) => $d['id'] === $inactive->id));
    }

    public function test_listing_returns_member_count(): void
    {
        $admin = $this->makeUser('admin');
        $agent = $this->makeUser('agent');
        $agent->update(['department_id' => $this->department->id]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/departments')
            ->assertOk();

        $department = collect($response->json('departments'))->firstWhere('id', $this->department->id);
        $this->assertSame(1, $department['users_count']);
    }

    public function test_supervisor_cannot_access_department_management(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)->getJson('/api/admin/departments')->assertForbidden();

        $this->actingAs($supervisor)
            ->postJson('/api/departments', ['name' => 'Nuevo'])
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->putJson("/api/departments/{$this->department->id}", ['is_active' => false])
            ->assertForbidden();
    }

    public function test_agent_cannot_access_department_management(): void
    {
        $agent = $this->makeUser('agent');

        $this->actingAs($agent)->getJson('/api/admin/departments')->assertForbidden();
    }

    public function test_admin_can_create_department(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)
            ->postJson('/api/departments', [
                'name' => 'Bases de Datos',
                'description' => 'Administración de SGBD',
                'is_active' => true,
            ])
            ->assertCreated();

        $response->assertJsonPath('department.name', 'Bases de Datos');
        $this->assertDatabaseHas('departments', ['name' => 'Bases de Datos', 'is_active' => true]);
    }

    public function test_department_name_must_be_unique(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->postJson('/api/departments', ['name' => 'Soporte Técnico'])
            ->assertStatus(422);
    }

    public function test_admin_can_update_department_info_and_status(): void
    {
        $admin = $this->makeUser('admin');

        $response = $this->actingAs($admin)
            ->putJson("/api/departments/{$this->department->id}", [
                'name' => 'Soporte Corporativo',
                'description' => 'Área renovada',
                'is_active' => false,
            ])
            ->assertOk();

        $response->assertJsonPath('department.name', 'Soporte Corporativo');
        $response->assertJsonPath('department.is_active', false);
        $this->assertDatabaseHas('departments', [
            'id' => $this->department->id,
            'name' => 'Soporte Corporativo',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_reactivate_department(): void
    {
        $admin = $this->makeUser('admin');
        $inactive = Department::create(['name' => 'Redes', 'is_active' => false]);

        $this->actingAs($admin)
            ->putJson("/api/departments/{$inactive->id}", ['is_active' => true])
            ->assertOk();

        $this->assertDatabaseHas('departments', ['id' => $inactive->id, 'is_active' => true]);
    }

    public function test_public_catalog_only_lists_active_departments(): void
    {
        $client = $this->makeUser('client');
        Department::create(['name' => 'Inactivo', 'is_active' => false]);

        $response = $this->actingAs($client)
            ->getJson('/api/departments')
            ->assertOk();

        $response->assertJsonCount(1, 'departments');
        $response->assertJsonPath('departments.0.name', 'Soporte Técnico');
    }
}
