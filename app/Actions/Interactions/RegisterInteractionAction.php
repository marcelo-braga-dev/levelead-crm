<?php

namespace App\Actions\Interactions;

use App\DomainServices\ComplianceChecker;
use App\DomainServices\LeadScoringService;
use App\Enums\InteractionChannel;
use App\Enums\InteractionDirection;
use App\Enums\InteractionType;
use App\Models\Lead;
use App\Models\LeadInteraction;
use Illuminate\Support\Carbon;

/**
 * Único caminho para registrar uma interação manual (call/whatsapp/email/visit/note) no
 * histórico único do card. Roda a `ComplianceChecker` antes de persistir: se houver avisos
 * e a interação não vier `confirmed`, não salva nada — devolve os avisos para o consultor
 * decidir (avisar e confirmar, nunca bloquear).
 */
class RegisterInteractionAction
{
    private const CHANNEL_TYPES = [
        InteractionType::Call,
        InteractionType::Whatsapp,
        InteractionType::Email,
        InteractionType::Visit,
    ];

    public function __construct(
        private ComplianceChecker $checker,
        private LeadScoringService $scoring,
    ) {}

    /**
     * @param  array{type: string, direction?: ?string, description?: ?string, phone_dialed?: ?string, confirmed?: bool}  $data
     * @return array{interaction: ?LeadInteraction, warnings: string[]}
     */
    public function execute(Lead $lead, array $data, int $userId): array
    {
        $type = InteractionType::from($data['type']);
        $channel = in_array($type, self::CHANNEL_TYPES, true) ? InteractionChannel::from($type->value) : null;
        $phoneDialed = $data['phone_dialed'] ?? null;

        $warnings = $this->checker->check($lead, $type, $channel, $phoneDialed);

        if ($warnings !== [] && empty($data['confirmed'])) {
            return ['interaction' => null, 'warnings' => $warnings];
        }

        $occurredAt = Carbon::now();
        $direction = $channel !== null ? ($data['direction'] ?? InteractionDirection::Outbound->value) : null;

        $interaction = $lead->interactions()->create([
            'user_id' => $userId,
            'type' => $type->value,
            'channel' => $channel?->value,
            'direction' => $direction,
            'description' => $data['description'] ?? null,
            'phone_dialed' => $phoneDialed,
            'occurred_at' => $occurredAt,
        ]);

        $attributes = ['last_interaction_at' => $occurredAt];

        if ($channel !== null
            && in_array($channel, [InteractionChannel::Call, InteractionChannel::Whatsapp], true)
            && $direction === InteractionDirection::Outbound->value) {
            $attributes['contact_attempts_count'] = $lead->contact_attempts_count + 1;
        }

        $lead->update($attributes);
        $this->scoring->recalculate($lead);

        return ['interaction' => $interaction, 'warnings' => []];
    }
}
