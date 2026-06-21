<?php

namespace App\Actions\SlaAlerts;

use App\Enums\LeadStage;
use App\Enums\SlaAlertType;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\SlaAlert;
use App\Models\User;
use App\Notifications\SlaAlertRaised;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Avalia as 4 condições de SLA do plano para um lead e mantém `sla_alerts` em sincronia:
 * cria um alerta novo quando a condição passa a se aplicar (idempotente — só um alerta
 * `resolved_at IS NULL` por tipo por lead) e resolve automaticamente quando a condição deixa
 * de se aplicar (ex.: consultor registra uma interação e o alerta de inatividade desaparece).
 *
 * MVP só alerta — nunca move o lead automaticamente, mesmo em `attempts_exhausted`.
 *
 * Limiares vêm de `Setting` (Hardening pós-MVP H2) — `Setting::get()` já cacheia, então não há
 * ida ao banco extra por avaliação além da 1ª de cada chave.
 */
class EvaluateSlaForLeadAction
{
    /** @return SlaAlert[] alertas recém-criados (para quem quiser contar/relatar) */
    public function execute(Lead $lead): array
    {
        if ($lead->stage->isTerminal() || $lead->archived_at !== null) {
            return [];
        }

        $created = [];

        foreach ($this->applicability($lead) as $typeValue => $applies) {
            $existing = $lead->slaAlerts()->unresolved()->where('type', $typeValue)->first();

            if ($applies) {
                if ($existing === null) {
                    $alert = $lead->slaAlerts()->create(['type' => $typeValue]);
                    $this->notifyManagers($alert);
                    $created[] = $alert;
                }
            } elseif ($existing !== null) {
                $existing->update(['resolved_at' => now()]);
            }
        }

        return $created;
    }

    /** @return array<string, bool> chaveado pelo valor (string) de SlaAlertType — enums não podem ser chave de array em PHP */
    private function applicability(Lead $lead): array
    {
        return [
            SlaAlertType::NoInteraction24h->value => $this->noInteraction24h($lead),
            SlaAlertType::Negotiation15d->value => $this->negotiation15d($lead),
            SlaAlertType::NoReturn7d->value => $this->noReturn7d($lead),
            SlaAlertType::AttemptsExhausted->value => $this->attemptsExhausted($lead),
        ];
    }

    private function noInteraction24h(Lead $lead): bool
    {
        $reference = $lead->last_interaction_at ?? $lead->created_at;
        $hours = Setting::get('sla.no_interaction_hours', 24);

        return $reference->lt(now()->subHours($hours));
    }

    private function negotiation15d(Lead $lead): bool
    {
        $days = Setting::get('sla.negotiation_days', 15);

        return $lead->stage === LeadStage::Negotiation
            && $lead->stage_entered_at !== null
            && $lead->stage_entered_at->lt(now()->subDays($days));
    }

    private function noReturn7d(Lead $lead): bool
    {
        if ($lead->stage !== LeadStage::ProposalSent) {
            return false;
        }

        $reference = $lead->last_interaction_at ?? $lead->stage_entered_at;
        $days = Setting::get('sla.no_return_days', 7);

        return $reference !== null && $reference->lt(now()->subDays($days));
    }

    private function attemptsExhausted(Lead $lead): bool
    {
        return $lead->contact_attempts_count >= Setting::get('sla.attempts_exhausted_threshold', 7);
    }

    private function notifyManagers(SlaAlert $alert): void
    {
        $managers = User::query()->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])->get();

        if ($managers->isEmpty()) {
            return;
        }

        try {
            Notification::send($managers, new SlaAlertRaised($alert));
        } catch (\Throwable $e) {
            // Não deixa um SMTP mal configurado (comum em dev/staging) impedir a criação do
            // alerta em si, que é o dado importante. A notificação é um canal auxiliar.
            Log::warning('Falha ao notificar gestores sobre sla_alert: '.$e->getMessage(), ['sla_alert_id' => $alert->id]);
        }
    }
}
