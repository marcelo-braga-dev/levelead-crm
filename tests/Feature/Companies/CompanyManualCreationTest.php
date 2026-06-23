<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;

it('lets a manager create a PJ lead manually, with the lead created together', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $response = $this->actingAs($manager)->post(route('companies.store'), [
        'person_type' => 'pj',
        'cnpj' => '11222333000181',
        'razao_social' => 'Padaria Pão Quente LTDA',
        'nome_fantasia' => 'Padaria Pão Quente',
        'contact_name' => 'João',
        'contact_phone' => '11988887777',
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertSessionHas('status');

    $company = Company::where('cnpj', '11222333000181')->first();
    expect($company)->not->toBeNull();
    expect($company->person_type->value)->toBe('pj');
    expect($company->razao_social)->toBe('Padaria Pão Quente LTDA');
    expect($company->nome_fantasia)->toBe('Padaria Pão Quente');

    $lead = Lead::where('company_id', $company->id)->first();
    expect($lead)->not->toBeNull();
    expect($lead->contact_name)->toBe('João');
    expect($lead->contact_phone)->toBe('11988887777');
    expect($lead->stage->value)->toBe('new');
});

it('lets a manager create a PF lead manually', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $response = $this->actingAs($manager)->post(route('companies.store'), [
        'person_type' => 'pf',
        'cpf' => '12345678901',
        'razao_social' => 'Maria da Silva',
    ]);

    $response->assertSessionDoesntHaveErrors();

    $company = Company::where('cpf', '12345678901')->first();
    expect($company)->not->toBeNull();
    expect($company->person_type->value)->toBe('pf');
    expect($company->cnpj)->toBeNull();
    expect(Lead::where('company_id', $company->id)->exists())->toBeTrue();
});

it('requires cnpj for pj and cpf for pf', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post(route('companies.store'), [
        'person_type' => 'pj',
        'razao_social' => 'Empresa Teste',
    ])->assertSessionHasErrors('cnpj');

    $this->actingAs($admin)->post(route('companies.store'), [
        'person_type' => 'pf',
        'razao_social' => 'Pessoa Teste',
    ])->assertSessionHasErrors('cpf');
});

it('lets an admin create a company without a nome_fantasia', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post(route('companies.store'), [
        'person_type' => 'pj',
        'cnpj' => '11222333000181',
        'razao_social' => 'Padaria Pão Quente LTDA',
    ])->assertSessionDoesntHaveErrors();

    expect(Company::where('cnpj', '11222333000181')->exists())->toBeTrue();
});

it('forbids a consultant from creating a company manually', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    $this->actingAs($consultant)->post(route('companies.store'), [
        'person_type' => 'pj',
        'cnpj' => '11222333000181',
        'razao_social' => 'Padaria Pão Quente LTDA',
    ])->assertForbidden();

    expect(Company::count())->toBe(0);
});

it('rejects a cnpj that already exists', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Company::create(['person_type' => 'pj', 'cnpj' => '11222333000181', 'razao_social' => 'Empresa Existente']);

    $response = $this->actingAs($admin)->post(route('companies.store'), [
        'person_type' => 'pj',
        'cnpj' => '11222333000181',
        'razao_social' => 'Outra Empresa LTDA',
    ]);

    $response->assertSessionHasErrors('cnpj');
    expect(Company::count())->toBe(1);
});

it('rejects a cnpj with the wrong number of digits', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->post(route('companies.store'), [
        'person_type' => 'pj',
        'cnpj' => '123',
        'razao_social' => 'Empresa Teste',
    ]);

    $response->assertSessionHasErrors('cnpj');
});

it('requires razao_social', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->post(route('companies.store'), [
        'person_type' => 'pj',
        'cnpj' => '11222333000181',
    ]);

    $response->assertSessionHasErrors('razao_social');
});

it('lets a manager update company data and the open lead contact together', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $company = Company::create(['person_type' => 'pj', 'cnpj' => '11222333000181', 'razao_social' => 'Empresa Original']);
    $lead = Lead::create([
        'company_id' => $company->id,
        'stage' => 'new',
        'stage_entered_at' => now(),
        'contact_name' => 'Nome Antigo',
    ]);

    $response = $this->actingAs($manager)->patch(route('companies.update', $company), [
        'person_type' => 'pj',
        'cnpj' => '11222333000181',
        'razao_social' => 'Empresa Atualizada LTDA',
        'contact_name' => 'Nome Novo',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($company->refresh()->razao_social)->toBe('Empresa Atualizada LTDA');
    expect($lead->refresh()->contact_name)->toBe('Nome Novo');
});

it('forbids a consultant from updating company registration data', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $company = Company::create(['person_type' => 'pj', 'cnpj' => '11222333000181', 'razao_social' => 'Empresa Original']);

    $this->actingAs($consultant)->patch(route('companies.update', $company), [
        'person_type' => 'pj',
        'cnpj' => '11222333000181',
        'razao_social' => 'Tentativa de Alteração',
    ])->assertForbidden();

    expect($company->refresh()->razao_social)->toBe('Empresa Original');
});

it('lets the assigned consultant update their own lead contact via the narrow endpoint', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $company = Company::create(['person_type' => 'pj', 'cnpj' => '11222333000181', 'razao_social' => 'Empresa Original']);
    $lead = Lead::create([
        'company_id' => $company->id,
        'stage' => 'new',
        'stage_entered_at' => now(),
        'assigned_to' => $consultant->id,
        'contact_name' => 'Nome Antigo',
    ]);

    $this->actingAs($consultant)->patch(route('leads.contact.update', $lead), [
        'contact_name' => 'Nome Novo',
    ])->assertSessionDoesntHaveErrors();

    expect($lead->refresh()->contact_name)->toBe('Nome Novo');
});
