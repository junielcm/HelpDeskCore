<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Department;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketAuditLog;
use App\Models\TicketRating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
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

    private function makeTicket(
        ?User $client = null,
        ?Department $department = null,
        ?User $assigned = null,
        string $status = 'open',
        string $priority = 'medium',
    ): Ticket {
        return Ticket::create([
            'title' => 'No puedo iniciar sesión',
            'description' => 'El sistema no me deja entrar.',
            'priority' => $priority,
            'status' => $status,
            'client_id' => $client?->id ?? $this->makeUser('client')->id,
            'department_id' => $department?->id ?? $this->department->id,
            'assigned_to' => $assigned?->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // KPIs
    // -----------------------------------------------------------------------

    public function test_client_is_forbidden_from_metrics(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->getJson('/api/dashboard/metrics')
            ->assertForbidden();
    }

    public function test_agent_is_forbidden_from_metrics(): void
    {
        $agent = $this->makeUser('agent', $this->department);

        $this->actingAs($agent)
            ->getJson('/api/dashboard/metrics')
            ->assertForbidden();
    }

    public function test_supervisor_reads_kpis(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);

        $this->makeTicket(status: 'open', priority: 'urgent');
        $this->makeTicket(status: 'in_progress', priority: 'high', assigned: $agent);
        $this->makeTicket(status: 'resolved', priority: 'medium');

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $response->assertJsonPath('metrics.total_tickets', 3);
        $response->assertJsonPath('metrics.open_tickets', 2);
        $response->assertJsonPath('metrics.critical_unassigned', 1);
    }

    public function test_satisfaction_percentage_uses_positive_ratings(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $client = $this->makeUser('client');
        $resolved = $this->makeTicket(client: $client, status: 'resolved');

        TicketRating::create(['ticket_id' => $resolved->id, 'user_id' => $client->id, 'rating' => 1]);
        TicketRating::create(['ticket_id' => $resolved->id, 'user_id' => $client->id, 'rating' => 5]);
        TicketRating::create(['ticket_id' => $resolved->id, 'user_id' => $client->id, 'rating' => 4]);

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $response->assertJsonPath('metrics.satisfaction', 66.7);
        $response->assertJsonPath('metrics.ratings_count', 3);
    }

    public function test_satisfaction_is_null_when_there_are_no_ratings(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $response->assertJsonPath('metrics.satisfaction', null);
    }

    public function test_average_response_time_uses_first_staff_comment(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);
        $client = $this->makeUser('client');

        $ticket = $this->makeTicket(client: $client, department: $this->department, assigned: $agent);
        Ticket::whereKey($ticket->id)->update(['created_at' => now()->subHours(24)]);

        $comment = Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Estamos revisando tu caso.',
            'is_internal' => false,
        ]);
        Comment::whereKey($comment->id)->update(['created_at' => now()->subHours(4)]);

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $this->assertSame(20 * 3600, $response->json('metrics.avg_response_time_seconds'));
    }

    public function test_average_resolution_time_uses_status_changed_audit_log(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $client = $this->makeUser('client');

        $ticket = $this->makeTicket(client: $client, status: 'resolved');
        Ticket::whereKey($ticket->id)->update(['created_at' => now()->subDays(3)]);

        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $supervisor->id,
            'action' => 'status_changed',
            'old_value' => 'open',
            'new_value' => 'resolved',
        ]);
        TicketAuditLog::where('ticket_id', $ticket->id)
            ->where('action', 'status_changed')
            ->update(['created_at' => now()->subDays(1)]);

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $this->assertSame(2 * 24 * 3600, $response->json('metrics.avg_resolution_time_seconds'));
    }

    // -----------------------------------------------------------------------
    // Distribución (gráficos)
    // -----------------------------------------------------------------------

    public function test_distribution_groups_by_department_and_status(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $networking = Department::create(['name' => 'Redes', 'is_active' => true]);

        $this->makeTicket(department: $this->department, status: 'open');
        $this->makeTicket(department: $networking, status: 'resolved');

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/distribution')
            ->assertOk();

        $response->assertJsonPath('distribution.total_tickets', 2);
        $departments = collect($response->json('distribution.by_department'))->pluck('count', 'department');
        $this->assertSame(1, $departments['Soporte Técnico']);
        $this->assertSame(1, $departments['Redes']);
        $response->assertJsonPath('distribution.by_status.open', 1);
        $response->assertJsonPath('distribution.by_status.resolved', 1);
    }

    public function test_agent_is_forbidden_from_distribution(): void
    {
        $agent = $this->makeUser('agent', $this->department);

        $this->actingAs($agent)
            ->getJson('/api/dashboard/distribution')
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Bitácora de auditoría
    // -----------------------------------------------------------------------

    public function test_audit_logs_list_is_filtered_by_user(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);

        $ticket = $this->makeTicket();
        TicketAuditLog::create(['ticket_id' => $ticket->id, 'user_id' => $supervisor->id, 'action' => 'status_changed', 'old_value' => 'open', 'new_value' => 'resolved']);
        TicketAuditLog::create(['ticket_id' => $ticket->id, 'user_id' => $agent->id, 'action' => 'status_changed', 'old_value' => 'resolved', 'new_value' => 'closed']);

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/audit-logs?user_id='.$agent->id)
            ->assertOk();

        $response->assertJsonCount(1, 'audit_logs.data');
        $response->assertJsonPath('audit_logs.data.0.user_id', $agent->id);
    }

    public function test_audit_logs_list_is_filtered_by_action_and_date(): void
    {
        $supervisor = $this->makeUser('supervisor', $this->department);

        $ticket = $this->makeTicket();
        $createdLog = TicketAuditLog::create(['ticket_id' => $ticket->id, 'user_id' => $supervisor->id, 'action' => 'created']);
        $reassignedLog = TicketAuditLog::create(['ticket_id' => $ticket->id, 'user_id' => $supervisor->id, 'action' => 'reassigned']);
        TicketAuditLog::whereKey($createdLog->id)->update(['created_at' => now()->subDays(5)]);
        TicketAuditLog::whereKey($reassignedLog->id)->update(['created_at' => now()]);

        $response = $this->actingAs($supervisor)
            ->getJson('/api/dashboard/audit-logs?action=reassigned&date_from='.now()->subDay()->toDateString().'&date_to='.now()->toDateString())
            ->assertOk();

        $response->assertJsonCount(1, 'audit_logs.data');
        $response->assertJsonPath('audit_logs.data.0.action', 'reassigned');
    }

    public function test_client_is_forbidden_from_audit_logs(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->getJson('/api/dashboard/audit-logs')
            ->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Calificaciones (rating)
    // -----------------------------------------------------------------------

    public function test_client_rates_resolved_own_ticket(): void
    {
        $client = $this->makeUser('client');
        $ticket = $this->makeTicket(client: $client, status: 'resolved');

        $response = $this->actingAs($client)
            ->postJson("/api/tickets/{$ticket->id}/ratings", ['rating' => 5, 'feedback' => 'Excelente atención'])
            ->assertCreated();

        $response->assertJsonPath('rating.rating', 5);
        $this->assertDatabaseHas('ticket_ratings', [
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'rating' => 5,
            'feedback' => 'Excelente atención',
        ]);
    }

    public function test_rating_requires_resolved_or_closed_ticket(): void
    {
        $client = $this->makeUser('client');
        $ticket = $this->makeTicket(client: $client, status: 'open');

        $this->actingAs($client)
            ->postJson("/api/tickets/{$ticket->id}/ratings", ['rating' => 5])
            ->assertStatus(422);

        $this->assertDatabaseCount('ticket_ratings', 0);
    }

    public function test_client_cannot_rate_ticket_belonging_to_another_client(): void
    {
        $client = $this->makeUser('client');
        $otherClient = $this->makeUser('client');
        $ticket = $this->makeTicket(client: $otherClient, status: 'resolved');

        $this->actingAs($client)
            ->postJson("/api/tickets/{$ticket->id}/ratings", ['rating' => 3])
            ->assertForbidden();

        $this->assertDatabaseCount('ticket_ratings', 0);
    }

    public function test_agent_cannot_rate_ticket(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(status: 'resolved');

        $this->actingAs($agent)
            ->postJson("/api/tickets/{$ticket->id}/ratings", ['rating' => 4])
            ->assertForbidden();

        $this->assertDatabaseCount('ticket_ratings', 0);
    }

    public function test_ticket_can_only_be_rated_once(): void
    {
        $client = $this->makeUser('client');
        $ticket = $this->makeTicket(client: $client, status: 'resolved');

        $this->actingAs($client)
            ->postJson("/api/tickets/{$ticket->id}/ratings", ['rating' => 4])
            ->assertCreated();

        $this->actingAs($client)
            ->postJson("/api/tickets/{$ticket->id}/ratings", ['rating' => 1])
            ->assertStatus(409);

        $this->assertDatabaseCount('ticket_ratings', 1);
    }

    public function test_rating_requires_value_between_one_and_five(): void
    {
        $client = $this->makeUser('client');
        $ticket = $this->makeTicket(client: $client, status: 'resolved');

        $this->actingAs($client)
            ->postJson("/api/tickets/{$ticket->id}/ratings", ['rating' => 9])
            ->assertStatus(422);

        $this->assertDatabaseCount('ticket_ratings', 0);
    }
}
