<?php

namespace App\Http\Controllers\Companies;

use App\Actions\Companies\CreateLeadWithCompanyAction;
use App\Actions\Companies\UpdateLeadWithCompanyAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyAddressRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\LossReasonRecycleRule;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Company::class);

        $search = trim((string) $request->query('search', ''));
        $withoutActiveLead = $request->boolean('without_active_lead');
        $stateId = $request->query('state_id');
        $city = trim((string) $request->query('city', ''));
        $assignedTo = $request->query('assigned_to');
        $stage = $request->query('stage');
        $createdFrom = $request->query('created_from');
        $createdTo = $request->query('created_to');

        $query = Company::query()
            ->with([
                'address.city:id,name',
                'address.state:id,uf',
                'contacts',
                'leads:id,company_id,stage,loss_reason,assigned_to,created_at,stage_entered_at,contact_name,contact_phone,contact_whatsapp,contact_email,interest_level,purchase_potential,qualification_notes',
                'leads.assignedTo:id,name',
                'placesProfile',
            ])
            ->orderBy('razao_social');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('razao_social', 'like', "%{$search}%")
                    ->orWhere('nome_fantasia', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        if ($withoutActiveLead) {
            $query->withoutActiveLead();
        }

        if (filled($stateId)) {
            $query->whereHas('address', fn ($q) => $q->where('state_id', $stateId));
        }

        if ($city !== '') {
            $query->whereHas('address.city', fn ($q) => $q->where('name', 'like', "%{$city}%"));
        }

        if (filled($assignedTo)) {
            $query->whereHas('leads', fn ($q) => $q->where('assigned_to', $assignedTo));
        }

        if (filled($stage)) {
            $query->whereHas('leads', fn ($q) => $q->where('stage', $stage));
        }

        if (filled($createdFrom)) {
            $query->whereHas('leads', fn ($q) => $q->whereDate('created_at', '>=', $createdFrom));
        }

        if (filled($createdTo)) {
            $query->whereHas('leads', fn ($q) => $q->whereDate('created_at', '<=', $createdTo));
        }

        $companies = $query->paginate(15)->withQueryString();

        // `can_edit_contact` por lead: permite o próprio consultor responsável editar contato/
        // qualificação do seu lead pela página Leads sem precisar de acesso admin/manager (esse é
        // o critério de companies.update, que cobre os dados cadastrais da Company).
        $companies->getCollection()->each(function (Company $company) use ($request) {
            $company->leads->each(function ($lead) use ($request) {
                $lead->setAttribute('can_edit_contact', Gate::forUser($request->user())->allows('update', $lead));
            });
        });

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $search,
                'without_active_lead' => $withoutActiveLead,
                'state_id' => $stateId,
                'city' => $city,
                'assigned_to' => $assignedTo,
                'stage' => $stage,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
            ],
            'states' => State::query()->select('id', 'uf')->orderBy('uf')->get(),
            'consultants' => User::query()->where('role', UserRole::Consultant->value)->select('id', 'name')->get(),
            'lossReasonRecycleRules' => LossReasonRecycleRule::query()
                ->select('loss_reason', 'suggested_recycle_days_min', 'suggested_recycle_days_max', 'is_recyclable')
                ->get(),
        ]);
    }

    /**
     * Cadastro manual de Lead (PF ou PJ) — complementa a importação via CSV (caminho principal
     * para volume, PJ-only) para o caso de um único Lead que não está em nenhuma planilha.
     * Company e Lead nascem juntos num único formulário/submit (CreateLeadWithCompanyAction) —
     * decisão do usuário de tratar o Lead como "entidade completa desde o início", sem telas
     * separadas para cadastrar a empresa e só depois abrir o lead.
     */
    public function store(StoreCompanyRequest $request, CreateLeadWithCompanyAction $action): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $action->execute($request->validated());

        return back()->with('status', 'Lead cadastrado com sucesso.');
    }

    /**
     * Edição dos dados cadastrais (Company) e do lead em andamento (se houver), no mesmo
     * formulário — contraparte de store(). Endereço não entra aqui de propósito: continua
     * editável só pela aba Mapa do Kanban (updateAddress()), decisão fechada com o usuário.
     */
    public function update(UpdateCompanyRequest $request, Company $company, UpdateLeadWithCompanyAction $action): RedirectResponse
    {
        Gate::authorize('update', $company);

        $action->execute($company, $request->validated());

        return back()->with('status', 'Lead atualizado com sucesso.');
    }

    /**
     * Endereço é dado cadastral da Company (não do Lead) — editável a partir do Kanban (aba
     * Mapa) para permitir completar o endereço antes de buscar a localização via Google Places,
     * mas a regra de RBAC é a mesma de qualquer outra edição de Company (admin/manager).
     */
    public function updateAddress(UpdateCompanyAddressRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('update', $company);

        $company->address()->updateOrCreate([], $request->validated());

        return back()->with('status', 'Endereço atualizado com sucesso.');
    }
}
