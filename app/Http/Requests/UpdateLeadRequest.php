<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Atualiza apenas contato/qualificação do Lead — `stage` nunca passa por aqui, só pelo futuro
 * LeadStageTransitionService (Fase 3).
 */
class UpdateLeadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'interest_level' => ['nullable', 'string', 'max:255'],
            'purchase_potential' => ['nullable', 'string', 'max:255'],
            'qualification_notes' => ['nullable', 'string'],
        ];
    }
}
