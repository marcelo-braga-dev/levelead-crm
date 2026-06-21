<?php

namespace App\Actions\Companies;

use App\DomainServices\GooglePlacesClient;
use App\Models\Company;
use App\Models\CompanyPlacesProfile;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Sincroniza `company_places_profiles` a partir da Google Places API, sob orçamento mensal
 * controlado (`Setting`, sem tabela nova para o contador — ver SettingsSeeder). Nunca lança
 * exceção: o resultado (enum-like string) é que diz ao controller o que mostrar ao usuário.
 */
class SyncGooglePlacesProfileAction
{
    public const RESULT_SYNCED = 'synced';

    public const RESULT_NOT_CONFIGURED = 'not_configured';

    public const RESULT_BUDGET_EXCEEDED = 'budget_exceeded';

    public const RESULT_NOT_FOUND = 'not_found';

    public function __construct(private GooglePlacesClient $client) {}

    /** @return array{result: string, profile: ?CompanyPlacesProfile} */
    public function execute(Company $company): array
    {
        if (! $this->client->isConfigured()) {
            return ['result' => self::RESULT_NOT_CONFIGURED, 'profile' => null];
        }

        if (! $this->consumeBudget()) {
            return ['result' => self::RESULT_BUDGET_EXCEEDED, 'profile' => null];
        }

        $placeId = $company->placesProfile?->google_place_id;

        if ($placeId === null) {
            $placeId = $this->client->findPlaceId($this->buildSearchQuery($company));
        }

        if ($placeId === null) {
            return ['result' => self::RESULT_NOT_FOUND, 'profile' => null];
        }

        $details = $this->client->details($placeId);

        if ($details === null) {
            return ['result' => self::RESULT_NOT_FOUND, 'profile' => null];
        }

        $profile = CompanyPlacesProfile::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'google_place_id' => $placeId,
                'latitude' => $details['location']['latitude'] ?? null,
                'longitude' => $details['location']['longitude'] ?? null,
                'rating' => $details['rating'] ?? null,
                'user_rating_count' => $details['userRatingCount'] ?? null,
                'primary_type' => $details['primaryType'] ?? null,
                'opening_hours' => $details['regularOpeningHours'] ?? null,
                'business_status' => $details['businessStatus'] ?? null,
                'has_website' => filled($details['websiteUri'] ?? null),
                'synced_at' => now(),
            ],
        );

        return ['result' => self::RESULT_SYNCED, 'profile' => $profile];
    }

    /**
     * Usa o endereço completo (logradouro, número, bairro, cidade, UF) quando disponível —
     * muito mais preciso para a Text Search encontrar o local certo do que só nome+rua, que era
     * o comportamento original (e falhava sempre que havia mais de uma empresa na mesma rua).
     */
    private function buildSearchQuery(Company $company): string
    {
        $address = $company->address;

        $addressParts = array_filter([
            $address?->logradouro,
            $address?->numero,
            $address?->bairro,
            $address?->city?->name,
            $address?->state?->uf,
        ]);

        return implode(', ', [$company->razao_social, ...$addressParts]);
    }

    /** @return bool false quando o orçamento mensal já foi consumido */
    private function consumeBudget(): bool
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $trackedMonth = Setting::get('google_places.budget_month');
        $used = $trackedMonth === $currentMonth ? Setting::get('google_places.budget_used', 0) : 0;
        $cap = Setting::get('google_places.monthly_budget_cap', 500);

        if ($used >= $cap) {
            Setting::set('google_places.budget_month', $currentMonth);
            Setting::set('google_places.budget_used', $used);

            return false;
        }

        Setting::set('google_places.budget_month', $currentMonth);
        Setting::set('google_places.budget_used', $used + 1);

        return true;
    }
}
