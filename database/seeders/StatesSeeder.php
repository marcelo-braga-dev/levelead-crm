<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;

/**
 * As 26 UFs + Distrito Federal, com código IBGE oficial. Dataset completo e estável
 * (não depende de fixture externa) — diferente de cities/cnaes/legal_natures, que aqui
 * estão como amostra de desenvolvimento (ver respectivos seeders).
 */
class StatesSeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['uf' => 'AC', 'name' => 'Acre', 'ibge_code' => 12],
            ['uf' => 'AL', 'name' => 'Alagoas', 'ibge_code' => 27],
            ['uf' => 'AP', 'name' => 'Amapá', 'ibge_code' => 16],
            ['uf' => 'AM', 'name' => 'Amazonas', 'ibge_code' => 13],
            ['uf' => 'BA', 'name' => 'Bahia', 'ibge_code' => 29],
            ['uf' => 'CE', 'name' => 'Ceará', 'ibge_code' => 23],
            ['uf' => 'DF', 'name' => 'Distrito Federal', 'ibge_code' => 53],
            ['uf' => 'ES', 'name' => 'Espírito Santo', 'ibge_code' => 32],
            ['uf' => 'GO', 'name' => 'Goiás', 'ibge_code' => 52],
            ['uf' => 'MA', 'name' => 'Maranhão', 'ibge_code' => 21],
            ['uf' => 'MT', 'name' => 'Mato Grosso', 'ibge_code' => 51],
            ['uf' => 'MS', 'name' => 'Mato Grosso do Sul', 'ibge_code' => 50],
            ['uf' => 'MG', 'name' => 'Minas Gerais', 'ibge_code' => 31],
            ['uf' => 'PA', 'name' => 'Pará', 'ibge_code' => 15],
            ['uf' => 'PB', 'name' => 'Paraíba', 'ibge_code' => 25],
            ['uf' => 'PR', 'name' => 'Paraná', 'ibge_code' => 41],
            ['uf' => 'PE', 'name' => 'Pernambuco', 'ibge_code' => 26],
            ['uf' => 'PI', 'name' => 'Piauí', 'ibge_code' => 22],
            ['uf' => 'RJ', 'name' => 'Rio de Janeiro', 'ibge_code' => 33],
            ['uf' => 'RN', 'name' => 'Rio Grande do Norte', 'ibge_code' => 24],
            ['uf' => 'RS', 'name' => 'Rio Grande do Sul', 'ibge_code' => 43],
            ['uf' => 'RO', 'name' => 'Rondônia', 'ibge_code' => 11],
            ['uf' => 'RR', 'name' => 'Roraima', 'ibge_code' => 14],
            ['uf' => 'SC', 'name' => 'Santa Catarina', 'ibge_code' => 42],
            ['uf' => 'SP', 'name' => 'São Paulo', 'ibge_code' => 35],
            ['uf' => 'SE', 'name' => 'Sergipe', 'ibge_code' => 28],
            ['uf' => 'TO', 'name' => 'Tocantins', 'ibge_code' => 17],
        ];

        foreach ($states as $state) {
            State::query()->updateOrCreate(['uf' => $state['uf']], $state);
        }
    }
}
