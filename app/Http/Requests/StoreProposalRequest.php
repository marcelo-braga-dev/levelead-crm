<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProposalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ];
    }
}
