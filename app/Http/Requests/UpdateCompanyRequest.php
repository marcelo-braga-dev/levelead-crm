<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contraparte de StoreCompanyRequest para edição — mesmas regras, `unique` ignorando a própria
 * Company. Cobre Company + o lead em andamento (se houver) juntos (ver UpdateLeadWithCompanyAction).
 */
class UpdateCompanyRequest extends FormRequest
{
    public function rules(): array
    {
        $personType = $this->input('person_type');
        $company = $this->route('company');

        return [
            'person_type' => ['required', Rule::in(['pf', 'pj'])],
            'cnpj' => [Rule::requiredIf($personType === 'pj'), 'nullable', 'digits:14', Rule::unique('companies', 'cnpj')->ignore($company)],
            'cpf' => [Rule::requiredIf($personType === 'pf'), 'nullable', 'digits:11', Rule::unique('companies', 'cpf')->ignore($company)],
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
