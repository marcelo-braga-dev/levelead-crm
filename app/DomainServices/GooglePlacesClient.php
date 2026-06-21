<?php

namespace App\DomainServices;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cliente fino para a Google Places API (New) — não a Business Profile API, que só dá acesso
 * a perfis que a própria empresa verificou (nunca a uma empresa-prospect arbitrária, o caso de
 * uso aqui). Pública, mas paga por requisição — quem chama (`SyncGooglePlacesProfileAction`) é
 * responsável por checar orçamento antes de cada chamada.
 *
 * Qualquer falha de rede/timeout vira `null`, nunca exceção — um problema do lado do Google
 * jamais pode quebrar a tela do lead (mesmo princípio não-bloqueante do `ComplianceChecker`).
 */
class GooglePlacesClient
{
    private const BASE_URL = 'https://places.googleapis.com/v1';

    private const DETAILS_FIELD_MASK = 'id,rating,userRatingCount,primaryType,regularOpeningHours,businessStatus,websiteUri';

    public function isConfigured(): bool
    {
        return filled(config('services.google_places.key'));
    }

    /** Text Search — resolve um place_id a partir de nome+endereço livre. */
    public function findPlaceId(string $textQuery): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => config('services.google_places.key'),
                'X-Goog-FieldMask' => 'places.id',
            ])
                ->timeout(5)
                ->post(self::BASE_URL.'/places:searchText', ['textQuery' => $textQuery]);

            return $response->successful() ? $response->json('places.0.id') : null;
        } catch (Throwable $e) {
            Log::warning('GooglePlacesClient::findPlaceId falhou: '.$e->getMessage());

            return null;
        }
    }

    /** Place Details — campos definidos em DETAILS_FIELD_MASK, nunca o corpo todo da resposta. */
    public function details(string $placeId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'X-Goog-Api-Key' => config('services.google_places.key'),
                'X-Goog-FieldMask' => self::DETAILS_FIELD_MASK,
            ])
                ->timeout(5)
                ->get(self::BASE_URL."/places/{$placeId}");

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::warning('GooglePlacesClient::details falhou: '.$e->getMessage());

            return null;
        }
    }
}
