<?php

namespace App\DomainServices;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Reconstrói (somente leitura) o estado de um registro auditável em um ponto no tempo passado,
 * aplicando em ordem cronológica os diffs de audit_logs sobre o snapshot mais antigo (created).
 * Não escreve nada — não existe "restore"/"undo", só exibição.
 */
class AuditLogReconstructor
{
    public function stateAt(Model $entity, Carbon $pointInTime): array
    {
        $logs = AuditLog::query()
            ->where('auditable_type', $entity->getMorphClass())
            ->where('auditable_id', $entity->getKey())
            ->where('created_at', '<=', $pointInTime)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $state = [];

        foreach ($logs as $log) {
            if ($log->action === AuditAction::Created) {
                $state = $log->new_values ?? [];

                continue;
            }

            if ($log->action === AuditAction::Updated) {
                $state = array_merge($state, $log->new_values ?? []);
            }
        }

        return $state;
    }
}
