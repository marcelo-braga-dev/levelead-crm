<?php

namespace App\Concerns;

use App\DomainServices\AuditContext;
use App\Enums\AuditActorType;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Captura toda escrita (created/updated/deleted) em audit_logs via eventos do Eloquent — não via
 * Events de domínio — para garantir que nenhum caminho de escrita escape da auditoria, incluindo
 * updates feitos por jobs/comandos que não disparam Events de domínio (ex.: upsert em massa numa
 * importação CSV). Em updated, grava apenas as colunas alteradas (diff), não o registro inteiro.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->recordAuditLog('created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $dirty = $model->getDirty();

            if ($dirty === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $dirty);
            $model->recordAuditLog('updated', $original, $dirty);
        });

        static::deleted(function (Model $model) {
            $model->recordAuditLog('deleted', $model->getOriginal(), null);
        });
    }

    protected function recordAuditLog(string $action, ?array $oldValues, ?array $newValues): void
    {
        $actor = AuditContext::current();

        AuditLog::create([
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'actor_label' => $actor['label'],
            'ip_address' => $actor['type'] === AuditActorType::User && ! app()->runningInConsole()
                ? request()?->ip()
                : null,
        ]);
    }
}
