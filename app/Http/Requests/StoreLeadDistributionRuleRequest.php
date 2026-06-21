<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadDistributionRuleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'strategy' => ['required', Rule::in(['manual', 'round_robin', 'by_team', 'by_region', 'by_state', 'by_product'])],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'priority' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
