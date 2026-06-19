<?php

namespace App\Policies;

use App\Models\Proposal;
use App\Models\User;

class ProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Proposal $proposal): bool
    {
        return $this->canAccess($user, $proposal);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Proposal $proposal): bool
    {
        return $this->canAccess($user, $proposal);
    }

    /**
     * Proposta é versionada (nova proposta = nova linha) — nunca apagada diretamente.
     */
    public function delete(User $user): bool
    {
        return false;
    }

    private function canAccess(User $user, Proposal $proposal): bool
    {
        return (new LeadPolicy)->view($user, $proposal->lead);
    }
}
