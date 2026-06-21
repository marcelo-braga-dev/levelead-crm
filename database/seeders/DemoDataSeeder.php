<?php

namespace Database\Seeders;

use App\Actions\FollowUps\CompleteFollowUpAction;
use App\Actions\FollowUps\CreateFollowUpAction;
use App\Actions\Interactions\RegisterInteractionAction;
use App\Actions\Leads\AssignLeadAction;
use App\Actions\Leads\CreateLeadAction;
use App\Actions\Proposals\CreateProposalVersionAction;
use App\Actions\SlaAlerts\EvaluateSlaForLeadAction;
use App\DomainServices\AuditContext;
use App\DomainServices\LeadScoringService;
use App\DomainServices\LeadStageTransitionService;
use App\Enums\AuditActorType;
use App\Enums\InteractionDirection;
use App\Enums\InteractionType;
use App\Enums\LeadStage;
use App\Enums\LossReason;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadDistributionRule;
use App\Models\LeadSource;
use App\Models\OptOut;
use App\Models\Product;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dados de demonstração para teste de usabilidade manual (Kanban, Dashboard, Auditoria, SLA,
 * Lead Scoring, Compliance) — NÃO faz parte do `DatabaseSeeder` padrão de propósito: um deploy
 * real não deve ganhar companies/leads fictícios automaticamente. Rodar explicitamente:
 *
 *   ./vendor/bin/sail artisan db:seed --class=DemoDataSeeder
 *
 * Idempotente o bastante para reexecução não acumular duplicatas óbvias (companies por CNPJ,
 * users por e-mail), mas leads/propostas/interações usam `firstOrCreate`-like guard pelo stage
 * atual — rodar duas vezes sobre um banco já alterado manualmente pode gerar resultados
 * inesperados. Em caso de dúvida, `migrate:fresh --seed` e rodar este seeder uma vez.
 *
 * Usa as mesmas Actions/Services do fluxo real (CreateLeadAction, LeadStageTransitionService,
 * RegisterInteractionAction, etc.) em vez de inserts crus — isso é o que garante que
 * lead_stage_history, audit_logs, scores e sla_alerts saiam consistentes, exatamente como
 * sairiam de um consultor de verdade usando o sistema.
 */
class DemoDataSeeder extends Seeder
{
    private LeadStageTransitionService $stageService;

    private CreateLeadAction $createLead;

    private RegisterInteractionAction $registerInteraction;

    private CreateProposalVersionAction $createProposal;

    private CreateFollowUpAction $createFollowUp;

    private CompleteFollowUpAction $completeFollowUp;

    private AssignLeadAction $assignLead;

    private LeadScoringService $scoring;

    private EvaluateSlaForLeadAction $evaluateSla;

    private User $admin;

    /** @var array<string, User> e-mail => User */
    private array $consultants = [];

    /** @var array<string, Team> nome => Team */
    private array $teams = [];

    /** @var array<string, Product> nome => Product */
    private array $products = [];

    /** @var array<string, int> nome da origem => id */
    private array $leadSources = [];

    public function run(): void
    {
        $this->stageService = app(LeadStageTransitionService::class);
        $this->createLead = app(CreateLeadAction::class);
        $this->registerInteraction = app(RegisterInteractionAction::class);
        $this->createProposal = app(CreateProposalVersionAction::class);
        $this->createFollowUp = app(CreateFollowUpAction::class);
        $this->completeFollowUp = app(CompleteFollowUpAction::class);
        $this->assignLead = app(AssignLeadAction::class);
        $this->scoring = app(LeadScoringService::class);
        $this->evaluateSla = app(EvaluateSlaForLeadAction::class);

        try {
            AuditContext::actingAs(AuditActorType::System, null, 'DemoDataSeeder');

            $this->loadReferenceData();
            $this->seedExtraConsultants();
            $this->seedDistributionRules();

            $leadsToFinalize = [
                $this->seedNimbusTecnologia(),
                $this->seedHorizonteConsultoria(),
                $this->seedComercialEstrela(),
                $this->seedConstrutoraPilar(),
                $this->seedAdvocaciaMarinsSouza(),
                $this->seedSaborECia(),
                $this->seedCursosAvancar(),
                $this->seedOfficePrime(),
                $this->seedTechNova(),
                $this->seedConsultoriaPlenum(),
            ];

            $this->seedMercadinhoSemLead();
            $this->seedOptOuts();

            // Recalcula score + reavalia SLA de cada lead por último, depois de todo backdating
            // de timestamps (alguns leads tiveram created_at/stage_entered_at/last_interaction_at
            // movidos para o passado via DB::table() direto, que não dispara os hooks que
            // recalculariam isso automaticamente).
            foreach ($leadsToFinalize as $lead) {
                $lead->refresh();
                $this->scoring->recalculate($lead);
                $this->evaluateSla->execute($lead);
            }
        } finally {
            AuditContext::reset();
        }

        $this->command?->info('DemoDataSeeder: 10 companies/leads, follow-ups, propostas, interações, opt-outs e alertas de SLA criados.');
    }

    private function loadReferenceData(): void
    {
        $this->admin = User::query()->where('role', UserRole::Admin)->firstOrFail();

        foreach (Team::all() as $team) {
            $this->teams[$team->name] = $team;
        }

        foreach (Product::all() as $product) {
            $this->products[$product->name] = $product;
        }

        foreach (LeadSource::all() as $source) {
            $this->leadSources[$source->name] = $source->id;
        }
    }

    private function seedExtraConsultants(): void
    {
        $extra = [
            ['name' => 'Consultora Sul', 'email' => 'consultora.sul@levelead.com.br', 'team' => 'Comercial Sul'],
            ['name' => 'Consultor Norte 2', 'email' => 'consultor.norte2@levelead.com.br', 'team' => 'Comercial Norte'],
        ];

        foreach ($extra as $data) {
            $user = User::query()->updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'role' => UserRole::Consultant,
                    'team_id' => $this->teams[$data['team']]->id,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $this->consultants[$data['email']] = $user;
        }

        // Inclui também o consultor base (Fase 1) no pool, para round-robin/atribuição manual
        // terem mais de uma opção visível na tela.
        $this->consultants['consultant@levelead.com.br'] = User::query()
            ->where('email', 'consultant@levelead.com.br')
            ->firstOrFail();
    }

    private function seedDistributionRules(): void
    {
        LeadDistributionRule::query()->updateOrCreate(
            ['name' => 'Round-robin global'],
            ['strategy' => 'round_robin', 'team_id' => null, 'state_id' => null, 'product_id' => null, 'priority' => 1, 'is_active' => true],
        );

        LeadDistributionRule::query()->updateOrCreate(
            ['name' => 'Time Comercial Sul prioritário'],
            ['strategy' => 'by_team', 'team_id' => $this->teams['Comercial Sul']->id, 'priority' => 10, 'is_active' => true],
        );
    }

    /** @param array<string, mixed> $attributes */
    private function makeCompany(string $cnpj, array $attributes): Company
    {
        $addressKeys = ['state_id', 'city_id', 'logradouro', 'numero', 'complemento', 'bairro', 'cep'];
        $addressAttributes = array_intersect_key($attributes, array_flip($addressKeys));
        $companyAttributes = array_diff_key($attributes, array_flip($addressKeys));

        $company = Company::query()->updateOrCreate(['cnpj' => $cnpj], $companyAttributes);

        if (array_filter($addressAttributes) !== []) {
            $company->address()->updateOrCreate([], $addressAttributes);
        }

        return $company;
    }

    private function cnaeId(string $code): ?int
    {
        return Cnae::query()->where('code', $code)->value('id');
    }

    /** @return array{0: int|null, 1: int|null} [state_id, city_id] */
    private function location(string $uf): array
    {
        $state = State::query()->where('uf', $uf)->first();
        $city = $state ? City::query()->where('state_id', $state->id)->first() : null;

        return [$state?->id, $city?->id];
    }

    private function addPhoneContact(Company $company, string $phone): void
    {
        $company->contacts()->updateOrCreate(
            ['type' => 'phone', 'value' => $phone],
            ['is_primary' => true],
        );
    }

    /** Avança um lead recém-criado (em "new") por uma sequência de stages, em ordem. */
    private function advance(Lead $lead, array $stages, array $context = []): Lead
    {
        foreach ($stages as $stage) {
            $lead = $this->stageService->transition($lead, $stage, ['changed_by' => $this->admin->id, ...$context]);
        }

        return $lead;
    }

    private function backdate(Lead $lead, array $columns): void
    {
        DB::table('leads')->where('id', $lead->id)->update($columns);
        $lead->refresh();
    }

    // ------------------------------------------------------------------
    // 1. Lead Novo, fit score alto, sem interação ainda — empresa grande de tecnologia.
    // ------------------------------------------------------------------
    private function seedNimbusTecnologia(): Lead
    {
        [$stateId, $cityId] = $this->location('SP');

        $company = $this->makeCompany('11222333000101', [
            'razao_social' => 'Nimbus Tecnologia LTDA',
            'nome_fantasia' => 'Nimbus Tech',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('6202-3/00'),
            'company_size' => 'Grande',
            'tax_regime' => 'real',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(8),
            'estimated_revenue_value' => 8_000_000,
            'employee_count' => 220,
            'data_inicio_atividade' => now()->subYears(10),
        ]);
        $this->addPhoneContact($company, '11988880001');

        return $this->createLead->execute($company, [], $this->leadSources['Landing Page']);
    }

    // ------------------------------------------------------------------
    // 2. Tentando Contato, com 1 ligação registrada e opt-out vinculado à company.
    // ------------------------------------------------------------------
    private function seedHorizonteConsultoria(): Lead
    {
        [$stateId, $cityId] = $this->location('MG');

        $company = $this->makeCompany('11222333000102', [
            'razao_social' => 'Horizonte Consultoria Empresarial LTDA',
            'nome_fantasia' => 'Horizonte Consultoria',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('7020-4/00'),
            'company_size' => 'Medio',
            'tax_regime' => 'presumido',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(6),
            'estimated_revenue_value' => 2_500_000,
            'employee_count' => 60,
            'data_inicio_atividade' => now()->subYears(7),
        ]);
        $this->addPhoneContact($company, '31988880002');

        $lead = $this->createLead->execute($company, [], $this->leadSources['Meta Ads']);
        $lead = $this->advance($lead, [LeadStage::AttemptingContact]);

        $this->assignLead->execute($lead, $this->consultants['consultant@levelead.com.br'], $this->admin);

        $this->registerInteraction->execute($lead, [
            'type' => InteractionType::Call->value,
            'direction' => InteractionDirection::Outbound->value,
            'description' => 'Primeira tentativa de contato, caixa postal.',
            'phone_dialed' => '31988880002',
            'confirmed' => true,
        ], $this->consultants['consultant@levelead.com.br']->id);

        return $lead;
    }

    // ------------------------------------------------------------------
    // 3. Contato Realizado, tentativas de contato esgotadas (SLA attempts_exhausted).
    // ------------------------------------------------------------------
    private function seedComercialEstrela(): Lead
    {
        [$stateId, $cityId] = $this->location('RJ');

        $company = $this->makeCompany('11222333000103', [
            'razao_social' => 'Comercial Estrela Informática LTDA',
            'nome_fantasia' => 'Estrela Informática',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('4751-2/01'),
            'company_size' => 'EPP',
            'tax_regime' => 'simples',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(4),
            'estimated_revenue_value' => 900_000,
            'employee_count' => 18,
            'data_inicio_atividade' => now()->subYears(5),
        ]);
        $this->addPhoneContact($company, '21988880003');

        $lead = $this->createLead->execute($company, [], $this->leadSources['Google Ads']);
        $lead = $this->advance($lead, [LeadStage::AttemptingContact, LeadStage::ContactMade]);

        foreach (['Tentativa 1, sem resposta.', 'Tentativa 2, atendeu mas pediu para ligar depois.'] as $note) {
            $this->registerInteraction->execute($lead, [
                'type' => InteractionType::Call->value,
                'direction' => InteractionDirection::Outbound->value,
                'description' => $note,
                'phone_dialed' => '21988880003',
                'confirmed' => true,
            ], $this->admin->id);
        }

        // Simula as tentativas restantes até o limiar de 7 sem criar 7 linhas de interação reais.
        $this->backdate($lead, ['contact_attempts_count' => 8]);

        return $lead;
    }

    // ------------------------------------------------------------------
    // 4. Qualificado, com nota de qualificação + e-mail de retorno do cliente.
    // ------------------------------------------------------------------
    private function seedConstrutoraPilar(): Lead
    {
        [$stateId, $cityId] = $this->location('BA');

        $company = $this->makeCompany('11222333000104', [
            'razao_social' => 'Construtora Pilar LTDA',
            'nome_fantasia' => 'Construtora Pilar',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('4120-4/00'),
            'company_size' => 'ME',
            'tax_regime' => 'simples',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(2),
            'estimated_revenue_value' => 480_000,
            'employee_count' => 9,
            'data_inicio_atividade' => now()->subYears(3),
        ]);
        $this->addPhoneContact($company, '71988880004');

        $lead = $this->createLead->execute($company, [], $this->leadSources['CSV']);
        $lead = $this->advance($lead, [LeadStage::AttemptingContact, LeadStage::ContactMade, LeadStage::Qualified]);

        $this->assignLead->execute($lead, $this->consultants['consultora.sul@levelead.com.br'], $this->admin);

        $lead->update(['qualification_notes' => 'Orçamento confirmado para o próximo trimestre, decisor é o sócio-administrador.']);

        $this->registerInteraction->execute($lead, [
            'type' => InteractionType::Email->value,
            'direction' => InteractionDirection::Inbound->value,
            'description' => 'Cliente respondeu confirmando interesse e pediu proposta formal.',
            'confirmed' => true,
        ], $this->consultants['consultora.sul@levelead.com.br']->id);

        return $lead;
    }

    // ------------------------------------------------------------------
    // 5. Proposta Enviada, com 2 versões de proposta (v1 superseded, v2 active) + follow-ups.
    // ------------------------------------------------------------------
    private function seedAdvocaciaMarinsSouza(): Lead
    {
        [$stateId, $cityId] = $this->location('SP');

        $company = $this->makeCompany('11222333000105', [
            'razao_social' => 'Marins & Souza Advocacia LTDA',
            'nome_fantasia' => 'Marins & Souza Advocacia',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('6911-7/01'),
            'company_size' => 'ME',
            'tax_regime' => 'simples',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(5),
            'estimated_revenue_value' => 700_000,
            'employee_count' => 12,
            'data_inicio_atividade' => now()->subYears(6),
        ]);
        $this->addPhoneContact($company, '11988880005');

        $lead = $this->createLead->execute($company, [], $this->leadSources['Manual']);
        $lead = $this->advance($lead, [LeadStage::AttemptingContact, LeadStage::ContactMade, LeadStage::Qualified, LeadStage::ProposalSent]);

        $consultant = $this->consultants['consultant@levelead.com.br'];
        $this->assignLead->execute($lead, $consultant, $this->admin);

        $this->registerInteraction->execute($lead, [
            'type' => InteractionType::Visit->value,
            'direction' => InteractionDirection::Outbound->value,
            'description' => 'Visita presencial ao escritório para apresentação inicial.',
            'confirmed' => true,
        ], $consultant->id);

        $this->createProposal->execute($lead, [
            'value' => '3500.00',
            'notes' => 'Proposta inicial — plano essencial.',
            'created_by' => $consultant->id,
        ]);

        $followUp1 = $this->createFollowUp->execute($lead, [
            'scheduled_at' => now()->subDays(2)->toDateTimeString(),
            'notes' => 'Ligar para ajustar escopo da proposta.',
            'created_by' => $consultant->id,
        ]);
        $this->completeFollowUp->execute($followUp1, ['user_id' => $consultant->id]);

        // v2 substitui v1 (que vira superseded) — mesma lead, escopo maior.
        $this->createProposal->execute($lead, [
            'value' => '5200.00',
            'notes' => 'Proposta revisada — inclui consultoria mensal recorrente.',
            'created_by' => $consultant->id,
        ]);

        $this->createFollowUp->execute($lead, [
            'scheduled_at' => now()->addDays(3)->toDateTimeString(),
            'notes' => 'Follow-up para fechamento da proposta revisada.',
            'created_by' => $consultant->id,
        ]);

        return $lead;
    }

    // ------------------------------------------------------------------
    // 6. Negociação há mais de 15 dias (SLA negotiation_15d).
    // ------------------------------------------------------------------
    private function seedSaborECia(): Lead
    {
        [$stateId, $cityId] = $this->location('PR');

        $company = $this->makeCompany('11222333000106', [
            'razao_social' => 'Sabor & Cia Restaurante LTDA',
            'nome_fantasia' => 'Sabor & Cia',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('5611-2/01'),
            'company_size' => 'MEI',
            'tax_regime' => 'mei',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(1),
            'estimated_revenue_value' => 80_000,
            'employee_count' => 2,
            'data_inicio_atividade' => now()->subYears(1),
        ]);
        $this->addPhoneContact($company, '41988880006');

        $lead = $this->createLead->execute($company, [], $this->leadSources['Manual']);
        $lead = $this->advance($lead, [
            LeadStage::AttemptingContact, LeadStage::ContactMade, LeadStage::Qualified,
            LeadStage::ProposalSent, LeadStage::Negotiation,
        ]);

        $consultant = $this->consultants['consultor.norte2@levelead.com.br'];
        $this->assignLead->execute($lead, $consultant, $this->admin);

        $this->registerInteraction->execute($lead, [
            'type' => InteractionType::Whatsapp->value,
            'direction' => InteractionDirection::Outbound->value,
            'description' => 'Negociando desconto para fechamento — cliente pediu prazo para decidir.',
            'phone_dialed' => '41988880006',
            'confirmed' => true,
        ], $consultant->id);

        // Backdate: entrou em negociação há 20 dias (Setting sla.negotiation_days default = 15).
        $this->backdate($lead, [
            'stage_entered_at' => now()->subDays(20),
            'last_interaction_at' => now()->subDays(20),
        ]);

        return $lead;
    }

    // ------------------------------------------------------------------
    // 7. Ganho — fluxo completo até Won, com produto e valor.
    // ------------------------------------------------------------------
    private function seedCursosAvancar(): Lead
    {
        [$stateId, $cityId] = $this->location('RS');

        $company = $this->makeCompany('11222333000107', [
            'razao_social' => 'Cursos Avançar Educação LTDA',
            'nome_fantasia' => 'Cursos Avançar',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('8599-6/04'),
            'company_size' => 'Medio',
            'tax_regime' => 'presumido',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(9),
            'estimated_revenue_value' => 3_200_000,
            'employee_count' => 75,
            'data_inicio_atividade' => now()->subYears(11),
        ]);
        $this->addPhoneContact($company, '51988880007');

        $lead = $this->createLead->execute($company, [], $this->leadSources['API']);
        $lead = $this->advance($lead, [
            LeadStage::AttemptingContact, LeadStage::ContactMade, LeadStage::Qualified, LeadStage::ProposalSent,
        ]);

        $consultant = $this->consultants['consultant@levelead.com.br'];
        $this->assignLead->execute($lead, $consultant, $this->admin);

        $this->registerInteraction->execute($lead, [
            'type' => InteractionType::Call->value,
            'direction' => InteractionDirection::Outbound->value,
            'description' => 'Fechamento confirmado por telefone.',
            'phone_dialed' => '51988880007',
            'confirmed' => true,
        ], $consultant->id);

        $this->createProposal->execute($lead, [
            'value' => '12000.00',
            'notes' => 'Plano Profissional, contrato anual.',
            'created_by' => $consultant->id,
        ]);

        return $this->advance($lead, [LeadStage::Negotiation, LeadStage::Won], [
            'won_value' => '12000.00',
            'won_product_id' => $this->products['Plano Profissional']->id,
        ]);
    }

    // ------------------------------------------------------------------
    // 8. Perdido — empresa baixada (gera aviso de compliance ao registrar ligação manualmente).
    // ------------------------------------------------------------------
    private function seedOfficePrime(): Lead
    {
        [$stateId, $cityId] = $this->location('DF');

        $company = $this->makeCompany('11222333000108', [
            'razao_social' => 'Office Prime Serviços Administrativos LTDA',
            'nome_fantasia' => 'Office Prime',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('8211-3/00'),
            'company_size' => 'EPP',
            'tax_regime' => 'simples',
            'registration_status' => RegistrationStatus::Closed,
            'registration_status_date' => now()->subMonths(4),
            'estimated_revenue_value' => 600_000,
            'employee_count' => 14,
            'data_inicio_atividade' => now()->subYears(4),
        ]);
        $this->addPhoneContact($company, '61988880008');

        $lead = $this->createLead->execute($company, [], $this->leadSources['Meta Ads']);
        $lead = $this->advance($lead, [LeadStage::AttemptingContact, LeadStage::ContactMade]);

        $consultant = $this->consultants['consultora.sul@levelead.com.br'];
        $this->assignLead->execute($lead, $consultant, $this->admin);

        $this->registerInteraction->execute($lead, [
            'type' => InteractionType::Call->value,
            'direction' => InteractionDirection::Outbound->value,
            'description' => 'Empresa confirmou que encerrou as atividades.',
            'phone_dialed' => '61988880008',
            'confirmed' => true,
        ], $consultant->id);

        return $this->stageService->transition($lead, LeadStage::Lost, [
            'changed_by' => $consultant->id,
            'loss_reason' => LossReason::BadTiming->value,
            'loss_notes' => 'Empresa baixada na Receita Federal antes do fechamento.',
        ]);
    }

    // ------------------------------------------------------------------
    // 9. Lead Novo, empresa inapta, sem nenhuma interação há mais de 24h (SLA no_interaction_24h).
    // ------------------------------------------------------------------
    private function seedTechNova(): Lead
    {
        [$stateId, $cityId] = $this->location('SP');

        $company = $this->makeCompany('11222333000109', [
            'razao_social' => 'TechNova Sistemas LTDA',
            'nome_fantasia' => 'TechNova',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('6201-5/01'),
            'company_size' => 'ME',
            'tax_regime' => 'simples',
            'registration_status' => RegistrationStatus::Unfit,
            'registration_status_date' => now()->subMonths(8),
            'estimated_revenue_value' => 350_000,
            'employee_count' => 6,
            'data_inicio_atividade' => now()->subYears(3),
        ]);
        $this->addPhoneContact($company, '11988880009');

        $lead = $this->createLead->execute($company, [], $this->leadSources['Landing Page']);

        $this->backdate($lead, ['created_at' => now()->subDays(2)]);

        return $lead;
    }

    // ------------------------------------------------------------------
    // 10. Proposta Enviada há mais de 7 dias sem retorno (SLA no_return_7d).
    // ------------------------------------------------------------------
    private function seedConsultoriaPlenum(): Lead
    {
        [$stateId, $cityId] = $this->location('MG');

        $company = $this->makeCompany('11222333000110', [
            'razao_social' => 'Consultoria Plenum TI LTDA',
            'nome_fantasia' => 'Plenum TI',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('6204-0/00'),
            'company_size' => 'Grande',
            'tax_regime' => 'real',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(12),
            'estimated_revenue_value' => 9_500_000,
            'employee_count' => 310,
            'data_inicio_atividade' => now()->subYears(14),
        ]);
        $this->addPhoneContact($company, '31988880010');

        $lead = $this->createLead->execute($company, [], $this->leadSources['Google Ads']);
        $lead = $this->advance($lead, [
            LeadStage::AttemptingContact, LeadStage::ContactMade, LeadStage::Qualified, LeadStage::ProposalSent,
        ]);

        $consultant = $this->consultants['consultor.norte2@levelead.com.br'];
        $this->assignLead->execute($lead, $consultant, $this->admin);

        $this->createProposal->execute($lead, [
            'value' => '18000.00',
            'notes' => 'Plano Enterprise, consultoria de TI dedicada.',
            'created_by' => $consultant->id,
        ]);

        // Backdate: proposta enviada e sem nenhum retorno há 9 dias (Setting sla.no_return_days default = 7).
        $this->backdate($lead, [
            'stage_entered_at' => now()->subDays(9),
            'last_interaction_at' => now()->subDays(9),
        ]);

        return $lead;
    }

    // ------------------------------------------------------------------
    // Company sem lead nenhum — para o filtro "Companies sem lead ativo".
    // ------------------------------------------------------------------
    private function seedMercadinhoSemLead(): void
    {
        [$stateId, $cityId] = $this->location('RJ');

        $this->makeCompany('11222333000111', [
            'razao_social' => 'Mercadinho Bom Preço LTDA',
            'nome_fantasia' => 'Mercadinho Bom Preço',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'primary_cnae_id' => $this->cnaeId('4751-2/01'),
            'company_size' => 'EPP',
            'tax_regime' => 'simples',
            'registration_status' => RegistrationStatus::Active,
            'registration_status_date' => now()->subYears(2),
            'estimated_revenue_value' => 1_100_000,
            'employee_count' => 22,
            'data_inicio_atividade' => now()->subYears(3),
        ]);
    }

    // ------------------------------------------------------------------
    // Opt-outs: 1 por company_id (Estrela Informática) — qualquer call/whatsapp nesse lead
    // mostra o aviso de compliance ao tentar registrar manualmente sem `confirmed=true`.
    // ------------------------------------------------------------------
    private function seedOptOuts(): void
    {
        $estrela = Company::query()->where('cnpj', '11222333000103')->first();

        if ($estrela !== null) {
            OptOut::query()->updateOrCreate(
                ['company_id' => $estrela->id, 'phone' => null, 'email' => null],
                [
                    'reason' => 'Solicitado pelo titular por telefone',
                    'requested_at' => now()->subMonths(1),
                    'requested_via' => 'phone',
                    'created_by' => $this->admin->id,
                ],
            );
        }
    }
}
