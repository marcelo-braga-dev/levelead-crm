<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\SlaAlert;
use App\Models\User;
use App\Notifications\SlaDigest;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Propositalmente NÃO implementa `ShouldQueue` — mesmo motivo dos demais jobs agendados deste
 * projeto (sem worker de fila no Sail). Não envia nada se não houver alerta em aberto (sem
 * digest vazio).
 */
class SendSlaDigestJob
{
    use Dispatchable;

    public function handle(): int
    {
        $alerts = SlaAlert::query()->unresolved()->with('lead.company')->get();

        if ($alerts->isEmpty()) {
            return 0;
        }

        $managers = User::query()->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])->get();

        if ($managers->isEmpty()) {
            return 0;
        }

        try {
            Notification::send($managers, new SlaDigest($alerts));
        } catch (Throwable $e) {
            Log::warning('Falha ao enviar digest de SLA: '.$e->getMessage());

            return 0;
        }

        return $alerts->count();
    }
}
