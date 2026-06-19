<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Comercial Norte', 'Comercial Sul'] as $name) {
            Team::query()->updateOrCreate(['name' => $name]);
        }
    }
}
