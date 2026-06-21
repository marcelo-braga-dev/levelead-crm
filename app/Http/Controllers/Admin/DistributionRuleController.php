<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadDistributionRuleRequest;
use App\Models\LeadDistributionRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DistributionRuleController extends Controller
{
    public function store(StoreLeadDistributionRuleRequest $request): RedirectResponse
    {
        Gate::authorize('create', LeadDistributionRule::class);

        LeadDistributionRule::create($request->validated());

        return back()->with('status', 'Regra de distribuição criada com sucesso.');
    }

    /**
     * Sem `destroy()`: `lead_assignments.lead_distribution_rule_id` referencia a regra
     * (nullOnDelete) — apagar de verdade perderia a trilha de qual regra atribuiu cada lead
     * historicamente. "Remover" aqui é desativar via `is_active=false` no próprio update.
     */
    public function update(StoreLeadDistributionRuleRequest $request, LeadDistributionRule $distributionRule): RedirectResponse
    {
        Gate::authorize('update', $distributionRule);

        $distributionRule->update($request->validated());

        return back()->with('status', 'Regra de distribuição atualizada com sucesso.');
    }
}
