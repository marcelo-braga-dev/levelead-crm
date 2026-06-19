<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'user_id', 'assigned_by', 'lead_distribution_rule_id', 'assigned_at'])]
class LeadAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function distributionRule(): BelongsTo
    {
        return $this->belongsTo(LeadDistributionRule::class, 'lead_distribution_rule_id');
    }
}
