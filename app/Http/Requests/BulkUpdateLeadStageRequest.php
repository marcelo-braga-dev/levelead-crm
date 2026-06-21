<?php

namespace App\Http\Requests;

use App\Enums\LeadStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Etapas terminais (Won/Lost) ficam de fora do bulk — exigem motivo/valor por lead, não fazem
 * sentido em lote (corte de escopo deliberado, ver CLAUDE.md/PLANO_IMPLEMENTACAO.md).
 */
class BulkUpdateLeadStageRequest extends FormRequest
{
    public function rules(): array
    {
        $nonTerminalStages = array_values(array_filter(
            array_column(LeadStage::cases(), 'value'),
            fn (string $value) => ! LeadStage::from($value)->isTerminal(),
        ));

        return [
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'exists:leads,id'],
            'to_stage' => ['required', Rule::in($nonTerminalStages)],
        ];
    }
}
