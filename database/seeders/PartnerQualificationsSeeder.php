<?php

namespace Database\Seeders;

use App\Models\PartnerQualification;
use Illuminate\Database\Seeder;

/**
 * Amostra de desenvolvimento das qualificações de sócio mais comuns da tabela da Receita
 * Federal. Substituir por fixture oficial completa em produção.
 */
class PartnerQualificationsSeeder extends Seeder
{
    public function run(): void
    {
        $qualifications = [
            ['code' => '05', 'description' => 'Administrador'],
            ['code' => '10', 'description' => 'Diretor'],
            ['code' => '16', 'description' => 'Presidente'],
            ['code' => '22', 'description' => 'Sócio'],
            ['code' => '49', 'description' => 'Sócio-Administrador'],
            ['code' => '65', 'description' => 'Titular Pessoa Física Residente no Brasil'],
        ];

        foreach ($qualifications as $qualification) {
            PartnerQualification::query()->updateOrCreate(['code' => $qualification['code']], $qualification);
        }
    }
}
