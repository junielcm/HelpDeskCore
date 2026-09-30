<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\SlaRule;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaBreached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaTest extends TestCase
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

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role_id' => match ($role) {
                'client' => $this->clientRole->id,
                'agent' => $this->agentRole->id,
                'supervisor' => $this->supervisorRole->id,
                'admin' => $this->adminRole->id,
            },
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
    }

    private function makeTicket(
        string $status = 'open',
        string $priority = 'high',
        ?User $assigned = null,
        ?string $createdAt = null,
    ): Ticket {
        $ticket = Ticket::create([
            'title' => 'Incidente de soporte',
            'description' => 'Descripción del incidente.',
            'priority' => $priority,
            'status' => $status,
            'client_id' => $this->makeUser('client')->id,
            'department_id' => $this->department->id,
            'assigned_to' => $assigned?->id,
        ]);

        if ($createdAt) {
            Ticket::whereKey($ticket->id)->update(['created_at' => $createdAt]);
        }

        return $ticket;
    }

    private function resolutionDeadlineHours(string $priority): int
    {
        return (int) SlaRule::where('priority', $priority)->value('resolution_hours');
    }

    // -----------------------------------------------------------------------
    // Comando programado de SLA
    // -----------------------------------------------------------------------

    public function test_escalates_high_ticket_and_marks_sla_violated_past_deadline(): void
    {
        $supervisor = $this->makeUser('supervisor');

        Notification::fake();

        $this->makeTicket(status: 'open', priority: 'high', createdAt: now()->subHours(30));

        $this->artisan('tickets:check-slas')->assertSuccessful();

        $ticket = Ticket::first();

        $this->assertTrue($ticket->sla_violated);
        $this->assertSame('urgent', $ticket->priority);

        Notification::assertSentTo($supervisor, SlaBreached::class);
    }

    public function test_fresh_ticket_remains_without_sla_violation(): void
    {
        $this->makeTicket(status: 'open', priority: 'high');

        $this->artisan('tickets:check-slas')->assertSuccessful();

        $ticket = Ticket::first();

        $this->assertFalse($ticket->sla_violated);
        $this->assertSame('high', $ticket->priority);
    }

    public function test_response_deadline_breaches_when_no_first_response(): void
    {
        $supervisor = $this->makeUser('supervisor');

        Notification::fake();

        // priority 'medium' → response_hours = 8. El ticket lleva 20h sin
        // ninguna respuesta del staff: supera el umbral de respuesta incluso
        // sin pasar aún el de resolución (48h).
        $this->makeTicket(status: 'open', priority: 'medium', createdAt: now()->subHours(20));

        $this->artisan('tickets:check-slas')->assertSuccessful();

        $ticket = Ticket::first();

        $this->assertTrue($ticket->sla_violated);
        $this->assertSame('high', $ticket->priority);

        Notification::assertSentTo($supervisor, SlaBreached::class);
    }

    public function test_resolved_ticket_is_not_evaluated(): void
    {
        $this->makeTicket(status: 'resolved', priority: 'high', createdAt: now()->subHours(30));

        $this->artisan('tickets:check-slas')->assertSuccessful();

        $ticket = Ticket::first();

        $this->assertFalse($ticket->sla_violated);
        $this->assertSame('high', $ticket->priority);
    }

    public function test_ticket_with_staff_first_response_in_time_is_not_violated(): void
    {
        $this->makeUser('agent');

        // high → response_hours 4, resolution_hours 24. El ticket se creó hace
        // 10h pero el staff respondió a las 2h; sigue abierto, así que no hay
        // incumplimiento de respuesta ni de resolución.
        $ticket = $this->makeTicket(status: 'in_progress', priority: 'high', createdAt: now()->subHours(10));
        $ticket->update(['first_response_at' => now()->subHours(8)]);

        $this->artisan('tickets:check-slas')->assertSuccessful();

        $this->assertFalse($ticket->fresh()->sla_violated);
    }

    // -----------------------------------------------------------------------
    // API: reglas y estado de SLA
    // -----------------------------------------------------------------------

    public function test_supervisor_reads_sla_rules(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $response = $this->actingAs($supervisor)
            ->getJson('/api/sla/rules')
            ->assertOk();

        $this->assertCount(4, $response->json('rules'));
        $this->assertSame('urgent', $response->json('rules.0.priority'));
    }

    public function test_admin_updates_sla_rule(): void
    {
        $admin = $this->makeUser('admin');
        $rule = SlaRule::where('priority', 'high')->firstOrFail();

        $this->actingAs($admin)
            ->putJson("/api/sla/rules/{$rule->id}", [
                'response_hours' => 2,
                'resolution_hours' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('rule.response_hours', 2)
            ->assertJsonPath('rule.resolution_hours', 12);
    }

    public function test_supervisor_reads_sla_status(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $this->makeTicket(status: 'open', priority: 'high');
        $open = Ticket::first();
        $open->update(['sla_violated' => true]);

        $this->makeTicket(status: 'in_progress', priority: 'low');

        $response = $this->actingAs($supervisor)
            ->getJson('/api/sla/status')
            ->assertOk();

        $this->assertSame(2, $response->json('sla.total_tickets'));
        $this->assertSame(1, $response->json('sla.breached'));
        $this->assertSame(1, $response->json('sla.compliant'));
    }

    public function test_client_is_forbidden_from_sla_rules(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->getJson('/api/sla/rules')
            ->assertForbidden();
    }

    public function test_agent_is_forbidden_from_sla_rules(): void
    {
        $agent = $this->makeUser('agent');

        $this->actingAs($agent)
            ->getJson('/api/sla/rules')
            ->assertForbidden();
    }
}
