<?php

namespace App\Http\Controllers\Kanban;

use App\Actions\Proposals\CreateProposalVersionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProposalRequest;
use App\Models\Lead;
use App\Models\ProposalAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalController extends Controller
{
    public function store(StoreProposalRequest $request, Lead $lead, CreateProposalVersionAction $action): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $action->execute($lead, [
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ], $request->file('attachments', []));

        return back()->with('status', 'Proposta registrada com sucesso.');
    }

    public function downloadAttachment(ProposalAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->proposal);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
