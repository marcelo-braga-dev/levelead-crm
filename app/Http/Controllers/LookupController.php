<?php

namespace App\Http\Controllers;

use App\DomainServices\CepLookupClient;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Lookups geográficos genéricos (não amarrados a Company/Lead), usados pelo editor de endereço
 * (aba Mapa do Kanban): cidades por estado (select em cascata) e busca de endereço por CEP.
 * `states` é pequeno o suficiente (27 linhas) para ir direto nas props da página; `cities` não,
 * por isso fica aqui.
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

    /**
     * Resolve `city_id`/`state_id` pelo `ibge` retornado pelo ViaCEP — muito mais confiável que
     * casar por nome de cidade (sem problema de acento/grafia divergente entre as duas fontes).
     */
    public function cep(Request $request, CepLookupClient $client): JsonResponse
    {
        $validated = $request->validate(['cep' => ['required', 'string']]);
        $cep = preg_replace('/\D/', '', $validated['cep']);

        if (strlen($cep) !== 8) {
            return response()->json(['found' => false]);
        }

        $data = $client->lookup($cep);

        if ($data === null) {
            return response()->json(['found' => false]);
        }

        $city = City::query()->where('ibge_code', $data['ibge'] ?? null)->first();

        return response()->json([
            'found' => true,
            'logradouro' => $data['logradouro'] ?: null,
            'bairro' => $data['bairro'] ?: null,
            'city_id' => $city?->id,
            'state_id' => $city?->state_id,
            'city_name' => $city?->name,
        ]);
    }
}
