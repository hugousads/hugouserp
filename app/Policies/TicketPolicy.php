<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function update(User $user, Ticket $ticket): bool
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'super-admin'])) {
            return true;
        }

        if (! $user->can('helpdesk.manage')) {
            return false;
        }

        if ($user->branch_id && $ticket->branch_id && $user->branch_id !== $ticket->branch_id) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }
}
