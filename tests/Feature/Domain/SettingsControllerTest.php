<?php

use App\DomainServices\GooglePlacesClient;
use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;

function validSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'compliance_max_calls_per_day' => 3,
        'compliance_max_calls_per_month' => 20,
        'sla_no_interaction_hours' => 30,
        'sla_negotiation_days' => 10,
        'sla_no_return_days' => 5,
        'sla_attempts_exhausted_threshold' => 8,
        'leads_archive_stale_months' => 18,
        'scoring_intent_decay_grace_days' => 5,
        'scoring_intent_decay_rate_per_day' => 1.5,
        'google_places_monthly_budget_cap' => 300,
        'google_places_low_rating_threshold' => 4.0,
        'google_places_low_review_threshold' => 5,
    ], $overrides);
}

it('lets an admin view the settings page with current and default values', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('values.compliance_max_calls_per_day', 2)
        ->where('values.sla_no_interaction_hours', 24)
        ->where('googlePlacesApiKey.configured', false)
        ->where('googlePlacesApiKey.masked', null));
});

it('forbids manager and consultant from viewing or updating settings', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    foreach ([$manager, $consultant] as $user) {
        $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.settings.update'), validSettingsPayload())->assertForbidden();
    }
});

it('lets an admin update the business settings', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->put(route('admin.settings.update'), validSettingsPayload());

    $response->assertRedirect();
    expect(Setting::get('compliance.max_calls_per_day'))->toBe(3);
    expect(Setting::get('compliance.max_calls_per_month'))->toBe(20);
    expect(Setting::get('sla.no_interaction_hours'))->toBe(30);
    expect(Setting::get('sla.negotiation_days'))->toBe(10);
    expect(Setting::get('sla.no_return_days'))->toBe(5);
    expect(Setting::get('sla.attempts_exhausted_threshold'))->toBe(8);
    expect(Setting::get('leads.archive_stale_months'))->toBe(18);
    expect(Setting::get('scoring.intent_decay_grace_days'))->toBe(5);
    expect((float) Setting::get('scoring.intent_decay_rate_per_day'))->toBe(1.5);
    expect(Setting::get('google_places.monthly_budget_cap'))->toBe(300);
    expect((float) Setting::get('google_places.low_rating_threshold'))->toBe(4.0);
    expect(Setting::get('google_places.low_review_threshold'))->toBe(5);
});

it('rejects invalid business setting values', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->put(
        route('admin.settings.update'),
        validSettingsPayload(['compliance_max_calls_per_day' => 0, 'google_places_low_rating_threshold' => 6]),
    );

    $response->assertSessionHasErrors(['compliance_max_calls_per_day', 'google_places_low_rating_threshold']);
});

it('saves a new google places api key and never echoes it back in plain text', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->put(route('admin.settings.update'), validSettingsPayload([
        'google_places_api_key' => 'super-secret-key-1234',
    ]));

    expect(Setting::get('integrations.google_places_api_key'))->toBe('super-secret-key-1234');

    $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('googlePlacesApiKey.configured', true)
        ->where('googlePlacesApiKey.masked', '••••1234')
        ->missing('googlePlacesApiKey.value'));
    $response->assertDontSee('super-secret-key-1234');
});

it('keeps the current api key when the field is left blank', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Setting::set('integrations.google_places_api_key', 'already-configured-key');

    $this->actingAs($admin)->put(route('admin.settings.update'), validSettingsPayload());

    expect(Setting::get('integrations.google_places_api_key'))->toBe('already-configured-key');
});

it('makes the google places client read the key from settings before falling back to env', function () {
    config(['services.google_places.key' => 'env-key']);
    Setting::set('integrations.google_places_api_key', 'db-key');

    expect(app(GooglePlacesClient::class)->isConfigured())->toBeTrue();

    Setting::set('integrations.google_places_api_key', null);
    config(['services.google_places.key' => null]);

    expect(app(GooglePlacesClient::class)->isConfigured())->toBeFalse();
});
