<?php

namespace App\Http\Requests;

use App\Enums\LeadStage;
use App\Enums\LossReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionLeadStageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'to_stage' => ['required', Rule::in(array_column(LeadStage::cases(), 'value'))],
            'reason' => ['nullable', 'string'],
            'loss_reason' => ['nullable', 'required_if:to_stage,lost', Rule::in(array_column(LossReason::cases(), 'value'))],
            'loss_notes' => ['nullable', 'string'],
            'won_value' => ['nullable', 'required_if:to_stage,won', 'numeric', 'min:0'],
            'won_product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}
