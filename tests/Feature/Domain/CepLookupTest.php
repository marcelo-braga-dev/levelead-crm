<?php

use App\Enums\UserRole;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('resolves city_id and state_id from a valid cep', function () {
    Http::fake([
        'viacep.com.br/*' => Http::response([
            'cep' => '01310-200',
            'logradouro' => 'Avenida Paulista',
            'bairro' => 'Bela Vista',
            'localidade' => 'São Paulo',
            'uf' => 'SP',
            'ibge' => '3550308',
        ]),
    ]);

    $state = State::create(['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35]);
    $city = City::create(['ibge_code' => 3550308, 'name' => 'São Paulo', 'state_id' => $state->id]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)->getJson(route('lookups.cep', ['cep' => '01310200']));

    $response->assertOk()->assertJson([
        'found' => true,
        'logradouro' => 'Avenida Paulista',
        'bairro' => 'Bela Vista',
        'city_id' => $city->id,
        'state_id' => $state->id,
        'city_name' => 'São Paulo',
    ]);
});

it('reports not found when viacep returns erro', function () {
    Http::fake(['viacep.com.br/*' => Http::response(['erro' => 'true'])]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)->getJson(route('lookups.cep', ['cep' => '00000000']));

    $response->assertOk()->assertJson(['found' => false]);
});

it('reports not found when the http call fails', function () {
    Http::fake(['viacep.com.br/*' => Http::response(null, 500)]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)->getJson(route('lookups.cep', ['cep' => '01310200']));

    $response->assertOk()->assertJson(['found' => false]);
});

it('rejects a cep with the wrong number of digits without calling the api', function () {
    Http::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)->getJson(route('lookups.cep', ['cep' => '123']));

    $response->assertOk()->assertJson(['found' => false]);
    Http::assertNothingSent();
});
