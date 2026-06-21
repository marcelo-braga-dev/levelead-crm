<?php

namespace App\Jobs;

use App\Actions\SlaAlerts\EvaluateSlaForLeadAction;
use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avalia SLA de todos os leads não-arquivados e não-terminais. Propositalmente NÃO implementa
 * `ShouldQueue` — mesmo motivo do DistributeUnassignedLeadsJob (Fase 6): sem worker no Sail,
 * o retorno de `dispatchSync()` numa classe `ShouldQueue` é descartado.
 */
class EvaluateLeadSlaJob
{
    use Dispatchable;

    public function handle(EvaluateSlaForLeadAction $action): int
    {
        $raised = 0;

        Lead::query()
            ->whereNull('archived_at')
            ->whereNotIn('stage', [LeadStage::Won->value, LeadStage::Lost->value])
            ->each(function (Lead $lead) use ($action, &$raised) {
                try {
                    $raised += count($action->execute($lead));
                } catch (Throwable $e) {
                    Log::error('EvaluateLeadSlaJob: falha ao avaliar SLA do lead', [
                        'lead_id' => $lead->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            });

        return $raised;
    }
}
