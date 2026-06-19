<?php

namespace App\Models;

use App\Enums\InteractionChannel;
use App\Enums\InteractionDirection;
use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lead_id', 'user_id', 'type', 'channel', 'direction', 'description', 'occurred_at',
    'duration_seconds', 'hold_time_seconds', 'recording_url', 'outcome_code',
    'phone_dialed', 'external_provider_ref', 'channel_metadata',
])]
class LeadInteraction extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'channel' => InteractionChannel::class,
            'direction' => InteractionDirection::class,
            'occurred_at' => 'datetime',
            'channel_metadata' => 'array',
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
}
