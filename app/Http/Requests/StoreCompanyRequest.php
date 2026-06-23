<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lead PF ou PJ no mesmo formulário — `person_type` decide qual identificador é obrigatório.
 * Cobre Company + o primeiro Lead juntos (ver CreateLeadWithCompanyAction).
 */
class StoreCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        $personType = $this->input('person_type');

        return [
            'person_type' => ['required', Rule::in(['pf', 'pj'])],
            'cnpj' => [Rule::requiredIf($personType === 'pj'), 'nullable', 'digits:14', 'unique:companies,cnpj'],
            'cpf' => [Rule::requiredIf($personType === 'pf'), 'nullable', 'digits:11', 'unique:companies,cpf'],
            'razao_social' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_whatsapp' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'interest_level' => ['nullable', 'string', 'max:255'],
            'purchase_potential' => ['nullable', 'string', 'max:255'],
            'qualification_notes' => ['nullable', 'string'],
        ];
    }
}
