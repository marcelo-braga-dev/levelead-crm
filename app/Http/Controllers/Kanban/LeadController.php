<?php

namespace App\Http\Controllers\Kanban;

use App\Actions\Leads\CreateLeadAction;
use App\Actions\Leads\RecycleLeadAction;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    /**
     * Quantidade de leads exibida por coluna por padrão (Hardening pós-MVP H3) — sem isso, uma
     * coluna com centenas/milhares de leads pesa no payload Inertia e no render do navegador.
     * O frontend pede mais via `loaded[stage]=N` (partial reload, só a prop `columns` recarrega).
     */
    private const PER_COLUMN_LIMIT = 30;

    public function board(Request $request): Response
    {
        Gate::authorize('viewAny', Lead::class);

        $user = $request->user();
        $loaded = $this->resolveLoadedCounts($request);

        $query = Lead::query()
            ->with([
                'company' => fn ($q) => $q->select([
                    'id', 'person_type', 'cnpj', 'cpf', 'razao_social', 'nome_fantasia', 'site',
                    'matriz_filial', 'ente_federativo', 'primary_cnae_id', 'legal_nature_id',
                    'data_inicio_atividade', 'company_size', 'share_capital',
                    'is_mei', 'mei_entry_date', 'mei_exit_date',
                    'registration_status', 'registration_status_date', 'tax_regime',
                    'estimated_revenue_value', 'employee_count',
                    'active_federal_debt', 'total_debt',
                ]),
                'company.placesProfile',
                'company.address.city:id,name',
                'company.address.state:id,uf',
                'company.primaryCnae:id,code,description',
                'company.legalNature:id,code,description',
                'company.contacts',
                'company.partners' => fn ($q) => $q->select(['id', 'company_id', 'nome', 'tipo_documento', 'faixa_etaria', 'partner_qualification_id', 'data_entrada']),
                'company.partners.partnerQualification:id,description',
                'company.financialSnapshots' => fn ($q) => $q->orderByDesc('snapshot_date'),
                'assignedTo:id,name',
                'team:id,name',
                'leadSource:id,name',
                'wonProduct:id,name',
                'recycledFrom:id,created_at',
                'proposals' => fn ($q) => $q->with('attachments', 'createdBy:id,name', 'product:id,name')->orderByDesc('version'),
                'followUps' => fn ($q) => $q->with('createdBy:id,name')->orderBy('scheduled_at'),
                'interactions' => fn ($q) => $q->with('user:id,name')->latest('occurred_at'),
            ])
            ->whereNull('archived_at');

        if ($user->role === UserRole::Consultant) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id);

                if ($user->team_id !== null) {
                    $q->orWhere('team_id', $user->team_id);
                }
            });
        }

        $leadsByStage = $query->get()
            ->groupBy(fn (Lead $lead) => $lead->stage->value)
            ->map(fn ($leads) => $leads->sortByDesc('stage_entered_at')->values());

        $columns = array_map(
            function (LeadStage $stage) use ($leadsByStage, $loaded) {
                $leads = $leadsByStage->get($stage->value) ?? collect();

                return [
                    'stage' => $stage->value,
                    'label' => $stage->label(),
                    'leads' => $leads->take($loaded[$stage->value] ?? self::PER_COLUMN_LIMIT)->values(),
                    'total' => $leads->count(),
                ];
            },
            LeadStage::cases(),
        );

        return Inertia::render('Kanban/Board', [
            'columns' => $columns,
            'loaded' => $loaded,
            'products' => Product::query()->select('id', 'name')->get(),
            'consultants' => User::query()->where('role', UserRole::Consultant->value)->select('id', 'name', 'team_id')->get(),
            'states' => State::query()->select('id', 'uf')->orderBy('uf')->get(),
            'googlePlacesBadgeThresholds' => [
                'low_rating' => (float) Setting::get('google_places.low_rating_threshold', 3.5),
                'low_review_count' => (int) Setting::get('google_places.low_review_threshold', 10),
            ],
        ]);
    }

    /** @return array<string, int> estágio => quantidade pedida (mínimo PER_COLUMN_LIMIT) */
    private function resolveLoadedCounts(Request $request): array
    {
        $loaded = $request->query('loaded', []);

        if (! is_array($loaded)) {
            return [];
        }

        return array_map(fn ($value) => max(self::PER_COLUMN_LIMIT, (int) $value), $loaded);
    }

    public function store(StoreLeadRequest $request, CreateLeadAction $action): RedirectResponse
    {
        Gate::authorize('create', Lead::class);

        $company = Company::findOrFail($request->validated('company_id'));

        try {
            $action->execute($company, $request->validated());
        } catch (DomainException $e) {
            return back()->withErrors(['company_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Lead criado com sucesso.');
    }

    /**
     * Escopo estreito: só contato/qualificação do próprio lead — é o que permite o consultor
     * responsável editar esses campos a partir da página Leads sem precisar de acesso admin/
     * manager (esse é o critério de companies.update, que cobre os dados cadastrais da Company).
     */
    public function updateContact(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $lead->update($request->validated());

        return back()->with('status', 'Lead atualizado com sucesso.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return back()->with('status', 'Lead removido com sucesso.');
    }

    public function recycle(Lead $lead, RecycleLeadAction $action): RedirectResponse
    {
        Gate::authorize('recycle', $lead);

        try {
            $action->execute($lead);
        } catch (DomainException $e) {
            return back()->withErrors(['recycle' => $e->getMessage()]);
        }

        return back()->with('status', 'Lead reciclado com sucesso — novo lead criado em "Lead Novo".');
    }
}
