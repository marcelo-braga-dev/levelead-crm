<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LeadScoringRule;
use App\Models\User;

/** Mudar regra de scoring afeta priorização de todo o funil — admin-only. */
class LeadScoringRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, LeadScoringRule $rule): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, LeadScoringRule $rule): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, LeadScoringRule $rule): bool
    {
        return $user->role === UserRole::Admin;
    }
}
