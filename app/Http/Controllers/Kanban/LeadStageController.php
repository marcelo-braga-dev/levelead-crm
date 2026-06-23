<?php

namespace App\Http\Controllers\Kanban;

use App\DomainServices\LeadStageTransitionService;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpdateLeadStageRequest;
use App\Http\Requests\ConfirmWonValueRequest;
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
        $canSetWonValue = in_array($request->user()->role, [UserRole::Admin, UserRole::Manager], true);

        try {
            $service->transition($lead, LeadStage::from($data['to_stage']), [
                'reason' => $data['reason'] ?? null,
                'changed_by' => $request->user()->id,
                'loss_reason' => $data['loss_reason'] ?? null,
                'loss_notes' => $data['loss_notes'] ?? null,
                // Consultor pode mover para Ganho, mas não fecha o valor sozinho — ver
                // LeadPolicy::confirmWonValue(). Ignorado aqui mesmo que enviado na request.
                'won_value' => $canSetWonValue ? ($data['won_value'] ?? null) : null,
                'won_product_id' => $canSetWonValue ? ($data['won_product_id'] ?? null) : null,
            ]);
        } catch (DomainException $e) {
            return back()->withErrors(['to_stage' => $e->getMessage()]);
        }

        return back()->with('status', 'Etapa do lead atualizada.');
    }

    public function confirmWonValue(ConfirmWonValueRequest $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('confirmWonValue', $lead);

        if ($lead->stage !== LeadStage::Won) {
            return back()->withErrors(['won_value' => 'O lead precisa estar na etapa Ganho para confirmar o valor.']);
        }

        $lead->update($request->validated());

        return back()->with('status', 'Valor de fechamento confirmado.');
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
