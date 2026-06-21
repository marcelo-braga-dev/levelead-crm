<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\City;
use App\Models\Company;
use App\Models\Lead;
use App\Models\State;
use App\Models\User;

function makeCompanyWithLead(array $companyAttributes, array $leadAttributes = []): Company
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Filtro '.uniqid(),
        ...$companyAttributes,
    ]);

    Lead::create([
        'company_id' => $company->id,
        'stage' => LeadStage::New->value,
        ...$leadAttributes,
    ]);

    return $company;
}

it('filters companies by state', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $sp = State::create(['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35]);
    $rj = State::create(['uf' => 'RJ', 'name' => 'Rio de Janeiro', 'ibge_code' => 33]);

    makeCompanyWithLead(['razao_social' => 'Empresa SP', 'state_id' => $sp->id]);
    makeCompanyWithLead(['razao_social' => 'Empresa RJ', 'state_id' => $rj->id]);

    $response = $this->actingAs($admin)->get(route('companies.index', ['state_id' => $sp->id]));

    $response->assertInertia(fn ($page) => $page
        ->has('companies.data', 1)
        ->where('companies.data.0.razao_social', 'Empresa SP'));
});

it('filters companies by city name', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $sp = State::create(['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35]);
    $capital = City::create(['name' => 'São Paulo', 'state_id' => $sp->id, 'ibge_code' => 3550308]);
    $campinas = City::create(['name' => 'Campinas', 'state_id' => $sp->id, 'ibge_code' => 3509502]);

    makeCompanyWithLead(['razao_social' => 'Empresa Capital', 'state_id' => $sp->id, 'city_id' => $capital->id]);
    makeCompanyWithLead(['razao_social' => 'Empresa Campinas', 'state_id' => $sp->id, 'city_id' => $campinas->id]);

    $response = $this->actingAs($admin)->get(route('companies.index', ['city' => 'Campinas']));

    $response->assertInertia(fn ($page) => $page
        ->has('companies.data', 1)
        ->where('companies.data.0.razao_social', 'Empresa Campinas'));
});

it('filters companies by assigned consultant', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    makeCompanyWithLead(['razao_social' => 'Empresa Atribuida'], ['assigned_to' => $consultant->id]);
    makeCompanyWithLead(['razao_social' => 'Empresa Sem Atribuicao']);

    $response = $this->actingAs($admin)->get(route('companies.index', ['assigned_to' => $consultant->id]));

    $response->assertInertia(fn ($page) => $page
        ->has('companies.data', 1)
        ->where('companies.data.0.razao_social', 'Empresa Atribuida'));
});

it('filters companies by lead stage', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    makeCompanyWithLead(['razao_social' => 'Empresa Qualificada'], ['stage' => LeadStage::Qualified->value]);
    makeCompanyWithLead(['razao_social' => 'Empresa Nova'], ['stage' => LeadStage::New->value]);

    $response = $this->actingAs($admin)->get(route('companies.index', ['stage' => LeadStage::Qualified->value]));

    $response->assertInertia(fn ($page) => $page
        ->has('companies.data', 1)
        ->where('companies.data.0.razao_social', 'Empresa Qualificada'));
});

it('combines new filters with the existing text search', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $sp = State::create(['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35]);

    makeCompanyWithLead(['razao_social' => 'Padaria Central', 'state_id' => $sp->id]);
    makeCompanyWithLead(['razao_social' => 'Padaria do Bairro']);

    $response = $this->actingAs($admin)->get(route('companies.index', ['search' => 'Padaria', 'state_id' => $sp->id]));

    $response->assertInertia(fn ($page) => $page
        ->has('companies.data', 1)
        ->where('companies.data.0.razao_social', 'Padaria Central'));
});
