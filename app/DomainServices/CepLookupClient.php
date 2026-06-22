<?php

namespace App\DomainServices;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Busca endereço a partir do CEP via ViaCEP (API pública brasileira, gratuita, sem chave) —
 * autocompleta o formulário de endereço do lead (aba Mapa) a partir só do CEP digitado. Mesmo
 * princípio não-bloqueante já usado pelo `GooglePlacesClient`: qualquer falha (CEP inexistente,
 * timeout, serviço fora) retorna `null` e nunca quebra a tela — o usuário sempre pode preencher
 * o endereço manualmente.
 */
class CepLookupClient
{
    /** @return array{logradouro: ?string, bairro: ?string, localidade: ?string, uf: ?string, ibge: ?string}|null */
    public function lookup(string $cep): ?array
    {
        try {
            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (empty($data) || ($data['erro'] ?? false)) {
                return null;
            }

            return $data;
        } catch (Throwable) {
            return null;
        }
    }
}
