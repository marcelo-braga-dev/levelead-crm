<?php

namespace App\Jobs;

use App\DomainServices\AuditContext;
use App\DomainServices\LeadDistributionService;
use App\Enums\AuditActorType;
use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Distribui todos os leads sem consultor, não-terminais e não-arquivados. Disparado pelo
 * scheduler (`leads:run-distribution`, ver routes/console.php) e também sob demanda pelo
 * botão "Distribuir leads sem consultor" no Kanban (admin/manager).
 *
 * Propositalmente NÃO implementa `ShouldQueue`: `dispatchSync()` numa classe `ShouldQueue`
 * executa o trabalho corretamente, mas descarta o valor de retorno do `handle()` (a fila
 * trata jobs como fire-and-forget) — e este job precisa retornar quantos leads distribuiu
 * para o Command/Controller que o chama. Sem worker de fila configurado no Sail ainda,
 * não há ganho em ser `ShouldQueue` hoje; revisar se/quando um worker for adicionado.
 *
 * Usa `chunkById()`, não `each()`/`chunk()`: o callback grava `assigned_to` na própria coluna
 * usada no `WHERE` deste método, e `chunk()` pagina por OFFSET — a partir do 2º lote, leads já
 * atribuídos saem do resultado e o OFFSET seguinte pula registros que nunca chegam a ser
 * processados. `chunkById()` pagina por `id > $ultimoId`, imune a esse efeito.
 */
class DistributeUnassignedLeadsJob
{
    use Dispatchable;

    private const CHUNK_SIZE = 100;

    public function handle(LeadDistributionService $service): int
    {
        AuditContext::actingAs(AuditActorType::System, null, self::class);

        $distributed = 0;

        try {
            Lead::query()
                ->whereNull('assigned_to')
                ->whereNull('archived_at')
                ->whereNotIn('stage', [LeadStage::Won->value, LeadStage::Lost->value])
                ->chunkById(self::CHUNK_SIZE, function ($leads) use ($service, &$distributed) {
                    foreach ($leads as $lead) {
                        try {
                            if ($service->assign($lead) !== null) {
                                $distributed++;
                            }
                        } catch (Throwable $e) {
                            Log::error('DistributeUnassignedLeadsJob: falha ao distribuir lead', [
                                'lead_id' => $lead->id,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }
                });
        } finally {
            AuditContext::reset();
        }

        return $distributed;
    }
}
