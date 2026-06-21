<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Valores atuais dos limiares que eram `const` hardcoded antes do Hardening pós-MVP (H2) —
 * roda só com `updateOrCreate` implícito via `Setting::set()`, então não muda nada no
 * comportamento de quem já tinha a `const` antiga, só dá um lugar para ajustar sem deploy.
 * `theme.*` (redesign visual) segue o mesmo padrão: default de fábrica até o admin trocar
 * em `/admin/appearance`. `google_places.*` segue o mesmo padrão para os limiares de
 * negócio (cache/orçamento/selos) — a credencial em si (`GOOGLE_PLACES_API_KEY`) NÃO mora
 * aqui, vive em `config/services.php`/`.env` (settings é para config de negócio, não secret).
 * `google_places.budget_month`/`budget_used` (contador mensal) não entram aqui — são
 * mutados em runtime por `SyncGooglePlacesProfileAction`, não fazem sentido como "default".
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'compliance.max_calls_per_day' => 2,
            'compliance.max_calls_per_month' => 15,
            'sla.no_interaction_hours' => 24,
            'sla.negotiation_days' => 15,
            'sla.no_return_days' => 7,
            'sla.attempts_exhausted_threshold' => 7,
            'leads.archive_stale_months' => 12,
            'scoring.intent_decay_grace_days' => 7,
            'scoring.intent_decay_rate_per_day' => 2,
            'theme.primary_color' => '#4F46E5',
            'theme.secondary_color' => '#06B6D4',
            'google_places.cache_ttl_days' => 30,
            'google_places.monthly_budget_cap' => 500,
            'google_places.low_review_threshold' => 10,
            'google_places.low_rating_threshold' => 3.5,
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::query()->where('key', $key)->doesntExist()) {
                Setting::set($key, $value);
            }
        }
    }
}
