<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function createLeadForProposalProductTest(): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Proposal Product Test',
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::ProposalSent->value]);
}

it('links a product to a proposal when provided', function () {
    Storage::fake('local');

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForProposalProductTest();
    $product = Product::create(['name' => 'Plano Avançado']);

    $this->actingAs($user)->post(route('proposals.store', $lead), [
        'value' => '999.00',
        'product_id' => $product->id,
    ])->assertSessionDoesntHaveErrors();

    $proposal = Proposal::where('lead_id', $lead->id)->first();
    expect($proposal->product_id)->toBe($product->id);
    expect($proposal->product->name)->toBe('Plano Avançado');
});

it('allows a proposal without a product', function () {
    Storage::fake('local');

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForProposalProductTest();

    $this->actingAs($user)->post(route('proposals.store', $lead), [
        'value' => '500.00',
    ])->assertSessionDoesntHaveErrors();

    $proposal = Proposal::where('lead_id', $lead->id)->first();
    expect($proposal->product_id)->toBeNull();
});

it('rejects a proposal referencing a nonexistent product', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForProposalProductTest();

    $this->actingAs($user)->post(route('proposals.store', $lead), [
        'value' => '500.00',
        'product_id' => 999999,
    ])->assertSessionHasErrors('product_id');
});
