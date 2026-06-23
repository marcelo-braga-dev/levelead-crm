<?php

namespace App\Actions\Companies;

use App\Actions\Leads\CreateLeadAction;
use App\Models\Company;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Cadastro manual avulso de Lead PF ou PJ — Company e Lead nascem juntos, num único formulário,
 * porque o requisito real do produto é "Lead é a entidade completa desde o início" (não duas
 * telas separadas para cadastrar a empresa e depois o lead). Complementa a importação via CSV
 * (caminho principal para volume, PJ-only) para o caso de um único Lead que não está em nenhuma
 * planilha.
 */
class CreateLeadWithCompanyAction
{
    public function __construct(private readonly CreateLeadAction $createLeadAction) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Company
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create(Arr::only($data, [
                'person_type', 'cnpj', 'cpf', 'razao_social', 'nome_fantasia',
            ]));

            $this->createLeadAction->execute($company, Arr::only($data, [
                'contact_name', 'contact_phone', 'contact_whatsapp', 'contact_email',
                'interest_level', 'purchase_potential', 'qualification_notes',
            ]));

            return $company->refresh();
        });
    }
}
