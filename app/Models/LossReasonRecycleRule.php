<?php

namespace App\Models;

use App\Enums\LossReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['loss_reason', 'suggested_recycle_days_min', 'suggested_recycle_days_max', 'is_recyclable', 'notes'])]
class LossReasonRecycleRule extends Model
{
    protected function casts(): array
    {
        return [
            'loss_reason' => LossReason::class,
            'is_recyclable' => 'boolean',
        ];
    }
}
