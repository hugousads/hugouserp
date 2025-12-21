<?php

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Livewire\Helpdesk\Tickets\Show;
use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class TicketReplyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('helpdesk.view', fn () => true);
        Gate::define('helpdesk.manage', fn () => false);
    }

    protected function createPriority(): TicketPriority
    {
        return TicketPriority::create([
            'name' => 'High',
            'slug' => 'high',
            'color' => '#ff0000',
            'level' => 3,
            'response_time_hours' => 4,
            'resolution_time_hours' => 8,
            'is_active' => true,
        ]);
    }

    protected function createCategory(): TicketCategory
    {
        return TicketCategory::create([
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);
    }

    public function test_viewer_cannot_reply_to_ticket_from_other_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $viewer = User::factory()->create(['branch_id' => $branchA->id]);

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-200001',
            'subject' => 'Other Branch Ticket',
            'description' => 'Issue',
            'status' => 'open',
            'priority_id' => $this->createPriority()->id,
            'category_id' => $this->createCategory()->id,
            'branch_id' => $branchB->id,
        ]);

        try {
            Livewire::actingAs($viewer)
                ->test(Show::class, ['ticket' => $ticket])
                ->set('replyMessage', 'Not allowed')
                ->call('addReply');

            $this->fail('Cross-branch reply should be forbidden.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertDatabaseMissing('ticket_replies', ['ticket_id' => $ticket->id]);
    }

    public function test_assigned_agent_can_add_internal_reply_within_branch(): void
    {
        $branch = Branch::factory()->create();
        $agent = User::factory()->create(['branch_id' => $branch->id]);

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-200002',
            'subject' => 'In-branch Ticket',
            'description' => 'Issue',
            'status' => 'open',
            'priority_id' => $this->createPriority()->id,
            'category_id' => $this->createCategory()->id,
            'branch_id' => $branch->id,
            'assigned_to' => $agent->id,
        ]);

        Livewire::actingAs($agent)
            ->test(Show::class, ['ticket' => $ticket])
            ->set('replyMessage', 'Internal note')
            ->set('isInternal', true)
            ->call('addReply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'is_internal' => true,
        ]);
    }
}
