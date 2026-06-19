<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function update(User $user, Company $company): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * CPF de sócio é criptografado em repouso e restrito a admin/manager.
     * A leitura deve ser registrada quando o controller/endpoint correspondente for implementado.
     */
    public function viewPartnerCpf(User $user, Company $company): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }
}
