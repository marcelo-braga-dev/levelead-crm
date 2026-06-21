<?php

namespace App\DomainServices;

use App\Enums\InteractionType;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadDistributionRule;
use App\Models\RoundRobinCursor;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Distribuição automática de leads sem consultor. Busca `lead_distribution_rules` ativas
 * por prioridade, monta o pool de consultores elegível pela primeira regra que casar com o
 * lead, e escolhe um via round-robin (cursor isolado por regra em `round_robin_cursors`).
 * Sem regra compatível, cai no round-robin global entre todos os consultores.
 *
 * `by_product` não é suportado: o Lead não tem um campo de "produto esperado" antes do Ganho
 * (só `won_product_id`, preenchido só ao ganhar) — não há o que casar. Regra com essa
 * estratégia é sempre pulada (ver CLAUDE.md).
 */
class LeadDistributionService
{
    /** UF => macrorregião do IBGE — fixo, não há coluna `region` em `states`. */
    private const BRAZIL_REGIONS = [
        'AC' => 'Norte', 'AP' => 'Norte', 'AM' => 'Norte', 'PA' => 'Norte', 'RO' => 'Norte', 'RR' => 'Norte', 'TO' => 'Norte',
        'AL' => 'Nordeste', 'BA' => 'Nordeste', 'CE' => 'Nordeste', 'MA' => 'Nordeste', 'PB' => 'Nordeste', 'PE' => 'Nordeste', 'PI' => 'Nordeste', 'RN' => 'Nordeste', 'SE' => 'Nordeste',
        'DF' => 'Centro-Oeste', 'GO' => 'Centro-Oeste', 'MT' => 'Centro-Oeste', 'MS' => 'Centro-Oeste',
        'ES' => 'Sudeste', 'MG' => 'Sudeste', 'RJ' => 'Sudeste', 'SP' => 'Sudeste',
        'PR' => 'Sul', 'RS' => 'Sul', 'SC' => 'Sul',
    ];

    public function assign(Lead $lead): ?LeadAssignment
    {
        $rules = LeadDistributionRule::query()->where('is_active', true)->orderByDesc('priority')->get();

        foreach ($rules as $rule) {
            if ($rule->strategy === 'manual') {
                continue;
            }

            $pool = $this->eligiblePool($rule, $lead);

            if ($pool->isEmpty()) {
                continue;
            }

            $user = $this->pickViaRoundRobin($pool, 'rule', $rule->id);

            return $this->createAssignment($lead, $user, $rule);
        }

        $globalPool = User::query()->where('role', UserRole::Consultant->value)->get();

        if ($globalPool->isEmpty()) {
            return null;
        }

        $user = $this->pickViaRoundRobin($globalPool, 'global', null);

        return $this->createAssignment($lead, $user, null);
    }

    /** @return Collection<int, User> */
    private function eligiblePool(LeadDistributionRule $rule, Lead $lead): Collection
    {
        return match ($rule->strategy) {
            'round_robin' => User::query()->where('role', UserRole::Consultant->value)->get(),
            'by_team' => $rule->team_id
                ? User::query()->where('role', UserRole::Consultant->value)->where('team_id', $rule->team_id)->get()
                : collect(),
            'by_state' => $this->poolByGeography($rule, $lead, exactState: true),
            'by_region' => $this->poolByGeography($rule, $lead, exactState: false),
            default => collect(), // by_product e demais estratégias não suportadas — ver docblock da classe.
        };
    }

    /** @return Collection<int, User> */
    private function poolByGeography(LeadDistributionRule $rule, Lead $lead, bool $exactState): Collection
    {
        $leadStateId = $lead->company?->address?->state_id;

        if (! $leadStateId || ! $rule->state_id) {
            return collect();
        }

        $matches = $exactState
            ? $leadStateId === $rule->state_id
            : $this->sameRegion($leadStateId, $rule->state_id);

        if (! $matches) {
            return collect();
        }

        $query = User::query()->where('role', UserRole::Consultant->value);

        if ($rule->team_id) {
            $query->where('team_id', $rule->team_id);
        }

        return $query->get();
    }

    private function sameRegion(int $leadStateId, int $ruleStateId): bool
    {
        if ($leadStateId === $ruleStateId) {
            return true;
        }

        $leadUf = State::find($leadStateId)?->uf;
        $ruleUf = State::find($ruleStateId)?->uf;

        if (! $leadUf || ! $ruleUf) {
            return false;
        }

        return (self::BRAZIL_REGIONS[$leadUf] ?? null) === (self::BRAZIL_REGIONS[$ruleUf] ?? null);
    }

    /** @param  Collection<int, User>  $pool */
    private function pickViaRoundRobin(Collection $pool, string $scopeType, ?int $scopeId): User
    {
        $cursor = RoundRobinCursor::firstOrCreate(['scope_type' => $scopeType, 'scope_id' => $scopeId]);
        $ids = $pool->pluck('id')->sort()->values();
        $lastIndex = $cursor->last_assigned_user_id ? $ids->search($cursor->last_assigned_user_id) : false;
        $nextIndex = $lastIndex === false ? 0 : ($lastIndex + 1) % $ids->count();
        $nextUserId = $ids[$nextIndex];

        $cursor->update(['last_assigned_user_id' => $nextUserId]);

        return $pool->firstWhere('id', $nextUserId);
    }

    private function createAssignment(Lead $lead, User $user, ?LeadDistributionRule $rule): LeadAssignment
    {
        return DB::transaction(function () use ($lead, $user, $rule) {
            $lead->update(['assigned_to' => $user->id, 'team_id' => $user->team_id]);

            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'assigned_by' => null,
                'lead_distribution_rule_id' => $rule?->id,
                'assigned_at' => now(),
            ]);

            $lead->interactions()->create([
                'type' => InteractionType::System->value,
                'description' => "Lead distribuído automaticamente para {$user->name}"
                    .($rule ? " (regra: {$rule->name})" : ' (round-robin global)'),
                'occurred_at' => now(),
            ]);

            return $assignment;
        });
    }
}
