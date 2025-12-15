<?php

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected TicketCategory $category;
    protected TicketPriority $priority;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->category = TicketCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);
        $this->priority = TicketPriority::create([
            'name' => 'Medium',
            'slug' => 'medium',
            'level' => 2,
            'color' => '#FFA500',
            'is_active' => true,
        ]);
    }

    protected function createTicket(array $overrides = []): Ticket
    {
        static $counter = 0;
        $counter++;

        return Ticket::create(array_merge([
            'ticket_number' => 'TKT-' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
            'subject' => 'Test Issue',
            'description' => 'Test description',
            'status' => 'new',
            'priority' => 'medium',
            'priority_id' => $this->priority->id,
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
        ], $overrides));
    }

    public function test_can_create_ticket(): void
    {
        $ticket = $this->createTicket();

        $this->assertDatabaseHas('tickets', ['subject' => 'Test Issue']);
    }

    public function test_can_read_ticket(): void
    {
        $ticket = $this->createTicket();

        $found = Ticket::find($ticket->id);
        $this->assertNotNull($found);
    }

    public function test_can_update_ticket(): void
    {
        $ticket = $this->createTicket();

        $ticket->update(['status' => 'resolved']);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'resolved']);
    }

    public function test_can_delete_ticket(): void
    {
        $ticket = $this->createTicket();

        $ticket->delete();
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }
}
