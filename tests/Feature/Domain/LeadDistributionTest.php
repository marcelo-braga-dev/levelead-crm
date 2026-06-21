<?php

use App\DomainServices\LeadDistributionService;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Jobs\DistributeUnassignedLeadsJob;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadDistributionRule;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\StatesSeeder;
use Illuminate\Support\Facades\Log;

function createLeadForDistributionTest(?int $stateId = null): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Distribution Test',
        'state_id' => $stateId,
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);
}

function consultant(?int $teamId = null): User
{
    return User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $teamId]);
}

it('distributes via round_robin rotating among all consultants', function () {
    LeadDistributionRule::create(['name' => 'RR', 'strategy' => 'round_robin', 'priority' => 10, 'is_active' => true]);
    $c1 = consultant();
    $c2 = consultant();
    $service = app(LeadDistributionService::class);

    $assignment1 = $service->assign(createLeadForDistributionTest());
    $assignment2 = $service->assign(createLeadForDistributionTest());
    $assignment3 = $service->assign(createLeadForDistributionTest());

    $assignedIds = [$assignment1->user_id, $assignment2->user_id, $assignment3->user_id];
    expect($assignedIds)->toEqual([min($c1->id, $c2->id), max($c1->id, $c2->id), min($c1->id, $c2->id)]);
});

it('restricts the pool to the team for a by_team rule', function () {
    $teamA = Team::create(['name' => 'Time A']);
    $teamB = Team::create(['name' => 'Time B']);
    $inTeamA = consultant($teamA->id);
    consultant($teamB->id); // não deve ser escolhido

    LeadDistributionRule::create(['name' => 'Por equipe', 'strategy' => 'by_team', 'team_id' => $teamA->id, 'priority' => 10, 'is_active' => true]);
    $service = app(LeadDistributionService::class);

    $assignment = $service->assign(createLeadForDistributionTest());

    expect($assignment->user_id)->toBe($inTeamA->id);
});

it('matches by_state only when the company state is exactly the rule state', function () {
    $this->seed(StatesSeeder::class);
    $sp = State::where('uf', 'SP')->first();
    $rj = State::where('uf', 'RJ')->first();
    $spConsultant = consultant();

    LeadDistributionRule::create(['name' => 'SP', 'strategy' => 'by_state', 'state_id' => $sp->id, 'priority' => 10, 'is_active' => true]);
    $service = app(LeadDistributionService::class);

    $leadInRj = createLeadForDistributionTest($rj->id);
    expect($service->assign($leadInRj))->not->toBeNull(); // sem match, cai no fallback global
    expect($leadInRj->fresh()->assigned_to)->toBe($spConsultant->id); // único consultor existe, via fallback

    $leadInSp = createLeadForDistributionTest($sp->id);
    $assignment = $service->assign($leadInSp);
    expect($assignment->user_id)->toBe($spConsultant->id);
    expect($assignment->lead_distribution_rule_id)->not->toBeNull();
});

it('matches by_region across different states in the same macro-region', function () {
    $this->seed(StatesSeeder::class);
    $sp = State::where('uf', 'SP')->first(); // Sudeste
    $mg = State::where('uf', 'MG')->first(); // Sudeste também
    $rs = State::where('uf', 'RS')->first(); // Sul — não deve casar
    $consultant = consultant();

    LeadDistributionRule::create(['name' => 'Sudeste', 'strategy' => 'by_region', 'state_id' => $sp->id, 'priority' => 10, 'is_active' => true]);
    $service = app(LeadDistributionService::class);

    $leadInMg = createLeadForDistributionTest($mg->id);
    $assignment = $service->assign($leadInMg);
    expect($assignment->user_id)->toBe($consultant->id);
    expect($assignment->lead_distribution_rule_id)->not->toBeNull();

    $leadInRs = createLeadForDistributionTest($rs->id);
    $service->assign($leadInRs);
    // não casou pela regra by_region (estados de regiões diferentes), mas o fallback global ainda atribui
    expect(LeadAssignment::where('lead_id', $leadInRs->id)->first()->lead_distribution_rule_id)->toBeNull();
});

it('skips manual rules and never auto-distributes through them', function () {
    LeadDistributionRule::create(['name' => 'Manual', 'strategy' => 'manual', 'priority' => 100, 'is_active' => true]);
    $fallbackConsultant = consultant();
    $service = app(LeadDistributionService::class);

    $assignment = $service->assign(createLeadForDistributionTest());

    expect($assignment->user_id)->toBe($fallbackConsultant->id);
    expect($assignment->lead_distribution_rule_id)->toBeNull();
});

it('prefers the higher priority rule when multiple rules match', function () {
    $team = Team::create(['name' => 'Time Prioridade']);
    $teamConsultant = consultant($team->id);
    consultant(); // elegível só pelo round_robin de prioridade mais baixa

    LeadDistributionRule::create(['name' => 'RR baixa prioridade', 'strategy' => 'round_robin', 'priority' => 1, 'is_active' => true]);
    LeadDistributionRule::create(['name' => 'Equipe alta prioridade', 'strategy' => 'by_team', 'team_id' => $team->id, 'priority' => 50, 'is_active' => true]);
    $service = app(LeadDistributionService::class);

    $assignment = $service->assign(createLeadForDistributionTest());

    expect($assignment->user_id)->toBe($teamConsultant->id);
});

it('returns null when there are no consultants at all', function () {
    $service = app(LeadDistributionService::class);

    expect($service->assign(createLeadForDistributionTest()))->toBeNull();
});

it('distributes only unassigned, non-terminal, non-archived leads via the job', function () {
    consultant();

    $unassigned = createLeadForDistributionTest();
    $alreadyAssigned = createLeadForDistributionTest();
    $alreadyAssigned->update(['assigned_to' => consultant()->id]);
    $wonLead = Lead::create(['company_id' => $unassigned->company_id, 'stage' => LeadStage::Won->value]);
    $archivedLead = createLeadForDistributionTest();
    $archivedLead->update(['archived_at' => now()]);

    $distributed = DistributeUnassignedLeadsJob::dispatchSync();

    expect($distributed)->toBe(1);
    expect($unassigned->fresh()->assigned_to)->not->toBeNull();
    expect($wonLead->fresh()->assigned_to)->toBeNull();
    expect($archivedLead->fresh()->assigned_to)->toBeNull();
});

it('distributes every eligible lead across multiple chunks via the job, none skipped', function () {
    // O job distribui 100 por vez (chunkById) e o próprio callback grava `assigned_to`, a
    // coluna usada no WHERE. Com chunk() por OFFSET isso pularia leads a partir do 2º lote;
    // este teste prova que chunkById() não tem esse problema, com mais leads que o lote.
    consultant();
    $total = 105;

    for ($i = 0; $i < $total; $i++) {
        createLeadForDistributionTest();
    }

    $distributed = DistributeUnassignedLeadsJob::dispatchSync();

    expect($distributed)->toBe($total);
    expect(Lead::whereNull('assigned_to')->count())->toBe(0);
});

it('logs and continues past a lead that fails to distribute instead of aborting the rest', function () {
    Log::spy();
    consultant();

    $broken = createLeadForDistributionTest();
    $healthy = createLeadForDistributionTest();

    Lead::updating(function (Lead $lead) use ($broken) {
        if ($lead->is($broken)) {
            throw new RuntimeException('falha forçada para teste');
        }
    });

    $distributed = DistributeUnassignedLeadsJob::dispatchSync();

    expect($distributed)->toBe(1);
    expect($broken->fresh()->assigned_to)->toBeNull();
    expect($healthy->fresh()->assigned_to)->not->toBeNull();
    Log::shouldHaveReceived('error')->once();
});

it('manually assigns a lead and records the assignment plus unified history', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $toConsultant = consultant();
    $lead = createLeadForDistributionTest();

    $this->actingAs($admin)
        ->post(route('leads.assignment.store', $lead), ['user_id' => $toConsultant->id])
        ->assertSessionDoesntHaveErrors();

    expect($lead->fresh()->assigned_to)->toBe($toConsultant->id);
    $assignment = LeadAssignment::where('lead_id', $lead->id)->first();
    expect($assignment->assigned_by)->toBe($admin->id);
    expect($lead->interactions()->where('type', 'system')->exists())->toBeTrue();
});

it('forbids a consultant from manually assigning a lead', function () {
    $consultant = consultant();
    $lead = createLeadForDistributionTest();

    $this->actingAs($consultant)
        ->post(route('leads.assignment.store', $lead), ['user_id' => $consultant->id])
        ->assertForbidden();
});

it('forbids a consultant from triggering the bulk distribution but allows a manager', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultantUser = consultant();
    createLeadForDistributionTest();

    $this->actingAs($consultantUser)->post(route('leads.distribute'))->assertForbidden();
    $this->actingAs($manager)->post(route('leads.distribute'))->assertSessionDoesntHaveErrors();
});
