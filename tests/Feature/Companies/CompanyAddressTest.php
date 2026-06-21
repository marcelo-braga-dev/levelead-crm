<?php

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\State;
use App\Models\User;

function makeCompanyForAddress(): Company
{
    return Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Endereço Teste',
    ]);
}

it('lets a manager create an address for a company that has none yet', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $company = makeCompanyForAddress();
    $state = State::create(['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35]);
    $city = City::create(['name' => 'São Paulo', 'state_id' => $state->id, 'ibge_code' => 3550308]);

    $response = $this->actingAs($manager)->patch(route('companies.address.update', $company), [
        'logradouro' => 'Avenida Paulista',
        'numero' => '1000',
        'bairro' => 'Bela Vista',
        'cep' => '01310100',
        'state_id' => $state->id,
        'city_id' => $city->id,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertSessionHas('status');

    $address = $company->address()->first();
    expect($address)->not->toBeNull();
    expect($address->logradouro)->toBe('Avenida Paulista');
    expect($address->city_id)->toBe($city->id);
    expect($address->state_id)->toBe($state->id);
    expect(Address::count())->toBe(1);
});

it('updates the existing address in place instead of creating a second row', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $company = makeCompanyForAddress();
    $company->address()->create(['logradouro' => 'Rua Antiga', 'bairro' => 'Centro']);

    // O formulário do Kanban sempre envia todos os campos juntos (mesmo os vazios) — o
    // middleware ConvertEmptyStringsToNull do Laravel transforma '' em null antes da
    // validação, então omitir um campo (em vez de mandá-lo vazio) é um cenário diferente:
    // validated() só some com chaves ausentes, não com chaves explicitamente nulas.
    $this->actingAs($admin)->patch(route('companies.address.update', $company), [
        'logradouro' => 'Rua Nova',
        'bairro' => '',
    ])->assertSessionDoesntHaveErrors();

    expect(Address::count())->toBe(1);
    expect($company->address()->first()->logradouro)->toBe('Rua Nova');
    expect($company->address()->first()->bairro)->toBeNull();
});

it('leaves untouched fields as-is when only a partial payload is sent', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $company = makeCompanyForAddress();
    $company->address()->create(['logradouro' => 'Rua Antiga', 'bairro' => 'Centro']);

    $this->actingAs($admin)->patch(route('companies.address.update', $company), [
        'logradouro' => 'Rua Nova',
    ])->assertSessionDoesntHaveErrors();

    expect($company->address()->first()->logradouro)->toBe('Rua Nova');
    expect($company->address()->first()->bairro)->toBe('Centro');
});

it('forbids a consultant from updating a company address', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $company = makeCompanyForAddress();

    $this->actingAs($consultant)
        ->patch(route('companies.address.update', $company), ['logradouro' => 'Rua X'])
        ->assertForbidden();

    expect(Address::count())->toBe(0);
});

it('rejects a city_id or state_id that does not exist', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $company = makeCompanyForAddress();

    $this->actingAs($admin)
        ->patch(route('companies.address.update', $company), ['state_id' => 999999])
        ->assertSessionHasErrors('state_id');
});

it('lists cities scoped to the requested state via the lookup endpoint', function () {
    $user = User::factory()->create(['role' => UserRole::Consultant]);
    $sp = State::create(['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35]);
    $rj = State::create(['uf' => 'RJ', 'name' => 'Rio de Janeiro', 'ibge_code' => 33]);
    City::create(['name' => 'São Paulo', 'state_id' => $sp->id, 'ibge_code' => 3550308]);
    City::create(['name' => 'Campinas', 'state_id' => $sp->id, 'ibge_code' => 3509502]);
    City::create(['name' => 'Niterói', 'state_id' => $rj->id, 'ibge_code' => 3303302]);

    $response = $this->actingAs($user)->getJson(route('lookups.cities', ['state_id' => $sp->id]));

    $response->assertOk();
    expect($response->json())->toHaveCount(2);
    expect(collect($response->json())->pluck('name')->sort()->values()->all())
        ->toBe(['Campinas', 'São Paulo']);
});
