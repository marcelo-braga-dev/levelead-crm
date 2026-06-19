<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Somente admin/manager acessam a trilha de auditoria — consultor não vê nem para os
 * próprios leads (continua restrito ao histórico único do card, lead_interactions).
 * audit_logs é append-only: update/delete sempre negados, para qualquer papel.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrManager($user);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->isAdminOrManager($user);
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    private function isAdminOrManager(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Manager], true);
    }
}
