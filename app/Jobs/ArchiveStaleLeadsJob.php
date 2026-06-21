<?php

namespace App\Jobs;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Arquiva leads não-terminais sem interação há `leads.archive_stale_months` (Setting,
 * Hardening pós-MVP H2 — default 12). Arquivado ≠ excluído: só sai do Kanban ativo por padrão
 * (`Lead::query()` no board já filtra `whereNull('archived_at')`).
 *
 * Usa `chunkById()`, não `each()`/`chunk()`: o callback grava `archived_at` na própria coluna
 * usada no `WHERE` deste método, e `chunk()` pagina por OFFSET — a partir do 2º lote, linhas já
 * arquivadas saem do resultado e o OFFSET seguinte pula registros que nunca chegam a ser
 * processados. `chunkById()` pagina por `id > $ultimoId`, imune a esse efeito.
 */
class ArchiveStaleLeadsJob
{
    use Dispatchable;

    private const CHUNK_SIZE = 100;

    public function handle(): int
    {
        $cutoff = now()->subMonths(Setting::get('leads.archive_stale_months', 12));
        $archived = 0;

        Lead::query()
            ->whereNull('archived_at')
            ->whereNotIn('stage', [LeadStage::Won->value, LeadStage::Lost->value])
            ->where(function ($query) use ($cutoff) {
                $query->where('last_interaction_at', '<=', $cutoff)
                    ->orWhere(function ($query) use ($cutoff) {
                        $query->whereNull('last_interaction_at')->where('created_at', '<=', $cutoff);
                    });
            })
            ->chunkById(self::CHUNK_SIZE, function ($leads) use (&$archived) {
                foreach ($leads as $lead) {
                    try {
                        $lead->update(['archived_at' => now()]);
                        $archived++;
                    } catch (Throwable $e) {
                        Log::error('ArchiveStaleLeadsJob: falha ao arquivar lead', [
                            'lead_id' => $lead->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $archived;
    }
}
