<?php

namespace App\Http\Controllers\Kanban;

use App\DomainServices\LeadStageTransitionService;
use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateLeadStageRequest;
use App\Http\Requests\TransitionLeadStageRequest;
use App\Models\Lead;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadStageController extends Controller
{
    public function update(TransitionLeadStageRequest $request, Lead $lead, LeadStageTransitionService $service): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $request->validated();

        try {
            $service->transition($lead, LeadStage::from($data['to_stage']), [
                'reason' => $data['reason'] ?? null,
                'changed_by' => $request->user()->id,
                'loss_reason' => $data['loss_reason'] ?? null,
                'loss_notes' => $data['loss_notes'] ?? null,
                'won_value' => $data['won_value'] ?? null,
                'won_product_id' => $data['won_product_id'] ?? null,
            ]);
        } catch (DomainException $e) {
            return back()->withErrors(['to_stage' => $e->getMessage()]);
        }

        return back()->with('status', 'Etapa do lead atualizada.');
    }

    /**
     * Mesmo princípio do `bulkAssign`: uma transição inválida para 1 lead (grafo da
     * `LeadStageTransitionService` ou RBAC) não aborta o lote, só entra na contagem de falha.
     */
    public function bulkUpdate(BulkUpdateLeadStageRequest $request, LeadStageTransitionService $service): RedirectResponse
    {
        $data = $request->validated();
        $toStage = LeadStage::from($data['to_stage']);
        $leads = Lead::query()->whereIn('id', $data['lead_ids'])->get();

        $succeeded = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            try {
                Gate::authorize('update', $lead);
                $service->transition($lead, $toStage, ['changed_by' => $request->user()->id]);
                $succeeded++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Falha ao mover lead em massa: '.$e->getMessage(), ['lead_id' => $lead->id]);
            }
        }

        return back()->with('status', "{$succeeded} lead(s) movido(s) para {$toStage->label()}.".($failed > 0 ? " {$failed} falharam." : ''));
    }
}
