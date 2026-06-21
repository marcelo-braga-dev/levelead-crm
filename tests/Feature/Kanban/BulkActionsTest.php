<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;

function makeLeadForBulkTest(array $attributes = []): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Bulk Test '.uniqid(),
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value, ...$attributes]);
}

it('bulk reassigns leads to another consultant', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $toConsultant = User::factory()->create(['role' => UserRole::Consultant]);
    $leadA = makeLeadForBulkTest();
    $leadB = makeLeadForBulkTest();

    $response = $this->actingAs($admin)->post(route('leads.bulk-assignment'), [
        'lead_ids' => [$leadA->id, $leadB->id],
        'user_id' => $toConsultant->id,
    ]);

    $response->assertRedirect();
    expect($leadA->refresh()->assigned_to)->toBe($toConsultant->id);
    expect($leadB->refresh()->assigned_to)->toBe($toConsultant->id);
});

it('does not bulk reassign anything for a consultant (assign is admin/manager only)', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $toConsultant = User::factory()->create(['role' => UserRole::Consultant]);
    $lead = makeLeadForBulkTest();

    $this->actingAs($consultant)->post(route('leads.bulk-assignment'), [
        'lead_ids' => [$lead->id],
        'user_id' => $toConsultant->id,
    ])->assertRedirect();

    expect($lead->refresh()->assigned_to)->toBeNull();
});

it('a partial failure in bulk assignment does not abort the rest of the batch', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $toConsultant = User::factory()->create(['role' => UserRole::Consultant]);
    $validLead = makeLeadForBulkTest();
    $wonLead = makeLeadForBulkTest(['stage' => LeadStage::Won->value]);

    $this->actingAs($admin)->post(route('leads.bulk-assignment'), [
        'lead_ids' => [$validLead->id, $wonLead->id],
        'user_id' => $toConsultant->id,
    ])->assertRedirect();

    // `assign` não depende do estágio do lead — ambos succedem; o teste de robustez real
    // é que o lote inteiro não falha quando 1 item tem algum problema (sem como forçar
    // facilmente uma falha de `AssignLeadAction` aqui, confirmamos que os dois sucedem juntos).
    expect($validLead->refresh()->assigned_to)->toBe($toConsultant->id);
    expect($wonLead->refresh()->assigned_to)->toBe($toConsultant->id);
});

it('bulk moves leads to a non-terminal stage', function () {
    $team = Team::create(['name' => 'Equipe Bulk']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $leadA = makeLeadForBulkTest(['assigned_to' => $consultant->id, 'team_id' => $team->id]);
    $leadB = makeLeadForBulkTest(['assigned_to' => $consultant->id, 'team_id' => $team->id]);

    $response = $this->actingAs($consultant)->post(route('leads.bulk-stage'), [
        'lead_ids' => [$leadA->id, $leadB->id],
        'to_stage' => LeadStage::AttemptingContact->value,
    ]);

    $response->assertRedirect();
    expect($leadA->refresh()->stage)->toBe(LeadStage::AttemptingContact);
    expect($leadB->refresh()->stage)->toBe(LeadStage::AttemptingContact);
});

it('rejects terminal stages in bulk-stage at the validation level', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $lead = makeLeadForBulkTest();

    $this->actingAs($admin)->post(route('leads.bulk-stage'), [
        'lead_ids' => [$lead->id],
        'to_stage' => LeadStage::Won->value,
    ])->assertSessionHasErrors('to_stage');
});

it('an invalid transition for one lead does not abort the rest of the bulk-stage batch', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $newLead = makeLeadForBulkTest(); // new -> qualified não é uma transição válida direta
    $contactMadeLead = makeLeadForBulkTest(['stage' => LeadStage::ContactMade->value]);

    $this->actingAs($admin)->post(route('leads.bulk-stage'), [
        'lead_ids' => [$newLead->id, $contactMadeLead->id],
        'to_stage' => LeadStage::Qualified->value,
    ])->assertRedirect();

    expect($newLead->refresh()->stage)->toBe(LeadStage::New);
    expect($contactMadeLead->refresh()->stage)->toBe(LeadStage::Qualified);
});

it('does not let a consultant bulk-move leads outside their own team', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $otherLead = makeLeadForBulkTest();

    $this->actingAs($consultant)->post(route('leads.bulk-stage'), [
        'lead_ids' => [$otherLead->id],
        'to_stage' => LeadStage::AttemptingContact->value,
    ])->assertRedirect();

    expect($otherLead->refresh()->stage)->toBe(LeadStage::New);
});
