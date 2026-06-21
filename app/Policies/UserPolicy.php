<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Gestão de usuários é admin-only — papel/acesso é dado sensível. `delete()` bloqueia
 * explicitamente a própria conta para evitar auto-lockout (sem checagem de "último admin",
 * sem precedente equivalente em outra Policy do projeto — corte de escopo deliberado).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, User $target): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, User $target): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, User $target): bool
    {
        return $user->role === UserRole::Admin && $user->id !== $target->id;
    }
}
