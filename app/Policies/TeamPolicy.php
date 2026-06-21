<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;

/** Estrutura organizacional — admin-only, mesmo nível de sensibilidade que UserPolicy. */
class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Team $team): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Team $team): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->role === UserRole::Admin;
    }
}
