<?php

declare(strict_types=1);

namespace App\Livewire\Helpdesk;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketSLAPolicy;
use App\Models\User;
use App\Services\HelpdeskService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TicketForm extends Component
{
    use AuthorizesRequests;

    public ?Ticket $ticket = null;

    public bool $isEdit = false;

    public string $subject = '';

    public string $description = '';

    public ?int $customer_id = null;

    public ?int $category_id = null;

    public ?int $priority_id = null;

    public ?int $assigned_to = null;

    public ?int $sla_policy_id = null;

    public string $due_date = '';

    public string $status = 'new';

    public array $tags = [];

    public string $tagInput = '';

    protected HelpdeskService $helpdeskService;

    public function boot(HelpdeskService $helpdeskService): void
    {
        $this->helpdeskService = $helpdeskService;
    }

    public function mount(?Ticket $ticket = null): void
    {
        if ($ticket && $ticket->exists) {
            $this->authorize('helpdesk.edit');
            $this->isEdit = true;
            $this->ticket = $ticket;
            $this->fill($ticket->only([
                'subject',
                'description',
                'customer_id',
                'category_id',
                'priority_id',
                'assigned_to',
                'sla_policy_id',
                'status',
                'tags',
            ]));
            $this->due_date = $ticket->due_date ? $ticket->due_date->format('Y-m-d\TH:i') : '';
        } else {
            $this->authorize('helpdesk.create');
        }
    }

    public function addTag(): void
    {
        if (empty(trim($this->tagInput))) {
            return;
        }

        $tag = trim($this->tagInput);
        if (! in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
        }

        $this->tagInput = '';
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_filter($this->tags, fn ($t) => $t !== $tag));
    }

    public function save(): RedirectResponse
    {
        $data = [
            'subject' => $this->subject,
            'description' => $this->description,
            'customer_id' => $this->customer_id,
            'category_id' => $this->category_id,
            'priority_id' => $this->priority_id,
            'assigned_to' => $this->assigned_to,
            'sla_policy_id' => $this->sla_policy_id,
            'tags' => $this->tags,
        ];

        $data['branch_id'] = auth()->user()?->branch_id ?? 1;

        if (! empty($this->due_date)) {
            $data['due_date'] = $this->due_date;
        }

        if ($this->isEdit) {
            $this->validate([
                'subject' => 'required|string|max:255',
                'description' => 'required|string',
                'status' => 'required|in:new,open,pending,resolved,closed',
                'category_id' => 'required|exists:ticket_categories,id',
                'priority_id' => 'required|exists:ticket_priorities,id',
            ]);

            $data['status'] = $this->status;

            $this->ticket = $this->helpdeskService->updateTicket($this->ticket, $data);

            session()->flash('success', __('Ticket updated successfully'));
        } else {
            $this->validate([
                'subject' => 'required|string|max:255',
                'description' => 'required|string',
                'category_id' => 'required|exists:ticket_categories,id',
                'priority_id' => 'required|exists:ticket_priorities,id',
            ]);

            $this->ticket = $this->helpdeskService->createTicket($data);

            session()->flash('success', __('Ticket created successfully'));
        }

        return redirect()->route('app.helpdesk.tickets.show', $this->ticket->id);
    }

    public function render()
    {
        $customers = Customer::orderBy('name')->get();
        $categories = TicketCategory::active()->ordered()->get();
        $priorities = TicketPriority::active()->ordered()->get();
        $slaPolicies = TicketSLAPolicy::active()->get();
        $agents = User::whereHas('roles', function ($query) {
            $query->where('name', 'like', '%agent%')
                ->orWhere('name', 'like', '%support%')
                ->orWhere('name', 'Super Admin');
        })->get();

        return view('livewire.helpdesk.ticket-form', [
            'customers' => $customers,
            'categories' => $categories,
            'priorities' => $priorities,
            'slaPolicies' => $slaPolicies,
            'agents' => $agents,
        ]);
    }
}
