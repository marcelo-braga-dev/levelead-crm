<?php

namespace App\Actions\Leads;

use App\Enums\LeadStage;
use App\Models\Lead;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Reciclagem de lead perdido (decisão de arquitetura do plano original, nunca automática):
 * cria um **novo** lead em "Lead Novo" para a mesma Company, linkado via `recycled_from_lead_id`.
 * O lead perdido original permanece imutável — nada aqui altera `$lostLead`. Reaproveita
 * `CreateLeadAction` (cópia de contato + fit score inicial), que já recusa criar se a Company
 * tiver um lead em andamento — mesma regra de "nunca duplicar lead aberto" da importação CSV.
 */
class RecycleLeadAction
{
    public function __construct(private readonly CreateLeadAction $createLeadAction) {}

    public function execute(Lead $lostLead): Lead
    {
        if ($lostLead->stage !== LeadStage::Lost) {
            throw new DomainException('Só é possível reciclar um lead que está perdido.');
        }

        return DB::transaction(function () use ($lostLead) {
            $newLead = $this->createLeadAction->execute($lostLead->company);

            $newLead->update([
                'is_recycled' => true,
                'recycled_from_lead_id' => $lostLead->id,
            ]);

            return $newLead->refresh();
        });
    }
}
