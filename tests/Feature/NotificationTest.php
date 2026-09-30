<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCreated;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $clientRole;

    private Role $agentRole;

    private Role $supervisorRole;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientRole = Role::create(['name' => 'client', 'description' => 'Solicitante']);
        $this->agentRole = Role::create(['name' => 'agent', 'description' => 'Técnico']);
        $this->supervisorRole = Role::create(['name' => 'supervisor', 'description' => 'Gestión operativa']);
        $this->department = Department::create(['name' => 'Soporte Técnico', 'is_active' => true]);
    }

    private function makeUser(string $role, ?Department $department = null): User
    {
        return User::factory()->create([
            'role_id' => match ($role) {
                'client' => $this->clientRole->id,
                'agent' => $this->agentRole->id,
                'supervisor' => $this->supervisorRole->id,
            },
            'department_id' => $department?->id,
        ]);
    }

    private function makeTicket(?User $client = null, ?Department $department = null, ?User $assigned = null): Ticket
    {
        return Ticket::create([
            'title' => 'No puedo iniciar sesión',
            'description' => 'El sistema no me deja entrar.',
            'status' => 'open',
            'priority' => 'medium',
            'client_id' => $client?->id ?? $this->makeUser('client')->id,
            'department_id' => $department?->id ?? $this->department->id,
            'assigned_to' => $assigned?->id,
        ]);
    }

    /**
     * Crea un ticket en un departamento sin personal, para que la creación no
     * genere notificaciones TicketCreated al departamento y no contamine los
     * contadores de pruebas centradas en el controlador.
     */
    private function makeNeutralTicket(): Ticket
    {
        $neutral = Department::create(['name' => 'SIN PERSONAL', 'is_active' => true]);

        return Ticket::create([
            'title' => 'Ticket neutral',
            'description' => 'Sin personal asignado.',
            'status' => 'open',
            'priority' => 'medium',
            'client_id' => $this->makeUser('client')->id,
            'department_id' => $neutral->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Emisión de notificaciones
    // -----------------------------------------------------------------------

    public function test_creating_ticket_notifies_department_staff(): void
    {
        NotificationFacade::fake();

        $client = $this->makeUser('client');
        $agent = $this->makeUser('agent', $this->department);
        $supervisor = $this->makeUser('supervisor', $this->department);
        $foreignAgent = $this->makeUser('agent', Department::create(['name' => 'Redes', 'is_active' => true]));

        $ticket = Ticket::create([
            'title' => 'Falla en la VPN',
            'description' => 'No puedo conectarme.',
            'priority' => 'high',
            'client_id' => $client->id,
            'department_id' => $this->department->id,
        ]);

        NotificationFacade::assertSentTo($agent, TicketCreated::class);
        NotificationFacade::assertSentTo($supervisor, TicketCreated::class);
        NotificationFacade::assertNotSentTo($foreignAgent, TicketCreated::class);
        NotificationFacade::assertNotSentTo($client, TicketCreated::class);
    }

    public function test_reassigning_ticket_notifies_new_agent(): void
    {
        NotificationFacade::fake();

        $supervisor = $this->makeUser('supervisor', $this->department);
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket();

        $this->actingAs($supervisor)
            ->putJson("/api/tickets/{$ticket->id}", ['assigned_to' => $agent->id])
            ->assertOk();

        NotificationFacade::assertSentTo($agent, TicketAssigned::class);
    }

    public function test_status_change_notifies_client(): void
    {
        NotificationFacade::fake();

        $client = $this->makeUser('client');
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeTicket(client: $client, assigned: $agent);

        $this->actingAs($agent)
            ->putJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertOk();

        NotificationFacade::assertSentTo($client, TicketStatusChanged::class);
    }

    // -----------------------------------------------------------------------
    // Listado y marcado como leída
    // -----------------------------------------------------------------------

    public function test_user_only_lists_own_notifications_with_unread_count(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $otherAgent = $this->makeUser('agent', Department::create(['name' => 'Redes', 'is_active' => true]));
        $ticket = $this->makeNeutralTicket();

        $agent->notify(new TicketAssigned($ticket));
        $otherAgent->notify(new TicketAssigned($ticket));

        $response = $this->actingAs($agent)
            ->getJson('/api/notifications')
            ->assertOk();

        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonCount(1, 'notifications.data');
    }

    public function test_marking_notification_as_read_decrements_unread_count(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeNeutralTicket();

        $agent->notify(new TicketAssigned($ticket));
        $record = $agent->notifications()->first();

        $response = $this->actingAs($agent)
            ->putJson("/api/notifications/{$record->id}/read")
            ->assertOk();

        $response->assertJsonPath('unread_count', 0);
        $this->assertNotNull($record->fresh()->read_at);
    }

    public function test_marking_all_as_read_updates_every_notification(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $ticket = $this->makeNeutralTicket();

        $agent->notify(new TicketAssigned($ticket));
        $agent->notify(new TicketAssigned($ticket));

        $this->actingAs($agent)
            ->putJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $agent->unreadNotifications()->count());
    }

    public function test_marking_other_users_notification_as_read_is_ignored(): void
    {
        $agent = $this->makeUser('agent', $this->department);
        $otherAgent = $this->makeUser('agent', Department::create(['name' => 'Redes', 'is_active' => true]));
        $ticket = $this->makeNeutralTicket();

        $otherAgent->notify(new TicketAssigned($ticket));
        $foreignRecord = $otherAgent->notifications()->first();

        $this->actingAs($agent)
            ->putJson("/api/notifications/{$foreignRecord->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        // La notificación ajena sigue sin leerse.
        $this->assertNull($foreignRecord->fresh()->read_at);
    }
}
