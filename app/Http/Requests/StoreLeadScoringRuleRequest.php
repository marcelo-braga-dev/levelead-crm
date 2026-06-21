<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadScoringRuleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'criterion' => ['required', 'string', 'max:255'],
            'criterion_value' => ['required', 'string', 'max:255'],
            'score_weight' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
