<?php

namespace Database\Seeders;

use App\Models\Cnae;
use Illuminate\Database\Seeder;

/**
 * Amostra de desenvolvimento de CNAEs comuns (versão 2.3). NÃO é a tabela completa
 * (~1.300 subclasses) — substituir por fixture oficial IBGE/Receita Federal em produção.
 */
class CnaesSeeder extends Seeder
{
    public function run(): void
    {
        $cnaes = [
            ['code' => '6201-5/01', 'description' => 'Desenvolvimento de programas de computador sob encomenda'],
            ['code' => '6202-3/00', 'description' => 'Desenvolvimento e licenciamento de programas de computador customizáveis'],
            ['code' => '6204-0/00', 'description' => 'Consultoria em tecnologia da informação'],
            ['code' => '7020-4/00', 'description' => 'Atividades de consultoria em gestão empresarial'],
            ['code' => '8211-3/00', 'description' => 'Serviços combinados de escritório e apoio administrativo'],
            ['code' => '4751-2/01', 'description' => 'Comércio varejista especializado de equipamentos e suprimentos de informática'],
            ['code' => '4120-4/00', 'description' => 'Construção de edifícios'],
            ['code' => '5611-2/01', 'description' => 'Restaurantes e similares'],
            ['code' => '8599-6/04', 'description' => 'Treinamento em informática'],
            ['code' => '6911-7/01', 'description' => 'Serviços advocatícios'],
        ];

        foreach ($cnaes as $cnae) {
            Cnae::query()->updateOrCreate(['code' => $cnae['code']], $cnae);
        }
    }
}
