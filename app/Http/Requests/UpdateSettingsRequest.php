<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'google_places_api_key' => ['nullable', 'string', 'max:255'],
            'compliance_max_calls_per_day' => ['required', 'integer', 'min:1'],
            'compliance_max_calls_per_month' => ['required', 'integer', 'min:1'],
            'sla_no_interaction_hours' => ['required', 'integer', 'min:1'],
            'sla_negotiation_days' => ['required', 'integer', 'min:1'],
            'sla_no_return_days' => ['required', 'integer', 'min:1'],
            'sla_attempts_exhausted_threshold' => ['required', 'integer', 'min:1'],
            'leads_archive_stale_months' => ['required', 'integer', 'min:1'],
            'scoring_intent_decay_grace_days' => ['required', 'integer', 'min:0'],
            'scoring_intent_decay_rate_per_day' => ['required', 'numeric', 'min:0'],
            'google_places_monthly_budget_cap' => ['required', 'integer', 'min:0'],
            'google_places_low_rating_threshold' => ['required', 'numeric', 'min:0', 'max:5'],
            'google_places_low_review_threshold' => ['required', 'integer', 'min:0'],
        ];
    }
}
