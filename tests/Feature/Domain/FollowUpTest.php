<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\Team;
use App\Models\User;

function createLeadForFollowUpTest(): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa FollowUp Test',
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::ContactMade->value]);
}

it('schedules a follow-up for a lead', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForFollowUpTest();

    $this->actingAs($user)->post(route('follow-ups.store', $lead), [
        'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'notes' => 'Ligar de novo',
    ])->assertSessionDoesntHaveErrors();

    $followUp = FollowUp::where('lead_id', $lead->id)->first();
    expect($followUp)->not->toBeNull();
    expect($followUp->status)->toBe('pending');
});

it('requires scheduled_at to create a follow-up', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForFollowUpTest();

    $this->actingAs($user)->post(route('follow-ups.store', $lead), [])
        ->assertSessionHasErrors('scheduled_at');
});

it('completes a follow-up and records it in the unified card history', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForFollowUpTest();
    $followUp = $lead->followUps()->create([
        'scheduled_at' => now()->addDay(),
        'notes' => 'Cliente pediu retorno',
        'status' => 'pending',
    ]);

    $this->actingAs($user)->patch(route('follow-ups.complete', $followUp))
        ->assertSessionDoesntHaveErrors();

    expect($followUp->fresh()->status)->toBe('done');
    expect($followUp->fresh()->completed_at)->not->toBeNull();

    $interaction = LeadInteraction::where('lead_id', $lead->id)->latest('id')->first();
    expect($interaction->type->value)->toBe('follow_up');
    expect($interaction->description)->toBe('Cliente pediu retorno');
});

it('rejects completing a follow-up that is already done', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForFollowUpTest();
    $followUp = $lead->followUps()->create([
        'scheduled_at' => now()->subDay(),
        'status' => 'done',
        'completed_at' => now(),
    ]);

    $this->actingAs($user)->patch(route('follow-ups.complete', $followUp))
        ->assertSessionHasErrors('follow_up');
});

it('forbids a consultant outside the team from scheduling a follow-up', function () {
    $team = Team::create(['name' => 'Equipe FollowUp']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $lead = createLeadForFollowUpTest();

    $this->actingAs($consultant)
        ->post(route('follow-ups.store', $lead), ['scheduled_at' => now()->addDay()->format('Y-m-d H:i:s')])
        ->assertForbidden();
});
