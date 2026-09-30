<?php

namespace Tests\Feature;

use App\Exports\TicketsExport;
use App\Models\Department;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['client', 'agent', 'supervisor', 'admin'] as $name) {
            Role::create(['name' => $name]);
        }
    }

    private function makeUser(string $roleName): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $roleName)->value('id'),
        ]);
    }

    private function makeTicket(array $overrides = []): Ticket
    {
        $defaultDepartmentId = Department::create([
            'name' => 'Soporte '.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ])->id;

        return Ticket::create(array_merge([
            'title' => 'No puedo iniciar sesión',
            'description' => 'El sistema no me deja entrar.',
            'priority' => 'medium',
            'status' => 'open',
            'client_id' => $this->makeUser('client')->id,
            'department_id' => $defaultDepartmentId,
        ], $overrides));
    }

    public function test_supervisor_can_download_tickets_excel(): void
    {
        $supervisor = $this->makeUser('supervisor');
        $this->makeTicket();

        $response = $this->actingAs($supervisor)
            ->getJson('/api/reports/tickets/excel')
            ->assertOk();

        $this->assertStringContainsString(
            'filename=reporte-tickets-',
            $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_admin_can_download_tickets_excel(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeTicket();

        $this->actingAs($admin)
            ->getJson('/api/reports/tickets/excel')
            ->assertOk();
    }

    public function test_agent_cannot_download_tickets_excel(): void
    {
        $agent = $this->makeUser('agent');

        $this->actingAs($agent)
            ->getJson('/api/reports/tickets/excel')
            ->assertForbidden();
    }

    public function test_client_cannot_download_tickets_excel(): void
    {
        $client = $this->makeUser('client');

        $this->actingAs($client)
            ->getJson('/api/reports/tickets/excel')
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_download_tickets_excel(): void
    {
        $this->getJson('/api/reports/tickets/excel')
            ->assertUnauthorized();
    }

    public function test_excel_with_invalid_status_is_rejected(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->getJson('/api/reports/tickets/excel?status=invalid')
            ->assertStatus(422);
    }

    public function test_date_to_before_date_from_is_rejected(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->getJson('/api/reports/tickets/excel?date_from=2024-01-10&date_to=2024-01-01')
            ->assertStatus(422);
    }

    public function test_export_applies_status_filter_and_maps_with_headings(): void
    {
        $department = Department::create(['name' => 'Soporte', 'is_active' => true]);
        $openTicket = $this->makeTicket(['status' => 'open', 'department_id' => $department->id, 'title' => 'Ticket abierto']);
        $this->makeTicket(['status' => 'closed', 'department_id' => $department->id, 'title' => 'Ticket cerrado']);

        $export = new TicketsExport(['status' => 'open']);

        $collection = $export->collection();
        $this->assertCount(1, $collection);
        $this->assertSame($openTicket->id, $collection->first()->id);

        $mapped = $export->map($collection->first());
        $this->assertSame('Ticket abierto', $mapped[1]);
        $this->assertSame('Abierto', $mapped[6]);

        $this->assertSame('Estado', $export->headings()[6]);
    }
}
