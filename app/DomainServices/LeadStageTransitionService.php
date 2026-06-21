<?php

namespace App\DomainServices;

use App\Enums\InteractionType;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\LeadStageHistory;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Único caminho permitido para alterar `leads.stage` (ver CLAUDE.md). Garante que toda
 * transição respeita o grafo fechado no plano, que retrocessos exigem motivo, e que
 * `lead_stage_history` (trilha técnica) e `lead_interactions` (histórico único do card)
 * são sempre gravados atomicamente junto com a mudança — a entrada em `audit_logs` vem
 * de graça via a trait `Auditable` já aplicada a `Lead`.
 */
class LeadStageTransitionService
{
    /** @var array<string, array<int, string>> */
    private const TRANSITIONS = [
        'new' => ['attempting_contact', 'lost'],
        'attempting_contact' => ['contact_made', 'lost'],
        'contact_made' => ['qualified', 'lost', 'attempting_contact'],
        'qualified' => ['proposal_sent', 'lost', 'contact_made'],
        'proposal_sent' => ['negotiation', 'lost', 'qualified'],
        'negotiation' => ['won', 'lost', 'proposal_sent'],
        'won' => [],
        'lost' => [],
    ];

    /** Ordem "para frente" do funil — usada só para detectar retrocesso (Lost não entra aqui). */
    private const FORWARD_ORDER = [
        'new', 'attempting_contact', 'contact_made', 'qualified', 'proposal_sent', 'negotiation', 'won',
    ];

    /** @param array{reason?: ?string, changed_by?: ?int, loss_reason?: ?string, loss_notes?: ?string, won_value?: ?string, won_product_id?: ?int} $context */
    public function transition(Lead $lead, LeadStage $to, array $context = []): Lead
    {
        $from = $lead->stage;

        if (! in_array($to->value, self::TRANSITIONS[$from->value], true)) {
            throw new DomainException("Transição inválida: {$from->label()} → {$to->label()}.");
        }

        if ($this->isRegression($from, $to) && blank($context['reason'] ?? null)) {
            throw new DomainException('Retroceder de etapa exige um motivo.');
        }

        $attributes = [
            'stage' => $to->value,
            'stage_entered_at' => now(),
        ];

        if ($to === LeadStage::Lost) {
            $attributes['loss_reason'] = $context['loss_reason'] ?? null;
            $attributes['loss_notes'] = $context['loss_notes'] ?? null;
        }

        if ($to === LeadStage::Won) {
            $attributes['won_value'] = $context['won_value'] ?? null;
            $attributes['won_product_id'] = $context['won_product_id'] ?? null;
        }

        DB::transaction(function () use ($lead, $from, $to, $context, $attributes) {
            $lead->update($attributes);

            LeadStageHistory::create([
                'lead_id' => $lead->id,
                'from_stage' => $from->value,
                'to_stage' => $to->value,
                'changed_by' => $context['changed_by'] ?? null,
                'reason' => $context['reason'] ?? null,
            ]);

            $lead->interactions()->create([
                'user_id' => $context['changed_by'] ?? null,
                'type' => InteractionType::StageChange->value,
                'description' => trim("{$from->label()} → {$to->label()}".(blank($context['reason'] ?? null) ? '' : " — {$context['reason']}")),
                'occurred_at' => now(),
            ]);

            // Won/Lost são terminais: nenhum alerta de SLA continua relevante depois disso,
            // e o job de avaliação periódica nem chega a olhar leads terminais para resolvê-los.
            if ($to->isTerminal()) {
                $lead->slaAlerts()->unresolved()->update(['resolved_at' => now()]);
            }
        });

        return $lead->refresh();
    }

    private function isRegression(LeadStage $from, LeadStage $to): bool
    {
        $fromIndex = array_search($from->value, self::FORWARD_ORDER, true);
        $toIndex = array_search($to->value, self::FORWARD_ORDER, true);

        if ($fromIndex === false || $toIndex === false) {
            return false;
        }

        return $toIndex < $fromIndex;
    }
}
