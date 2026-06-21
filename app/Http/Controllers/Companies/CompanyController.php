<?php

namespace App\Http\Controllers\Companies;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\State;
use App\Models\User;
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
                'city:id,name',
                'state:id,uf',
                'contacts',
                'leads:id,company_id,stage,assigned_to,created_at',
                'leads.assignedTo:id,name',
                'placesProfile',
            ])
            ->orderBy('razao_social');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('razao_social', 'like', "%{$search}%")
                    ->orWhere('nome_fantasia', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        if ($withoutActiveLead) {
            $query->withoutActiveLead();
        }

        if (filled($stateId)) {
            $query->where('state_id', $stateId);
        }

        if ($city !== '') {
            $query->whereHas('city', fn ($q) => $q->where('name', 'like', "%{$city}%"));
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

        return Inertia::render('Companies/Index', [
            'companies' => $query->paginate(15)->withQueryString(),
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
        ]);
    }
}
