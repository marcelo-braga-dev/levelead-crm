<?php

use App\DomainServices\LeadStageTransitionService;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\LeadStageHistory;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;

function createLeadForStageTest(LeadStage $stage = LeadStage::New): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Stage Test',
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => $stage->value]);
}

it('moves a lead forward through the funnel recording stage history', function () {
    $lead = createLeadForStageTest();
    $service = app(LeadStageTransitionService::class);

    $service->transition($lead, LeadStage::AttemptingContact);
    $service->transition($lead, LeadStage::ContactMade);
    $service->transition($lead, LeadStage::Qualified);

    expect($lead->fresh()->stage)->toBe(LeadStage::Qualified);
    expect(LeadStageHistory::where('lead_id', $lead->id)->count())->toBe(3);

    $history = LeadStageHistory::where('lead_id', $lead->id)->orderBy('id')->get();
    expect($history[0]->from_stage)->toBe(LeadStage::New);
    expect($history[0]->to_stage)->toBe(LeadStage::AttemptingContact);
});

it('rejects an invalid transition that skips stages', function () {
    $lead = createLeadForStageTest();

    expect(fn () => app(LeadStageTransitionService::class)->transition($lead, LeadStage::Won))
        ->toThrow(DomainException::class);

    expect($lead->fresh()->stage)->toBe(LeadStage::New);
});

it('requires a reason to regress one stage and records it', function () {
    $lead = createLeadForStageTest(LeadStage::Qualified);
    $service = app(LeadStageTransitionService::class);

    expect(fn () => $service->transition($lead, LeadStage::ContactMade))
        ->toThrow(DomainException::class);

    $service->transition($lead, LeadStage::ContactMade, ['reason' => 'Cliente pediu para revisar proposta']);

    expect($lead->fresh()->stage)->toBe(LeadStage::ContactMade);
    $history = LeadStageHistory::where('lead_id', $lead->id)->latest('id')->first();
    expect($history->reason)->toBe('Cliente pediu para revisar proposta');
});

it('treats won and lost as terminal stages with no outgoing transition', function () {
    $won = createLeadForStageTest(LeadStage::Won);
    $lost = createLeadForStageTest(LeadStage::Lost);
    $service = app(LeadStageTransitionService::class);

    expect(fn () => $service->transition($won, LeadStage::Negotiation))->toThrow(DomainException::class);
    expect(fn () => $service->transition($lost, LeadStage::New))->toThrow(DomainException::class);
});

it('requires loss_reason via the http endpoint when moving to lost', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForStageTest();

    $response = $this->actingAs($user)->patch(route('leads.stage.update', $lead), ['to_stage' => 'lost']);
    $response->assertSessionHasErrors('loss_reason');

    $response = $this->actingAs($user)->patch(route('leads.stage.update', $lead), [
        'to_stage' => 'lost',
        'loss_reason' => 'no_budget',
    ]);
    $response->assertSessionDoesntHaveErrors();
    expect($lead->fresh()->loss_reason->value)->toBe('no_budget');
});

it('requires won_value via the http endpoint when moving to won', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForStageTest(LeadStage::Negotiation);
    $product = Product::create(['name' => 'Produto Teste']);

    $response = $this->actingAs($user)->patch(route('leads.stage.update', $lead), ['to_stage' => 'won']);
    $response->assertSessionHasErrors('won_value');

    $response = $this->actingAs($user)->patch(route('leads.stage.update', $lead), [
        'to_stage' => 'won',
        'won_value' => '1500.00',
        'won_product_id' => $product->id,
    ]);
    $response->assertSessionDoesntHaveErrors();
    expect((string) $lead->fresh()->won_value)->toBe('1500.00');
});

it('accepts a regression transition even when the form sends blank loss_reason/won_value alongside it', function () {
    // Reproduz o payload real do StageTransitionDialog: o form sempre envia todos os campos
    // (loss_reason/won_value vazios) mesmo numa transição de retrocesso comum.
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForStageTest(LeadStage::Qualified);

    $response = $this->actingAs($user)->patch(route('leads.stage.update', $lead), [
        'to_stage' => 'contact_made',
        'reason' => 'Cliente pediu para revisar',
        'loss_reason' => '',
        'loss_notes' => '',
        'won_value' => '',
        'won_product_id' => '',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($lead->fresh()->stage)->toBe(LeadStage::ContactMade);
});

it('records the stage change in audit_logs as an update on the lead', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForStageTest();

    $this->actingAs($user)->patch(route('leads.stage.update', $lead), ['to_stage' => 'attempting_contact']);

    $log = AuditLog::where('auditable_type', $lead->getMorphClass())
        ->where('auditable_id', $lead->id)
        ->where('action', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->new_values['stage'])->toBe('attempting_contact');
});

it('records the stage change in lead_interactions as the unified card history', function () {
    $lead = createLeadForStageTest(LeadStage::Qualified);
    $service = app(LeadStageTransitionService::class);

    $service->transition($lead, LeadStage::ContactMade, ['reason' => 'Cliente pediu para revisar']);

    $interaction = LeadInteraction::where('lead_id', $lead->id)->latest('id')->first();
    expect($interaction)->not->toBeNull();
    expect($interaction->type->value)->toBe('stage_change');
    expect($interaction->description)->toContain('Cliente pediu para revisar');
});

it('forbids a consultant from transitioning a lead outside their team', function () {
    $team = Team::create(['name' => 'Equipe Stage']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $lead = createLeadForStageTest();

    $this->actingAs($consultant)
        ->patch(route('leads.stage.update', $lead), ['to_stage' => 'attempting_contact'])
        ->assertForbidden();
});
