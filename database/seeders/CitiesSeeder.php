<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * Amostra de desenvolvimento: apenas as capitais de cada UF, para destravar testes e o board
 * localmente. NÃO é o fixture oficial completo (IBGE tem ~5.570 municípios) exigido pelo plano —
 * a importação da lista completa fica para quando houver acesso à fonte oficial (IBGE/Receita
 * Federal) e deve substituir este seeder sem quebrar FKs (mesma estrutura uf->ibge_code->name).
 */
class CitiesSeeder extends Seeder
{
    public function run(): void
    {
        $capitals = [
            'AC' => ['name' => 'Rio Branco', 'ibge_code' => 1200401],
            'AL' => ['name' => 'Maceió', 'ibge_code' => 2704302],
            'AP' => ['name' => 'Macapá', 'ibge_code' => 1600303],
            'AM' => ['name' => 'Manaus', 'ibge_code' => 1302603],
            'BA' => ['name' => 'Salvador', 'ibge_code' => 2927408],
            'CE' => ['name' => 'Fortaleza', 'ibge_code' => 2304400],
            'DF' => ['name' => 'Brasília', 'ibge_code' => 5300108],
            'ES' => ['name' => 'Vitória', 'ibge_code' => 3205309],
            'GO' => ['name' => 'Goiânia', 'ibge_code' => 5208707],
            'MA' => ['name' => 'São Luís', 'ibge_code' => 2111300],
            'MT' => ['name' => 'Cuiabá', 'ibge_code' => 5103403],
            'MS' => ['name' => 'Campo Grande', 'ibge_code' => 5002704],
            'MG' => ['name' => 'Belo Horizonte', 'ibge_code' => 3106200],
            'PA' => ['name' => 'Belém', 'ibge_code' => 1501402],
            'PB' => ['name' => 'João Pessoa', 'ibge_code' => 2507507],
            'PR' => ['name' => 'Curitiba', 'ibge_code' => 4106902],
            'PE' => ['name' => 'Recife', 'ibge_code' => 2611606],
            'PI' => ['name' => 'Teresina', 'ibge_code' => 2211001],
            'RJ' => ['name' => 'Rio de Janeiro', 'ibge_code' => 3304557],
            'RN' => ['name' => 'Natal', 'ibge_code' => 2408102],
            'RS' => ['name' => 'Porto Alegre', 'ibge_code' => 4314902],
            'RO' => ['name' => 'Porto Velho', 'ibge_code' => 1100205],
            'RR' => ['name' => 'Boa Vista', 'ibge_code' => 1400100],
            'SC' => ['name' => 'Florianópolis', 'ibge_code' => 4205407],
            'SP' => ['name' => 'São Paulo', 'ibge_code' => 3550308],
            'SE' => ['name' => 'Aracaju', 'ibge_code' => 2800308],
            'TO' => ['name' => 'Palmas', 'ibge_code' => 1721000],
        ];

        $stateIdsByUf = State::query()->pluck('id', 'uf');

        foreach ($capitals as $uf => $city) {
            if (! $stateIdsByUf->has($uf)) {
                continue;
            }

            City::query()->updateOrCreate(
                ['ibge_code' => $city['ibge_code']],
                ['name' => $city['name'], 'state_id' => $stateIdsByUf[$uf]],
            );
        }
    }
}
