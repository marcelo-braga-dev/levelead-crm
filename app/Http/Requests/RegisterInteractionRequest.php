<?php

namespace App\Http\Requests;

use App\Enums\InteractionDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterInteractionRequest extends FormRequest
{
    /**
     * Tipos que um consultor pode registrar manualmente — `stage_change`/`follow_up`/`system`
     * são gerados automaticamente pelas respectivas Actions, nunca por este endpoint.
     */
    private const LOGGABLE_TYPES = ['call', 'whatsapp', 'email', 'visit', 'note'];

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(self::LOGGABLE_TYPES)],
            'direction' => ['nullable', Rule::in(array_column(InteractionDirection::cases(), 'value'))],
            'description' => ['nullable', 'string'],
            'phone_dialed' => ['nullable', 'string', 'max:30'],
            'confirmed' => ['nullable', 'boolean'],
        ];
    }
}
