<?php

namespace App\Actions\Companies;

use App\Enums\LeadStage;
use App\Models\Company;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Contraparte de CreateLeadWithCompanyAction para edição: atualiza os dados cadastrais da
 * Company e, se houver um lead em andamento (estágio não-terminal), os campos de contato/
 * qualificação desse lead — tudo no mesmo formulário/submit, sem telas separadas. Não toca em
 * `stage`/`won_value`/endereço — isso continua passando por `LeadStageTransitionService` e pela
 * aba Mapa do Kanban, que têm suas próprias regras.
 */
class UpdateLeadWithCompanyAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Company $company, array $data): Company
    {
        return DB::transaction(function () use ($company, $data) {
            $company->update(Arr::only($data, [
                'person_type', 'cnpj', 'cpf', 'razao_social', 'nome_fantasia',
            ]));

            $openLead = $company->leads()
                ->whereNotIn('stage', [LeadStage::Won->value, LeadStage::Lost->value])
                ->first();

            if ($openLead) {
                $openLead->update(Arr::only($data, [
                    'contact_name', 'contact_phone', 'contact_whatsapp', 'contact_email',
                    'interest_level', 'purchase_potential', 'qualification_notes',
                ]));
            }

            return $company->refresh();
        });
    }
}
