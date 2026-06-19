<?php

namespace Database\Seeders;

use App\Enums\LossReason;
use App\Models\LossReasonRecycleRule;
use Illuminate\Database\Seeder;

class LossReasonRecycleRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['loss_reason' => LossReason::NoBudget, 'min' => 45, 'max' => 60, 'is_recyclable' => true],
            ['loss_reason' => LossReason::BadTiming, 'min' => 30, 'max' => 45, 'is_recyclable' => true],
            ['loss_reason' => LossReason::Competition, 'min' => 60, 'max' => 90, 'is_recyclable' => true],
            ['loss_reason' => LossReason::NoInterest, 'min' => null, 'max' => null, 'is_recyclable' => false],
            ['loss_reason' => LossReason::InvalidData, 'min' => null, 'max' => null, 'is_recyclable' => false],
            ['loss_reason' => LossReason::Other, 'min' => null, 'max' => null, 'is_recyclable' => true],
        ];

        foreach ($rules as $rule) {
            LossReasonRecycleRule::query()->updateOrCreate(
                ['loss_reason' => $rule['loss_reason']],
                [
                    'suggested_recycle_days_min' => $rule['min'],
                    'suggested_recycle_days_max' => $rule['max'],
                    'is_recyclable' => $rule['is_recyclable'],
                ],
            );
        }
    }
}
