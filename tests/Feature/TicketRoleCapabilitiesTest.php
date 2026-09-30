<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketRoleCapabilitiesTest extends TestCase
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

    private function makeTicket(?User $client = null, ?Department $department = null, ?User $assigned = null): Ticket
    {
        return Ticket::create([
            'title' => 'No puedo iniciar sesión',
            'description' => 'El sistema no me deja entrar.',
            'priority' => 'medium',
            'client_id' => $client?->id ?? $this->makeUser('client')->id,
            'department_id' => $department?->id ?? $this->department->id,
            'assigned_to' => $assigned?->id,
        ]);
    }

    public function test_agent_can_change_status(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(assigned: $agent);

        $this->actingAs($agent)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'in_progress'])
            ->assertOk();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'status_changed',
            'old_value' => 'open',
            'new_value' => 'in_progress',
        ]);
    }

    public function test_agent_cannot_edit_priority(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($agent)
            ->putJson("/api/tickets/{$ticket->id}", ['priority' => 'high'])
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'priority' => 'medium']);
    }

    public function test_agent_cannot_reassign_ticket(): void
    {
        $agentA = $this->makeUser('agent', $this->department);
        $agentB = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(assigned: $agentA);

        $this->actingAs($agentB)
            ->putJson("/api/tickets/{$ticket->id}", ['assigned_to' => $agentB->id])
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assigned_to' => $agentA->id]);
    }

    public function test_agent_can_take_an_unassigned_ticket_from_their_department(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($agent)
            ->putJson("/api/tickets/{$ticket->id}", ['assigned_to' => $agent->id, 'status' => 'in_progress'])
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => $agent->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_agent_can_resolve_a_ticket_assigned_to_them(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(assigned: $agent);

        $this->actingAs($agent)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertOk();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'resolved']);
    }

    public function test_agent_cannot_resolve_a_ticket_assigned_to_another_agent(): void
    {
        $agentA = $this->makeUser('agent', $this->department);
        $agentB = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(assigned: $agentA);

        $this->actingAs($agentB)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'open']);
    }

    public function test_agent_cannot_close_an_unassigned_ticket(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($agent)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertForbidden();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'open']);
    }

    public function test_agent_cannot_operate_on_ticket_from_other_department(): void
    {
        $otherDepartment = Department::create(['name' => 'Redes', 'is_active' => true]);
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(department: $otherDepartment);

        $this->actingAs($agent)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertForbidden();
    }

    public function test_supervisor_can_edit_priority_and_reassign(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($supervisor)
            ->putJson("/api/tickets/{$ticket->id}", ['priority' => 'urgent', 'assigned_to' => $agent->id])
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'priority' => 'urgent',
            'assigned_to' => $agent->id,
        ]);
        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'reassigned',
            'old_value' => null,
            'new_value' => (string) $agent->id,
        ]);
    }

    public function test_admin_can_return_ticket_to_queue(): void
    {
        $admin = $this->makeUser('admin', $this->department);
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(assigned: $agent);

        $this->actingAs($admin)
            ->putJson("/api/tickets/{$ticket->id}", ['assigned_to' => null])
            ->assertOk();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'assigned_to' => null]);
        $this->assertDatabaseHas('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'reassigned',
            'old_value' => (string) $agent->id,
            'new_value' => null,
        ]);
    }

    public function test_client_cannot_update_ticket(): void
    {
        $client = $this->makeUser('client');
        $ticket = $this->makeTicket(client: $client);

        $this->actingAs($client)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertForbidden();
    }

    public function test_manager_can_list_assignable_agents(): void
    {
        $manager = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);
        $otherDepartment = Department::create(['name' => 'Redes', 'is_active' => true]);
        $otherAgent = $this->makeUser('agent', $otherDepartment);
        $ticket = $this->makeTicket();

        $response = $this->actingAs($manager)
            ->getJson("/api/tickets/{$ticket->id}/agents")
            ->assertOk();

        $response->assertJsonPath('agents.*.id', [$agent->id]);
        $this->assertNotEquals($otherAgent->id, $response->json('agents.0.id'));
    }

    public function test_agent_cannot_list_assignable_agents(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($agent)
            ->getJson("/api/tickets/{$ticket->id}/agents")
            ->assertForbidden();
    }

    public function test_audit_log_is_not_written_without_changes(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($supervisor)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'open'])
            ->assertOk();

        $this->assertDatabaseMissing('ticket_audit_logs', [
            'ticket_id' => $ticket->id,
            'action' => 'status_changed',
        ]);
    }

    public function test_ticket_audit_log_is_written_on_manual_edition(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($supervisor)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertOk();

        $this->assertNotEmpty(TicketAuditLog::where('ticket_id', $ticket->id)->first());
    }
}
