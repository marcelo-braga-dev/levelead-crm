<?php

namespace App\Actions\Proposals;

use App\DomainServices\LeadScoringService;
use App\Enums\InteractionType;
use App\Models\Lead;
use App\Models\Proposal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Versionamento de proposta: nova proposta = nova linha com version+1; a proposta
 * `active` anterior (se houver) é marcada `superseded` — nunca há update/delete da anterior.
 */
class CreateProposalVersionAction
{
    public function __construct(private LeadScoringService $scoring) {}

    /**
     * @param  array{value?: ?string, notes?: ?string, created_by?: ?int, product_id?: ?int}  $data
     * @param  UploadedFile[]  $attachments
     */
    public function execute(Lead $lead, array $data, array $attachments = []): Proposal
    {
        return DB::transaction(function () use ($lead, $data, $attachments) {
            $nextVersion = ((int) $lead->proposals()->max('version')) + 1;

            $lead->proposals()->where('status', 'active')->update(['status' => 'superseded']);

            $proposal = $lead->proposals()->create([
                'version' => $nextVersion,
                'status' => 'active',
                'value' => $data['value'] ?? null,
                'notes' => $data['notes'] ?? null,
                'product_id' => $data['product_id'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            foreach ($attachments as $file) {
                $path = $file->store("proposals/{$lead->id}/{$proposal->id}");

                $proposal->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                ]);
            }

            $lead->interactions()->create([
                'user_id' => $data['created_by'] ?? null,
                'type' => InteractionType::System->value,
                'description' => "Proposta v{$nextVersion} criada".(blank($data['value'] ?? null) ? '' : ' — R$ '.number_format((float) $data['value'], 2, ',', '.')),
                'occurred_at' => now(),
            ]);

            $this->scoring->recalculate($lead);

            return $proposal->load('attachments');
        });
    }
}
