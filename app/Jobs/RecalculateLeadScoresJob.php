<?php

namespace App\Jobs;

use App\DomainServices\LeadScoringService;
use App\Models\Lead;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recalcula fit/intent/total score de todos os leads não-arquivados. Cobre o decaimento por
 * inatividade do intent score (que só se move com o tempo, sem nenhum evento disparar
 * recálculo) — o recálculo incremental nas Actions (registrar interação, criar proposta,
 * concluir follow-up) cobre o resto.
 *
 * Propositalmente NÃO implementa `ShouldQueue` — mesmo motivo do DistributeUnassignedLeadsJob
 * (Fase 6): `dispatchSync()` numa classe `ShouldQueue` descarta o valor de retorno do
 * `handle()`, e o Command precisa do total recalculado para reportar ao operador.
 */
class RecalculateLeadScoresJob
{
    use Dispatchable;

    public function handle(LeadScoringService $service): int
    {
        $recalculated = 0;

        Lead::query()
            ->whereNull('archived_at')
            ->each(function (Lead $lead) use ($service, &$recalculated) {
                try {
                    $service->recalculate($lead);
                    $recalculated++;
                } catch (Throwable $e) {
                    Log::error('RecalculateLeadScoresJob: falha ao recalcular score do lead', [
                        'lead_id' => $lead->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            });

        return $recalculated;
    }
}
