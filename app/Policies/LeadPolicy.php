<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->canAccess($user, $lead);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->canAccess($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Atribuir/transferir consultor é restrito a admin/manager — um consultor não pode
     * repassar o próprio lead para outra pessoa.
     */
    public function assign(User $user, Lead $lead): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    /** Reabrir um lead perdido é uma decisão gerencial — mesmo critério de assign(). */
    public function recycle(User $user, Lead $lead): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    /**
     * Definir/confirmar valor e produto do fechamento é gerencial — consultor pode mover o lead
     * para Ganho, mas não fecha o valor sozinho (mesmo critério de assign()/recycle()).
     */
    public function confirmWonValue(User $user, Lead $lead): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }

    /**
     * Manager vê todos os leads da empresa. Consultor só vê leads próprios ou da própria equipe.
     */
    private function canAccess(User $user, Lead $lead): bool
    {
        if (in_array($user->role, [UserRole::Admin, UserRole::Manager], true)) {
            return true;
        }

        return $lead->assigned_to === $user->id
            || ($user->team_id !== null && $lead->team_id === $user->team_id);
    }
}
