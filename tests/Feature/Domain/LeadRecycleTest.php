<?php

use App\Actions\Leads\RecycleLeadAction;
use App\DomainServices\LeadStageTransitionService;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\LossReasonRecycleRulesSeeder;

function makeLostLead(string $lossReason = 'no_budget'): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Reciclagem Teste',
    ]);

    $lead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);

    app(LeadStageTransitionService::class)->transition($lead, LeadStage::Lost, ['loss_reason' => $lossReason]);

    return $lead->fresh();
}

it('creates a new lead in New stage linked to the lost lead', function () {
    $lostLead = makeLostLead();

    $newLead = app(RecycleLeadAction::class)->execute($lostLead);

    expect($newLead->stage)->toBe(LeadStage::New);
    expect($newLead->company_id)->toBe($lostLead->company_id);
    expect($newLead->is_recycled)->toBeTrue();
    expect($newLead->recycled_from_lead_id)->toBe($lostLead->id);
    expect($newLead->id)->not->toBe($lostLead->id);

    // o lead perdido original permanece imutável
    expect($lostLead->fresh()->stage)->toBe(LeadStage::Lost);
});

it('refuses to recycle a lead that is not lost', function () {
    $company = Company::create(['cnpj' => (string) random_int(10000000000000, 99999999999999), 'razao_social' => 'Empresa Won']);
    $wonLead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);
    $service = app(LeadStageTransitionService::class);
    $service->transition($wonLead, LeadStage::AttemptingContact);
    $service->transition($wonLead, LeadStage::ContactMade);
    $service->transition($wonLead, LeadStage::Qualified);
    $service->transition($wonLead, LeadStage::ProposalSent);
    $service->transition($wonLead, LeadStage::Negotiation);
    $service->transition($wonLead, LeadStage::Won, ['won_value' => '1000']);

    expect(fn () => app(RecycleLeadAction::class)->execute($wonLead->fresh()))
        ->toThrow(DomainException::class);
});

it('refuses to recycle when the company already has an open lead', function () {
    $lostLead = makeLostLead();
    Lead::create(['company_id' => $lostLead->company_id, 'stage' => LeadStage::New->value]);

    expect(fn () => app(RecycleLeadAction::class)->execute($lostLead))
        ->toThrow(DomainException::class);
});

it('lets a manager recycle a lost lead via the route', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $lostLead = makeLostLead();

    $response = $this->actingAs($manager)->post(route('leads.recycle', $lostLead));

    $response->assertSessionDoesntHaveErrors();
    $response->assertSessionHas('status');
    expect(Lead::where('recycled_from_lead_id', $lostLead->id)->exists())->toBeTrue();
});

it('forbids a consultant from recycling a lost lead', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $lostLead = makeLostLead();

    $this->actingAs($consultant)
        ->post(route('leads.recycle', $lostLead))
        ->assertForbidden();

    expect(Lead::where('recycled_from_lead_id', $lostLead->id)->exists())->toBeFalse();
});

it('exposes the loss reason recycle rules to the companies index page', function () {
    $this->seed(LossReasonRecycleRulesSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('companies.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('lossReasonRecycleRules', 6)
        ->where('lossReasonRecycleRules.0.loss_reason', 'no_budget'));
});
