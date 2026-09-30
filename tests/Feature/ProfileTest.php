<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    private function putProfile(User $user, array $payload): void
    {
        $this->actingAs($user)
            ->putJson('/api/profile', $payload)
            ->assertOk();
    }

    public function test_client_can_update_own_name(): void
    {
        $client = $this->makeUser('client');

        $this->putProfile($client, ['name' => 'María López']);

        $this->assertDatabaseHas('users', ['id' => $client->id, 'name' => 'María López']);
    }

    public function test_client_can_update_own_email(): void
    {
        $client = $this->makeUser('client');

        $response = $this->actingAs($client)
            ->putJson('/api/profile', ['email' => 'maria@correo.com'])
            ->assertOk();

        $response->assertJsonPath('user.email', 'maria@correo.com');
    }

    public function test_profile_ignores_privileged_fields(): void
    {
        $client = $this->makeUser('client');
        $originalRole = $client->role_id;

        $this->putProfile($client, [
            'role_id' => $this->supervisorRole->id,
            'department_id' => $this->department->id,
            'is_active' => false,
            'password' => 'hackeada123',
        ]);

        $client->refresh();

        $this->assertSame($originalRole, $client->role_id);
        $this->assertNull($client->department_id);
        $this->assertTrue($client->is_active);
        $this->assertTrue(Hash::check('password', $client->password));
    }

    public function test_profile_validates_unique_email(): void
    {
        $client = $this->makeUser('client');
        $other = $this->makeUser('client');

        $this->actingAs($client)
            ->putJson('/api/profile', ['email' => $other->email])
            ->assertUnprocessable();
    }

    public function test_client_can_change_own_password(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'nuevaClave2026',
                'password_confirmation' => 'nuevaClave2026',
            ])
            ->assertOk();

        $client->refresh();

        $this->assertTrue(Hash::check('nuevaClave2026', $client->password));
    }

    public function test_password_change_requires_current_password(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->putJson('/api/profile/password', [
                'current_password' => 'incorrecta',
                'password' => 'nuevaClave2026',
                'password_confirmation' => 'nuevaClave2026',
            ])
            ->assertUnprocessable();
    }

    public function test_password_change_requires_confirmation_match(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'nuevaClave2026',
                'password_confirmation' => 'otraClave2026',
            ])
            ->assertUnprocessable();
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->putJson('/api/profile', ['name' => 'Sin sesión'])->assertUnauthorized();

        $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'nuevaClave2026',
            'password_confirmation' => 'nuevaClave2026',
        ])->assertUnauthorized();
    }
}
