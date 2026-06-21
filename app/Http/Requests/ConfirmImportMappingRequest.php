<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmImportMappingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
            'save_as_profile' => ['nullable', 'boolean'],
            'profile_name' => ['required_if:save_as_profile,true', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $targets = array_values(array_filter((array) $this->input('mapping')));

            if (! in_array('cnpj', $targets, true) || ! in_array('razao_social', $targets, true)) {
                $validator->errors()->add('mapping', 'É preciso mapear uma coluna para CNPJ e outra para Razão Social.');
            }
        });
    }
}
