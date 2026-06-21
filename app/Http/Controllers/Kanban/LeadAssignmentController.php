<?php

namespace App\Http\Controllers\Kanban;

use App\Actions\Leads\AssignLeadAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignLeadRequest;
use App\Http\Requests\BulkAssignLeadsRequest;
use App\Jobs\DistributeUnassignedLeadsJob;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class LeadAssignmentController extends Controller
{
    public function store(AssignLeadRequest $request, Lead $lead, AssignLeadAction $action): RedirectResponse
    {
        Gate::authorize('assign', $lead);

        $toUser = User::findOrFail($request->validated('user_id'));
        $action->execute($lead, $toUser, $request->user());

        return back()->with('status', "Lead atribuído a {$toUser->name}.");
    }

    /**
     * Distribuição em massa não é sobre um Lead específico (a ability `assign` da
     * LeadPolicy só depende do papel do usuário, não do lead) — checa o papel direto.
     */
    public function distributeNow(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, [UserRole::Admin, UserRole::Manager], true), 403);

        $distributed = DistributeUnassignedLeadsJob::dispatchSync();

        return back()->with('status', "{$distributed} lead(s) distribuído(s).");
    }

    /**
     * Reatribuição em massa reaproveita `AssignLeadAction` por lead — uma falha isolada
     * (RBAC ou lead inexistente) não aborta o lote (mesmo princípio da H1 nos jobs agendados).
     */
    public function bulkAssign(BulkAssignLeadsRequest $request, AssignLeadAction $action): RedirectResponse
    {
        $toUser = User::findOrFail($request->validated('user_id'));
        $leads = Lead::query()->whereIn('id', $request->validated('lead_ids'))->get();

        $succeeded = 0;
        $failed = 0;

        foreach ($leads as $lead) {
            try {
                Gate::authorize('assign', $lead);
                $action->execute($lead, $toUser, $request->user());
                $succeeded++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Falha ao reatribuir lead em massa: '.$e->getMessage(), ['lead_id' => $lead->id]);
            }
        }

        return back()->with('status', "{$succeeded} lead(s) reatribuído(s) a {$toUser->name}.".($failed > 0 ? " {$failed} falharam." : ''));
    }
}
