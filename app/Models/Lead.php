<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\LeadStage;
use App\Enums\LossReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id', 'stage', 'assigned_to', 'team_id', 'lead_source_id',
    'contact_name', 'contact_phone', 'contact_whatsapp', 'contact_email',
    'interest_level', 'purchase_potential', 'qualification_notes',
    'loss_reason', 'loss_notes',
    'won_value', 'won_product_id', 'won_commission_value',
    'is_recycled', 'recycled_from_lead_id',
    'last_interaction_at', 'stage_entered_at', 'contact_attempts_count',
    'fit_score', 'intent_score', 'total_score', 'score_updated_at',
    'archived_at', 'external_ref',
])]
class Lead extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'stage' => LeadStage::class,
            'loss_reason' => LossReason::class,
            'won_value' => 'decimal:2',
            'won_commission_value' => 'decimal:2',
            'is_recycled' => 'boolean',
            'last_interaction_at' => 'datetime',
            'stage_entered_at' => 'datetime',
            'score_updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function wonProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'won_product_id');
    }

    public function recycledFrom(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'recycled_from_lead_id');
    }

    public function recycledTo(): HasMany
    {
        return $this->hasMany(Lead::class, 'recycled_from_lead_id');
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(LeadStageHistory::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(LeadInteraction::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function slaAlerts(): HasMany
    {
        return $this->hasMany(SlaAlert::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }
}
