<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Ordem respeita dependências de FK.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            StatesSeeder::class,
            CitiesSeeder::class,
            LegalNaturesSeeder::class,
            CnaesSeeder::class,
            PartnerQualificationsSeeder::class,
            TeamsSeeder::class,
            UsersSeeder::class,
            ProductsSeeder::class,
            LeadSourcesSeeder::class,
            NationalHolidaysSeeder::class,
            LeadScoringRulesSeeder::class,
            LossReasonRecycleRulesSeeder::class,
            CompanySeeder::class,
        ]);
    }
}
