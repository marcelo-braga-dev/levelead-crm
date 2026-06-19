<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Bloqueia update/delete em tabelas append-only (lead_stage_history, lead_interactions).
 * Registrada explicitamente via Gate::policy() em AppServiceProvider para cada model
 * append-only, já que esses models não seguem a convenção de nome Model->ModelPolicy.
 */
class AppendOnlyPolicy
{
    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
