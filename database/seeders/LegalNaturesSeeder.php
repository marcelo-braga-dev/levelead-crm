<?php

namespace Database\Seeders;

use App\Models\LegalNature;
use Illuminate\Database\Seeder;

/**
 * Amostra de desenvolvimento das naturezas jurídicas mais comuns. NÃO é a tabela oficial
 * completa da Receita Federal/IBGE (~90 códigos) — substituir por fixture oficial em produção.
 */
class LegalNaturesSeeder extends Seeder
{
    public function run(): void
    {
        $legalNatures = [
            ['code' => '2062', 'description' => 'Sociedade Empresária Limitada'],
            ['code' => '2135', 'description' => 'Empresário (Individual)'],
            ['code' => '2240', 'description' => 'Sociedade Simples Limitada'],
            ['code' => '2046', 'description' => 'Sociedade Anônima Aberta'],
            ['code' => '2054', 'description' => 'Sociedade Anônima Fechada'],
            ['code' => '2143', 'description' => 'Empresa Individual de Responsabilidade Limitada (de Natureza Empresária)'],
            ['code' => '3999', 'description' => 'Associação Privada'],
            ['code' => '4014', 'description' => 'Cooperativa'],
        ];

        foreach ($legalNatures as $legalNature) {
            LegalNature::query()->updateOrCreate(['code' => $legalNature['code']], $legalNature);
        }
    }
}
