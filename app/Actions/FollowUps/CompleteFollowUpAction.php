<?php

namespace App\Actions\FollowUps;

use App\DomainServices\LeadScoringService;
use App\Enums\InteractionType;
use App\Models\FollowUp;
use DomainException;
use Illuminate\Support\Facades\DB;

class CompleteFollowUpAction
{
    public function __construct(private LeadScoringService $scoring) {}

    /** @param  array{user_id?: ?int}  $context */
    public function execute(FollowUp $followUp, array $context = []): FollowUp
    {
        if ($followUp->status === 'done') {
            throw new DomainException('Este follow-up já está concluído.');
        }

        return DB::transaction(function () use ($followUp, $context) {
            $followUp->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);

            $followUp->lead->interactions()->create([
                'user_id' => $context['user_id'] ?? null,
                'type' => InteractionType::FollowUp->value,
                'description' => $followUp->notes ?: 'Follow-up concluído.',
                'occurred_at' => now(),
            ]);

            $this->scoring->recalculate($followUp->lead);

            return $followUp;
        });
    }
}
