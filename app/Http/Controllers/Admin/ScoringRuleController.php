<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadScoringRuleRequest;
use App\Models\LeadScoringRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ScoringRuleController extends Controller
{
    public function store(StoreLeadScoringRuleRequest $request): RedirectResponse
    {
        Gate::authorize('create', LeadScoringRule::class);

        LeadScoringRule::create($request->validated());

        return back()->with('status', 'Regra de scoring criada com sucesso.');
    }

    /** Mesmo padrão da regra de distribuição: "remover" é desativar via `is_active=false`. */
    public function update(StoreLeadScoringRuleRequest $request, LeadScoringRule $scoringRule): RedirectResponse
    {
        Gate::authorize('update', $scoringRule);

        $scoringRule->update($request->validated());

        return back()->with('status', 'Regra de scoring atualizada com sucesso.');
    }
}
