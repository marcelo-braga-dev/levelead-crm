<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkAssignLeadsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
