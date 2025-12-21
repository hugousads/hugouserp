<?php

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketReplySoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_reply_soft_delete_sets_deleted_at(): void
    {
        $branch = Branch::factory()->create();
        $priority = TicketPriority::create([
            'name' => 'Normal',
            'slug' => 'normal',
            'color' => '#000000',
            'level' => 1,
            'response_time_hours' => 24,
            'resolution_time_hours' => 48,
            'is_active' => true,
        ]);
        $category = TicketCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-100001',
            'subject' => 'Support Needed',
            'description' => 'Help with setup',
            'status' => 'open',
            'priority_id' => $priority->id,
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'assigned_to' => $user->id,
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'We are looking into this.',
            'is_internal' => false,
        ]);

        $reply->delete();

        $this->assertSoftDeleted('ticket_replies', ['id' => $reply->id]);
        $this->assertTrue(TicketReply::withTrashed()->whereKey($reply->id)->exists());
    }
}
