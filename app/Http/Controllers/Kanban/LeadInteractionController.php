<?php

namespace App\Http\Controllers\Kanban;

use App\Actions\Interactions\RegisterInteractionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterInteractionRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class LeadInteractionController extends Controller
{
    public function store(RegisterInteractionRequest $request, Lead $lead, RegisterInteractionAction $action): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $result = $action->execute($lead, $request->validated(), $request->user()->id);

        if ($result['interaction'] === null) {
            return back()->with('compliance_warnings', $result['warnings']);
        }

        return back()->with('status', 'Interação registrada com sucesso.');
    }
}
