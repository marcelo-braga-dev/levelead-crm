<?php

use App\Actions\Companies\SyncGooglePlacesProfileAction;
use App\DomainServices\LeadScoringService;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyPlacesProfile;
use App\Models\Lead;
use App\Models\LeadScoringRule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function makeCompanyForPlaces(): Company
{
    return Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Padaria Exemplo',
        'logradouro' => 'Rua das Flores',
        'bairro' => 'Centro',
    ]);
}

it('does not call the Google API and reports not_configured when no key is set', function () {
    config(['services.google_places.key' => null]);
    Http::fake();

    $company = makeCompanyForPlaces();

    $result = app(SyncGooglePlacesProfileAction::class)->execute($company);

    expect($result['result'])->toBe(SyncGooglePlacesProfileAction::RESULT_NOT_CONFIGURED);
    expect($result['profile'])->toBeNull();
    Http::assertNothingSent();
    expect(CompanyPlacesProfile::query()->count())->toBe(0);
});

it('syncs and persists a places profile on success', function () {
    config(['services.google_places.key' => 'test-key']);
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [['id' => 'PLACE123']]], 200),
        'places.googleapis.com/v1/places/PLACE123' => Http::response([
            'id' => 'PLACE123',
            'rating' => 4.5,
            'userRatingCount' => 87,
            'primaryType' => 'bakery',
            'businessStatus' => 'OPERATIONAL',
            'websiteUri' => 'https://padaria.example.com',
        ], 200),
    ]);

    $company = makeCompanyForPlaces();

    $result = app(SyncGooglePlacesProfileAction::class)->execute($company);

    expect($result['result'])->toBe(SyncGooglePlacesProfileAction::RESULT_SYNCED);
    $profile = $company->placesProfile()->first();
    expect($profile)->not->toBeNull();
    expect((float) $profile->rating)->toBe(4.5);
    expect($profile->user_rating_count)->toBe(87);
    expect($profile->has_website)->toBeTrue();
    expect($profile->google_place_id)->toBe('PLACE123');
});

it('blocks sync once the monthly budget cap is reached', function () {
    config(['services.google_places.key' => 'test-key']);
    Setting::set('google_places.monthly_budget_cap', 1);
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [['id' => 'PLACE1']]], 200),
        'places.googleapis.com/v1/places/PLACE1' => Http::response(['id' => 'PLACE1', 'rating' => 4.0], 200),
    ]);

    $companyA = makeCompanyForPlaces();
    $companyB = makeCompanyForPlaces();

    $first = app(SyncGooglePlacesProfileAction::class)->execute($companyA);
    $second = app(SyncGooglePlacesProfileAction::class)->execute($companyB);

    expect($first['result'])->toBe(SyncGooglePlacesProfileAction::RESULT_SYNCED);
    expect($second['result'])->toBe(SyncGooglePlacesProfileAction::RESULT_BUDGET_EXCEEDED);
    expect(CompanyPlacesProfile::query()->count())->toBe(1);
});

it('exposes a POST route any authenticated role can trigger', function () {
    config(['services.google_places.key' => null]);
    $company = makeCompanyForPlaces();
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    $response = $this->actingAs($consultant)->post(route('companies.places-profile.store', $company));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Integração com o Google não está configurada.');
});

it('matches the new google-based lead scoring criteria', function () {
    $company = makeCompanyForPlaces();
    CompanyPlacesProfile::create([
        'company_id' => $company->id,
        'rating' => 4.8,
        'user_rating_count' => 120,
    ]);
    LeadScoringRule::create(['criterion' => 'has_google_profile', 'criterion_value' => '1', 'score_weight' => 5, 'is_active' => true]);
    LeadScoringRule::create(['criterion' => 'google_rating_above', 'criterion_value' => '4.0', 'score_weight' => 10, 'is_active' => true]);
    LeadScoringRule::create(['criterion' => 'google_rating_above', 'criterion_value' => '4.9', 'score_weight' => 99, 'is_active' => true]); // não casa

    $lead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);

    expect(app(LeadScoringService::class)->calculateFitScore($lead))->toBe(15);
});
