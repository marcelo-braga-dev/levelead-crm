<?php

namespace App\Actions\Leads;

use App\Enums\InteractionType;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Atribuição/transferência manual de consultor — mesma operação independente de o lead já
 * estar com outro consultor (transferência) ou sem nenhum (atribuição). O plano original
 * previa `AssignLeadAction`/`TransferLeadAction` como duas Actions, mas o corpo seria
 * idêntico (atualizar `assigned_to`/`team_id`, gravar `lead_assignments`, registrar no
 * histórico unificado) — então existe só esta, reaproveitada para os dois casos.
 */
class AssignLeadAction
{
    public function execute(Lead $lead, User $toUser, User $actor): LeadAssignment
    {
        return DB::transaction(function () use ($lead, $toUser, $actor) {
            $fromUser = $lead->assignedTo;

            $lead->update(['assigned_to' => $toUser->id, 'team_id' => $toUser->team_id]);

            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'user_id' => $toUser->id,
                'assigned_by' => $actor->id,
                'lead_distribution_rule_id' => null,
                'assigned_at' => now(),
            ]);

            $description = $fromUser
                ? "Lead transferido de {$fromUser->name} para {$toUser->name} por {$actor->name}."
                : "Lead atribuído a {$toUser->name} por {$actor->name}.";

            $lead->interactions()->create([
                'user_id' => $actor->id,
                'type' => InteractionType::System->value,
                'description' => $description,
                'occurred_at' => now(),
            ]);

            return $assignment;
        });
    }
}
