<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;

/** Catálogo comercial — admin e manager, mesmo nível de risco menor que regras de funil/usuários. */
class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function update(User $user, Product $product): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    public function delete(User $user, Product $product): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }
}
