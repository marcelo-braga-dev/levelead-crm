<?php

namespace App\Models;

use App\Enums\LeadStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lead_id', 'from_stage', 'to_stage', 'changed_by', 'reason'])]
class LeadStageHistory extends Model
{
    // "History" pluraliza para "Histories" por padrão — a tabela usa o nome singular do plano.
    protected $table = 'lead_stage_history';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_stage' => LeadStage::class,
            'to_stage' => LeadStage::class,
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
