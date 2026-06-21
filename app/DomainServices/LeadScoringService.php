<?php

namespace App\DomainServices;

use App\Enums\InteractionType;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadScoringRule;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * `fit_score`: soma os pesos de `lead_scoring_rules` ativas cujo critério casa com a Company
 * vinculada ao lead (porte/faixa de faturamento/CNAE/estado/regime tributário/perfil do
 * Google via `company_places_profiles`) — configurável via banco, não recalibrado em código.
 *
 * `intent_score`: fórmula fixa (não configurável no MVP, ver docblock de calculateIntentScore).
 *
 * `total_score` = fit + intent, **soma simples sem normalização** (pode passar de 100) — os
 * thresholds Hot/Warm/Cold abaixo são os valores provisórios do plano original, marcados para
 * calibrar depois com dados reais.
 */
class LeadScoringService
{
    private const RECENT_INTERACTIONS_WINDOW_DAYS = 30;

    private const RECENT_INTERACTIONS_MAX_COUNTED = 5;

    private const POINTS_PER_RECENT_INTERACTION = 10;

    private const POINTS_FOR_ACTIVE_PROPOSAL = 30;

    public function recalculate(Lead $lead): Lead
    {
        $fit = $this->calculateFitScore($lead);
        $intent = $this->calculateIntentScore($lead);

        $lead->update([
            'fit_score' => $fit,
            'intent_score' => $intent,
            'total_score' => $fit + $intent,
            'score_updated_at' => now(),
        ]);

        return $lead->refresh();
    }

    public function calculateFitScore(Lead $lead): int
    {
        $company = $lead->company;

        if ($company === null) {
            return 0;
        }

        $score = LeadScoringRule::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (LeadScoringRule $rule) => $this->ruleMatches($rule, $company))
            ->sum('score_weight');

        return max(0, min(100, (int) $score));
    }

    /**
     * Pontos por interação recente (até 5, 10 pts cada) + proposta ativa (30 pts), com
     * decaimento linear a partir do fim da janela de carência sem interação — fórmula fixa,
     * não configurável via `lead_scoring_rules` (essa tabela só alimenta o fit score), mas a
     * janela de carência e a taxa de decaimento são `Setting` (`scoring.intent_decay_grace_days`/
     * `scoring.intent_decay_rate_per_day`, Hardening pós-MVP H2 — defaults 7 dias / 2 pts/dia).
     */
    public function calculateIntentScore(Lead $lead): int
    {
        $score = 0;

        // Só conta toques reais de consultor — exclui `system`/`stage_change` (logs
        // administrativos automáticos, não sinal de interesse do comprador).
        $recentInteractions = $lead->interactions()
            ->whereIn('type', [
                InteractionType::Call->value,
                InteractionType::Whatsapp->value,
                InteractionType::Email->value,
                InteractionType::Visit->value,
                InteractionType::Note->value,
                InteractionType::FollowUp->value,
            ])
            ->where('occurred_at', '>=', now()->subDays(self::RECENT_INTERACTIONS_WINDOW_DAYS))
            ->count();

        $score += min($recentInteractions, self::RECENT_INTERACTIONS_MAX_COUNTED) * self::POINTS_PER_RECENT_INTERACTION;

        if ($lead->proposals()->where('status', 'active')->exists()) {
            $score += self::POINTS_FOR_ACTIVE_PROPOSAL;
        }

        if ($lead->last_interaction_at !== null) {
            $inactiveDays = Carbon::now()->diffInDays($lead->last_interaction_at, absolute: true);
            $graceDays = Setting::get('scoring.intent_decay_grace_days', 7);

            if ($inactiveDays > $graceDays) {
                $score -= ($inactiveDays - $graceDays) * Setting::get('scoring.intent_decay_rate_per_day', 2);
            }
        }

        return max(0, min(100, $score));
    }

    private function ruleMatches(LeadScoringRule $rule, Company $company): bool
    {
        return match ($rule->criterion) {
            'company_size' => $company->company_size === $rule->criterion_value,
            'tax_regime' => $company->tax_regime === $rule->criterion_value,
            'state' => $company->address?->state?->uf === $rule->criterion_value,
            'cnae' => $company->primaryCnae?->code === $rule->criterion_value,
            'revenue_range' => $this->matchesRevenueRange($rule->criterion_value, $company->estimated_revenue_value),
            'has_google_profile' => $company->placesProfile !== null,
            'google_rating_above' => (float) ($company->placesProfile?->rating ?? 0) >= (float) $rule->criterion_value,
            'google_review_count_above' => ($company->placesProfile?->user_rating_count ?? 0) >= (int) $rule->criterion_value,
            default => false,
        };
    }

    /**
     * `criterion_value` no formato "min-max" (ex.: "0-360000", "360000-4800000", "4800000-"
     * para faixa aberta acima). Lado vazio = sem limite naquela ponta.
     */
    private function matchesRevenueRange(string $criterionValue, ?float $revenue): bool
    {
        if ($revenue === null) {
            return false;
        }

        [$min, $max] = array_pad(explode('-', $criterionValue, 2), 2, '');

        if ($min !== '' && $revenue < (float) $min) {
            return false;
        }

        if ($max !== '' && $revenue > (float) $max) {
            return false;
        }

        return true;
    }
}
