<?php

namespace App\Policies;

use App\Models\FollowUp;
use App\Models\User;

class FollowUpPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FollowUp $followUp): bool
    {
        return $this->canAccess($user, $followUp);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FollowUp $followUp): bool
    {
        return $this->canAccess($user, $followUp);
    }

    public function delete(User $user): bool
    {
        return false;
    }

    private function canAccess(User $user, FollowUp $followUp): bool
    {
        return (new LeadPolicy)->view($user, $followUp->lead);
    }
}
