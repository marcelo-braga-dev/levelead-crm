<?php

use App\Actions\Leads\CreateLeadAction;
use App\DomainServices\LeadScoringService;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Jobs\RecalculateLeadScoresJob;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadScoringRule;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

afterEach(function () {
    Carbon::setTestNow();
});

function createLeadForScoringTest(array $companyAttributes = []): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Scoring Test',
        ...$companyAttributes,
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);
}

it('sums weights of matching active rules for the fit score', function () {
    LeadScoringRule::create(['criterion' => 'company_size', 'criterion_value' => 'ME', 'score_weight' => 20, 'is_active' => true]);
    LeadScoringRule::create(['criterion' => 'tax_regime', 'criterion_value' => 'simples', 'score_weight' => 15, 'is_active' => true]);
    LeadScoringRule::create(['criterion' => 'company_size', 'criterion_value' => 'Grande', 'score_weight' => 50, 'is_active' => true]); // não casa

    $lead = createLeadForScoringTest(['company_size' => 'ME', 'tax_regime' => 'simples']);

    expect(app(LeadScoringService::class)->calculateFitScore($lead))->toBe(35);
});

it('ignores inactive rules for the fit score', function () {
    LeadScoringRule::create(['criterion' => 'company_size', 'criterion_value' => 'ME', 'score_weight' => 99, 'is_active' => false]);

    $lead = createLeadForScoringTest(['company_size' => 'ME']);

    expect(app(LeadScoringService::class)->calculateFitScore($lead))->toBe(0);
});

it('matches the state criterion via the company UF', function () {
    $sp = State::create(['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35]);
    LeadScoringRule::create(['criterion' => 'state', 'criterion_value' => 'SP', 'score_weight' => 25, 'is_active' => true]);

    $lead = createLeadForScoringTest(['state_id' => $sp->id]);

    expect(app(LeadScoringService::class)->calculateFitScore($lead))->toBe(25);
});

it('matches the cnae criterion via the company primary CNAE code', function () {
    $cnae = Cnae::create(['code' => '6201-5/01', 'description' => 'Desenvolvimento de software']);
    LeadScoringRule::create(['criterion' => 'cnae', 'criterion_value' => '6201-5/01', 'score_weight' => 30, 'is_active' => true]);

    $lead = createLeadForScoringTest(['primary_cnae_id' => $cnae->id]);

    expect(app(LeadScoringService::class)->calculateFitScore($lead))->toBe(30);
});

it('matches the revenue_range criterion within bounds and rejects outside', function () {
    LeadScoringRule::create(['criterion' => 'revenue_range', 'criterion_value' => '360000-4800000', 'score_weight' => 40, 'is_active' => true]);

    $inRange = createLeadForScoringTest(['estimated_revenue_value' => 1000000]);
    $belowRange = createLeadForScoringTest(['estimated_revenue_value' => 100000]);
    $aboveRange = createLeadForScoringTest(['estimated_revenue_value' => 9000000]);

    $service = app(LeadScoringService::class);
    expect($service->calculateFitScore($inRange))->toBe(40);
    expect($service->calculateFitScore($belowRange))->toBe(0);
    expect($service->calculateFitScore($aboveRange))->toBe(0);
});

it('matches an open-ended revenue_range above a minimum', function () {
    LeadScoringRule::create(['criterion' => 'revenue_range', 'criterion_value' => '4800000-', 'score_weight' => 50, 'is_active' => true]);

    $lead = createLeadForScoringTest(['estimated_revenue_value' => 50000000]);

    expect(app(LeadScoringService::class)->calculateFitScore($lead))->toBe(50);
});

it('clamps the fit score to 100 even when rule weights sum higher', function () {
    LeadScoringRule::create(['criterion' => 'company_size', 'criterion_value' => 'ME', 'score_weight' => 70, 'is_active' => true]);
    LeadScoringRule::create(['criterion' => 'tax_regime', 'criterion_value' => 'simples', 'score_weight' => 70, 'is_active' => true]);

    $lead = createLeadForScoringTest(['company_size' => 'ME', 'tax_regime' => 'simples']);

    expect(app(LeadScoringService::class)->calculateFitScore($lead))->toBe(100);
});

it('awards intent points for recent interactions up to the cap', function () {
    $lead = createLeadForScoringTest();

    for ($i = 0; $i < 7; $i++) {
        $lead->interactions()->create(['type' => 'note', 'description' => "nota {$i}", 'occurred_at' => now()->subDays($i)]);
    }

    // 7 interações, mas o cap é 5 * 10 pts = 50.
    expect(app(LeadScoringService::class)->calculateIntentScore($lead))->toBe(50);
});

it('does not count system or stage_change interactions toward intent score', function () {
    $lead = createLeadForScoringTest();
    $lead->interactions()->create(['type' => 'system', 'description' => 'log automático', 'occurred_at' => now()]);
    $lead->interactions()->create(['type' => 'stage_change', 'description' => 'mudança de etapa', 'occurred_at' => now()]);

    expect(app(LeadScoringService::class)->calculateIntentScore($lead))->toBe(0);
});

it('awards intent points for an active proposal', function () {
    $lead = createLeadForScoringTest();
    $lead->proposals()->create(['version' => 1, 'status' => 'active']);

    expect(app(LeadScoringService::class)->calculateIntentScore($lead))->toBe(30);
});

it('decays the intent score linearly after the inactivity grace period', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 19, 12, 0, 0));
    $lead = createLeadForScoringTest();
    $lead->proposals()->create(['version' => 1, 'status' => 'active']); // 30 pts de base
    $lead->update(['last_interaction_at' => now()->subDays(10)]); // 3 dias após a folga de 7 -> -6 pts

    expect(app(LeadScoringService::class)->calculateIntentScore($lead))->toBe(24);
});

it('respects custom scoring.intent_decay_grace_days and scoring.intent_decay_rate_per_day settings', function () {
    Setting::set('scoring.intent_decay_grace_days', 2);
    Setting::set('scoring.intent_decay_rate_per_day', 5);

    Carbon::setTestNow(Carbon::create(2026, 6, 19, 12, 0, 0));
    $lead = createLeadForScoringTest();
    $lead->proposals()->create(['version' => 1, 'status' => 'active']); // 30 pts de base
    $lead->update(['last_interaction_at' => now()->subDays(10)]); // 8 dias após a folga de 2 -> -40 pts

    // Com os defaults (grace=7, rate=2) este mesmo cenário resultaria em 24, não 0.
    expect(app(LeadScoringService::class)->calculateIntentScore($lead))->toBe(0);
});

it('floors the intent score at zero when decay exceeds the base points', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 19, 12, 0, 0));
    $lead = createLeadForScoringTest();
    $lead->update(['last_interaction_at' => now()->subDays(60)]);

    expect(app(LeadScoringService::class)->calculateIntentScore($lead))->toBe(0);
});

it('recalculates and persists fit, intent and total score with a timestamp', function () {
    LeadScoringRule::create(['criterion' => 'company_size', 'criterion_value' => 'ME', 'score_weight' => 20, 'is_active' => true]);
    $lead = createLeadForScoringTest(['company_size' => 'ME']);
    $lead->proposals()->create(['version' => 1, 'status' => 'active']);

    $updated = app(LeadScoringService::class)->recalculate($lead);

    expect($updated->fit_score)->toBe(20);
    expect($updated->intent_score)->toBe(30);
    expect($updated->total_score)->toBe(50);
    expect($updated->score_updated_at)->not->toBeNull();
});

it('computes an initial fit score when a lead is created', function () {
    LeadScoringRule::create(['criterion' => 'company_size', 'criterion_value' => 'ME', 'score_weight' => 45, 'is_active' => true]);
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Criação Lead',
        'company_size' => 'ME',
    ]);

    $lead = app(CreateLeadAction::class)->execute($company);

    expect($lead->fit_score)->toBe(45);
    expect($lead->score_updated_at)->not->toBeNull();
});

it('recalculates scores for all non-archived leads via the job and skips archived ones', function () {
    $active = createLeadForScoringTest();
    $archived = createLeadForScoringTest();
    $archived->update(['archived_at' => now()]);

    $recalculated = RecalculateLeadScoresJob::dispatchSync();

    expect($recalculated)->toBe(1);
    expect($active->fresh()->score_updated_at)->not->toBeNull();
    expect($archived->fresh()->score_updated_at)->toBeNull();
});

it('logs and continues past a lead that fails to recalculate instead of aborting the rest', function () {
    Log::spy();

    $broken = createLeadForScoringTest();
    $healthy = createLeadForScoringTest();

    Lead::updating(function (Lead $lead) use ($broken) {
        if ($lead->is($broken)) {
            throw new RuntimeException('falha forçada para teste');
        }
    });

    $recalculated = RecalculateLeadScoresJob::dispatchSync();

    expect($recalculated)->toBe(1);
    expect($broken->fresh()->score_updated_at)->toBeNull();
    expect($healthy->fresh()->score_updated_at)->not->toBeNull();
    Log::shouldHaveReceived('error')->once();
});

it('recalculates intent score after registering an interaction', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForScoringTest();

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'note',
        'description' => 'Conversa rápida',
    ])->assertSessionDoesntHaveErrors();

    expect($lead->fresh()->intent_score)->toBe(10);
});
