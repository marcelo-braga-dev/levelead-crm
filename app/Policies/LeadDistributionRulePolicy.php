<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LeadDistributionRule;
use App\Models\User;

/** Mudar regra de distribuição afeta todo o funil — admin-only. */
class LeadDistributionRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, LeadDistributionRule $rule): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, LeadDistributionRule $rule): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, LeadDistributionRule $rule): bool
    {
        return $user->role === UserRole::Admin;
    }
}
