<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmWonValueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'won_value' => ['required', 'numeric', 'min:0'],
            'won_product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}
