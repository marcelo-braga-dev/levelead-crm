<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use Illuminate\Database\Seeder;

class LeadSourcesSeeder extends Seeder
{
    public function run(): void
    {
        $sources = ['Landing Page', 'Meta Ads', 'Google Ads', 'CSV', 'API', 'Manual'];

        foreach ($sources as $source) {
            LeadSource::query()->updateOrCreate(['name' => $source]);
        }
    }
}
