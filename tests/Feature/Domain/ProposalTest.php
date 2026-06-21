<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\Proposal;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createLeadForProposalTest(): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Proposal Test',
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::ProposalSent->value]);
}

it('creates the first proposal version as active', function () {
    Storage::fake('local');

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForProposalTest();

    $this->actingAs($user)->post(route('proposals.store', $lead), [
        'value' => '1200.50',
        'notes' => 'Proposta inicial',
    ])->assertSessionDoesntHaveErrors();

    $proposal = Proposal::where('lead_id', $lead->id)->first();
    expect($proposal->version)->toBe(1);
    expect($proposal->status)->toBe('active');
    expect((string) $proposal->value)->toBe('1200.50');
});

it('supersedes the previous active proposal when a new version is created', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForProposalTest();

    $this->actingAs($user)->post(route('proposals.store', $lead), ['value' => '1000.00']);
    $first = Proposal::where('lead_id', $lead->id)->first();

    $this->actingAs($user)->post(route('proposals.store', $lead), ['value' => '1500.00']);
    $second = Proposal::where('lead_id', $lead->id)->where('version', 2)->first();

    expect($first->fresh()->status)->toBe('superseded');
    expect($second->status)->toBe('active');
    expect($second->version)->toBe(2);
});

it('stores an uploaded attachment and allows downloading it', function () {
    Storage::fake('local');

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForProposalTest();

    $file = UploadedFile::fake()->create('proposta.pdf', 100, 'application/pdf');

    $this->actingAs($user)->post(route('proposals.store', $lead), [
        'value' => '900.00',
        'attachments' => [$file],
    ])->assertSessionDoesntHaveErrors();

    $proposal = Proposal::where('lead_id', $lead->id)->first();
    $attachment = $proposal->attachments->first();

    expect($attachment)->not->toBeNull();
    expect($attachment->original_name)->toBe('proposta.pdf');
    Storage::disk('local')->assertExists($attachment->path);

    $this->actingAs($user)
        ->get(route('proposals.attachments.download', $attachment))
        ->assertOk();
});

it('records a system interaction in the unified card history when a proposal is created', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForProposalTest();

    $this->actingAs($user)->post(route('proposals.store', $lead), ['value' => '500.00']);

    $interaction = LeadInteraction::where('lead_id', $lead->id)->latest('id')->first();
    expect($interaction->type->value)->toBe('system');
    expect($interaction->description)->toContain('Proposta v1 criada');
});

it('forbids a consultant outside the team from creating a proposal', function () {
    $team = Team::create(['name' => 'Equipe Proposal']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $lead = createLeadForProposalTest();

    $this->actingAs($consultant)
        ->post(route('proposals.store', $lead), ['value' => '500.00'])
        ->assertForbidden();
});
