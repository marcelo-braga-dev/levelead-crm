<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Lookups geográficos genéricos (não amarrados a Company/Lead) — hoje só cidades por estado,
 * usado pelo select em cascata do editor de endereço (aba Mapa do Kanban). `states` é pequeno o
 * suficiente (27 linhas) para ir direto nas props da página; `cities` não, por isso fica aqui.
 */
class LookupController extends Controller
{
    public function cities(Request $request): Collection
    {
        $validated = $request->validate(['state_id' => ['required', 'integer', 'exists:states,id']]);

        return City::query()
            ->where('state_id', $validated['state_id'])
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }
}
