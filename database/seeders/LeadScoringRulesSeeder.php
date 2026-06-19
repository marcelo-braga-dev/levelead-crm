<?php

namespace Database\Seeders;

use App\Models\LeadScoringRule;
use Illuminate\Database\Seeder;

/**
 * Pesos de exemplo do fit score — calibrar com dados reais antes de fixar os thresholds
 * Hot/Warm/Cold definitivos (ver PLANO_IMPLEMENTACAO.md, "Decisões ainda abertas").
 */
class LeadScoringRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['criterion' => 'company_size', 'criterion_value' => 'Grande', 'score_weight' => 20],
            ['criterion' => 'company_size', 'criterion_value' => 'Medio', 'score_weight' => 15],
            ['criterion' => 'company_size', 'criterion_value' => 'EPP', 'score_weight' => 10],
            ['criterion' => 'company_size', 'criterion_value' => 'ME', 'score_weight' => 5],
            ['criterion' => 'tax_regime', 'criterion_value' => 'real', 'score_weight' => 15],
            ['criterion' => 'tax_regime', 'criterion_value' => 'presumido', 'score_weight' => 10],
            ['criterion' => 'tax_regime', 'criterion_value' => 'simples', 'score_weight' => 5],
        ];

        foreach ($rules as $rule) {
            LeadScoringRule::query()->updateOrCreate(
                ['criterion' => $rule['criterion'], 'criterion_value' => $rule['criterion_value']],
                ['score_weight' => $rule['score_weight'], 'is_active' => true],
            );
        }
    }
}
