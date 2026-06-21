<?php

namespace App\Actions\FollowUps;

use App\Models\FollowUp;
use App\Models\Lead;

class CreateFollowUpAction
{
    /** @param  array{scheduled_at: string, notes?: ?string, created_by?: ?int}  $data */
    public function execute(Lead $lead, array $data): FollowUp
    {
        return $lead->followUps()->create([
            'created_by' => $data['created_by'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);
    }
}
