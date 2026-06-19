<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['auditable_type', 'auditable_id', 'action', 'old_values', 'new_values', 'actor_type', 'actor_id', 'actor_label', 'ip_address'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'actor_type' => AuditActorType::class,
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
