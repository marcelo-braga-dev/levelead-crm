<?php

namespace App\Http\Controllers\Companies;

use App\Actions\Companies\SyncGooglePlacesProfileAction;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * Mesma visibilidade aberta de `CompanyPolicy::viewAny()` (todos os papéis veem companies) —
 * sem Gate extra aqui, sincronizar o perfil do Google não expõe nada que o usuário já não veja.
 */
class GooglePlacesProfileController extends Controller
{
    private const MESSAGES = [
        SyncGooglePlacesProfileAction::RESULT_SYNCED => 'Perfil do Google sincronizado.',
        SyncGooglePlacesProfileAction::RESULT_NOT_CONFIGURED => 'Integração com o Google não está configurada.',
        SyncGooglePlacesProfileAction::RESULT_BUDGET_EXCEEDED => 'Orçamento mensal de consultas ao Google foi atingido.',
        SyncGooglePlacesProfileAction::RESULT_NOT_FOUND => 'Não foi possível encontrar esta empresa no Google.',
    ];

    public function store(Company $company, SyncGooglePlacesProfileAction $action): RedirectResponse
    {
        $outcome = $action->execute($company);

        return back()->with('status', self::MESSAGES[$outcome['result']]);
    }
}
