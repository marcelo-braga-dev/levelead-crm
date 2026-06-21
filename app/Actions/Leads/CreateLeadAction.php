<?php

namespace App\Actions\Leads;

use App\DomainServices\LeadScoringService;
use App\Enums\LeadStage;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadSource;
use DomainException;

/**
 * Cria um Lead em "Lead Novo" para uma Company, copiando o contato de trabalho a partir do
 * contato principal cadastrado (editável depois sem afetar o cadastro da Company).
 *
 * Decisão fechada com o usuário: nunca cria um lead para uma Company que já tem um lead em
 * andamento (estágio não-terminal) — vale tanto para a importação CSV quanto para criação manual.
 */
class CreateLeadAction
{
    /**
     * @param  array<string, mixed>  $contactOverrides
     * @param  int|null  $leadSourceId  Se omitido, assume a origem "Manual" — quem cria via CSV
     *                                  (ImportCompaniesFromCsvAction) passa a origem "CSV" explicitamente.
     */
    public function execute(Company $company, array $contactOverrides = [], ?int $leadSourceId = null): Lead
    {
        $hasOpenLead = $company->leads()
            ->whereNotIn('stage', [LeadStage::Won->value, LeadStage::Lost->value])
            ->exists();

        if ($hasOpenLead) {
            throw new DomainException('Esta empresa já possui um lead em andamento.');
        }

        $primaryContact = fn (string $type): ?string => $company->contacts()
            ->where('type', $type)
            ->where('is_primary', true)
            ->value('value');

        $lead = Lead::create([
            'company_id' => $company->id,
            'lead_source_id' => $leadSourceId ?? LeadSource::where('name', 'Manual')->value('id'),
            'stage' => LeadStage::New->value,
            'contact_name' => $contactOverrides['contact_name'] ?? $company->nome_fantasia ?? $company->razao_social,
            'contact_phone' => $contactOverrides['contact_phone'] ?? $primaryContact('phone'),
            'contact_whatsapp' => $contactOverrides['contact_whatsapp'] ?? $primaryContact('whatsapp'),
            'contact_email' => $contactOverrides['contact_email'] ?? $primaryContact('email'),
            'stage_entered_at' => now(),
        ]);

        // Fit score depende só de dados firmográficos da Company (já disponíveis na criação);
        // sem isso o lead ficaria com fit_score=0/"Cold" até o próximo RecalculateLeadScoresJob diário.
        app(LeadScoringService::class)->recalculate($lead);

        return $lead->refresh();
    }
}
