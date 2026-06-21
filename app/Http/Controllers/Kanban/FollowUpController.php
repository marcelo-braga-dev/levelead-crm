<?php

namespace App\Http\Controllers\Kanban;

use App\Actions\FollowUps\CompleteFollowUpAction;
use App\Actions\FollowUps\CreateFollowUpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFollowUpRequest;
use App\Models\FollowUp;
use App\Models\Lead;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FollowUpController extends Controller
{
    public function store(StoreFollowUpRequest $request, Lead $lead, CreateFollowUpAction $action): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $action->execute($lead, [
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Follow-up agendado com sucesso.');
    }

    public function complete(Request $request, FollowUp $followUp, CompleteFollowUpAction $action): RedirectResponse
    {
        Gate::authorize('update', $followUp);

        try {
            $action->execute($followUp, ['user_id' => $request->user()->id]);
        } catch (DomainException $e) {
            return back()->withErrors(['follow_up' => $e->getMessage()]);
        }

        return back()->with('status', 'Follow-up concluído.');
    }
}
