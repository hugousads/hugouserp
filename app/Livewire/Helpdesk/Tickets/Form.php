<?php

declare(strict_types=1);

namespace App\Livewire\Helpdesk\Tickets;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketSLAPolicy;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    public ?Ticket $ticket = null;
    public bool $isEditing = false;

    // Form fields
    public string $subject = '';
    public string $description = '';
    public string $status = 'new';
    public ?int $priority_id = null;
    public ?int $customer_id = null;
    public ?int $assigned_to = null;
    public ?int $category_id = null;
    public ?int $sla_policy_id = null;
    public ?string $due_date = null;
    public array $tags = [];

    protected function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', 'string', 'in:new,open,pending,resolved,closed'],
            'priority_id' => ['required', 'exists:ticket_priorities,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'category_id' => ['required', 'exists:ticket_categories,id'],
            'sla_policy_id' => ['nullable', 'exists:ticket_sla_policies,id'],
            'due_date' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
        ];
    }

    public function mount(?Ticket $ticket = null): void
    {
        $user = Auth::user();

        if (! $user || ! $user->can('helpdesk.manage')) {
            abort(403);
        }

        if ($ticket && $ticket->exists) {
            $this->isEditing = true;
            $this->ticket = $ticket;
            $this->fill([
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority_id' => $ticket->priority_id,
                'customer_id' => $ticket->customer_id,
                'assigned_to' => $ticket->assigned_to,
                'category_id' => $ticket->category_id,
                'sla_policy_id' => $ticket->sla_policy_id,
                'due_date' => $ticket->due_date?->format('Y-m-d'),
                'tags' => $ticket->tags ?? [],
            ]);
        }
    }

    public function save()
    {
        $this->validate();

        $user = Auth::user();

        $data = [
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'priority_id' => $this->priority_id,
            'customer_id' => $this->customer_id,
            'assigned_to' => $this->assigned_to,
            'category_id' => $this->category_id,
            'sla_policy_id' => $this->sla_policy_id,
            'due_date' => $this->due_date,
            'tags' => $this->tags,
            'branch_id' => $user->branch_id,
        ];

        if ($this->isEditing) {
            $data['updated_by'] = $user->id;
            $this->ticket->update($data);
            session()->flash('message', __('Ticket updated successfully.'));
            $this->redirect(route('app.helpdesk.tickets.index'));
        } else {
            $data['created_by'] = $user->id;
            $ticket = Ticket::create($data);
            session()->flash('message', __('Ticket created successfully.'));
            $this->redirect(route('app.helpdesk.tickets.show', $ticket->id));
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $categories = TicketCategory::orderBy('name')->get();
        $priorities = TicketPriority::orderBy('sort_order')->get();
        $sla_policies = TicketSLAPolicy::where('is_active', true)->orderBy('name')->get();
        $customers = Customer::orderBy('name')->limit(100)->get();
        $agents = User::whereHas('roles', function ($q) {
            $q->where('name', 'agent')->orWhere('name', 'admin');
        })->orderBy('name')->get();

        return view('livewire.helpdesk.tickets.form', [
            'categories' => $categories,
            'priorities' => $priorities,
            'sla_policies' => $sla_policies,
            'customers' => $customers,
            'agents' => $agents,
        ]);
    }
}
