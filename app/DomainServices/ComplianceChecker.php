<?php

namespace App\DomainServices;

use App\Enums\InteractionChannel;
use App\Enums\InteractionType;
use App\Enums\RegistrationStatus;
use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\OptOut;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Camada de Compliance (LGPD/ANATEL) do MVP: opt-out, janela de ligação, situação
 * cadastral da empresa e frequência de discagem por número. Todas as checagens aqui
 * só geram **avisos** — quem decide se confirma é o consultor (ver CLAUDE.md).
 */
class ComplianceChecker
{
    public function __construct(private CallWindowValidator $callWindowValidator) {}

    /** @return string[] */
    public function check(Lead $lead, InteractionType $type, ?InteractionChannel $channel, ?string $phoneDialed): array
    {
        $warnings = [];

        if (in_array($channel, [InteractionChannel::Call, InteractionChannel::Whatsapp], true)) {
            if ($optOut = $this->matchOptOut($lead, $phoneDialed)) {
                $warnings[] = 'Este contato solicitou opt-out em '.$optOut->requested_at->format('d/m/Y')
                    .($optOut->reason ? " (motivo: {$optOut->reason})" : '').'.';
            }
        }

        if ($type === InteractionType::Call) {
            if ($reason = $this->callWindowValidator->reason(Carbon::now())) {
                $warnings[] = $reason;
            }

            if (in_array($lead->company->registration_status, [RegistrationStatus::Closed, RegistrationStatus::Unfit], true)) {
                $warnings[] = "Empresa com situação cadastral '{$lead->company->registration_status->value}' na Receita Federal.";
            }

            if ($phoneDialed) {
                $maxPerDay = Setting::get('compliance.max_calls_per_day', 2);
                $maxPerMonth = Setting::get('compliance.max_calls_per_month', 15);

                $today = $this->callCount($phoneDialed, Carbon::today(), Carbon::tomorrow());
                $month = $this->callCount($phoneDialed, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

                if ($today >= $maxPerDay) {
                    $warnings[] = "Este número já recebeu {$today} ligação(ões) hoje (limite recomendado: {$maxPerDay}/dia).";
                }

                if ($month >= $maxPerMonth) {
                    $warnings[] = "Este número já recebeu {$month} ligação(ões) este mês (limite recomendado: {$maxPerMonth}/mês).";
                }
            }
        }

        return $warnings;
    }

    private function matchOptOut(Lead $lead, ?string $phoneDialed): ?OptOut
    {
        $phones = array_filter(array_unique([$phoneDialed, $lead->contact_phone, $lead->contact_whatsapp]));

        return OptOut::query()
            ->where(function ($query) use ($lead, $phones) {
                $query->where('company_id', $lead->company_id);

                foreach ($phones as $phone) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->latest('requested_at')
            ->first();
    }

    private function callCount(string $phoneDialed, Carbon $start, Carbon $end): int
    {
        return LeadInteraction::query()
            ->where('phone_dialed', $phoneDialed)
            ->where('type', InteractionType::Call->value)
            ->whereBetween('occurred_at', [$start, $end])
            ->count();
    }
}
