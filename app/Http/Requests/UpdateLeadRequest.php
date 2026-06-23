<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Escopo estreito de propósito: só os campos de contato/qualificação do Lead, nunca `stage`
 * (isso passa por LeadStageTransitionService) nem dados cadastrais da Company (isso é
 * `UpdateCompanyRequest`/`companies.update`, admin/manager apenas). Existe como rota separada
 * porque `LeadPolicy::update()` permite o próprio consultor responsável editar esses campos do
 * seu lead — `CompanyPolicy::update()` (usada por `companies.update`) é admin/manager apenas.
 */
class UpdateLeadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
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
