<?php

use App\DomainServices\CallWindowValidator;
use App\Enums\LeadStage;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\NationalHoliday;
use App\Models\OptOut;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

function createLeadForComplianceTest(array $companyAttributes = []): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Compliance Test',
        'registration_status' => RegistrationStatus::Active->value,
        ...$companyAttributes,
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::ContactMade->value]);
}

// Quarta-feira às 10h — dentro da janela permitida em dia de semana.
function aWeekdayWithinWindow(): Carbon
{
    return Carbon::create(2026, 6, 17, 10, 0, 0);
}

it('saves a note without any compliance check regardless of time window', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 21, 23, 0, 0)); // domingo, fora de qualquer janela
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest();

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'note',
        'description' => 'Cliente pediu para retornar depois',
    ])->assertSessionDoesntHaveErrors();

    expect(LeadInteraction::where('lead_id', $lead->id)->where('type', 'note')->exists())->toBeTrue();
});

it('warns but does not save a call outside the allowed window, then saves when confirmed', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 21, 23, 0, 0)); // domingo
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest();

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => '11999990000',
    ]);
    expect(LeadInteraction::where('lead_id', $lead->id)->where('type', 'call')->exists())->toBeFalse();

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => '11999990000',
        'confirmed' => true,
    ]);
    expect(LeadInteraction::where('lead_id', $lead->id)->where('type', 'call')->exists())->toBeTrue();
});

it('warns about opt-out by phone before registering a call interaction', function () {
    Carbon::setTestNow(aWeekdayWithinWindow());
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest();
    OptOut::create([
        'phone' => '11988887777',
        'reason' => 'Não quer mais ser contatado',
        'requested_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => '11988887777',
    ]);
    expect(LeadInteraction::where('lead_id', $lead->id)->exists())->toBeFalse();

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => '11988887777',
        'confirmed' => true,
    ]);
    expect(LeadInteraction::where('lead_id', $lead->id)->exists())->toBeTrue();
});

it('warns about opt-out matched by company even with a different phone number', function () {
    Carbon::setTestNow(aWeekdayWithinWindow());
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest();
    OptOut::create([
        'company_id' => $lead->company_id,
        'requested_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'whatsapp',
        'phone_dialed' => '11900001111',
    ]);

    expect(LeadInteraction::where('lead_id', $lead->id)->exists())->toBeFalse();
});

it('warns when the company registration status is closed or unfit', function () {
    Carbon::setTestNow(aWeekdayWithinWindow());
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest(['registration_status' => RegistrationStatus::Closed->value]);

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => '11999998888',
    ]);

    expect(LeadInteraction::where('lead_id', $lead->id)->exists())->toBeFalse();
});

it('warns when the dialed phone already received too many calls today', function () {
    Carbon::setTestNow(aWeekdayWithinWindow());
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest();
    $phone = '11977776666';

    foreach (range(1, 2) as $_) {
        $this->actingAs($user)->post(route('interactions.store', $lead), [
            'type' => 'call',
            'phone_dialed' => $phone,
            'confirmed' => true,
        ]);
    }
    expect(LeadInteraction::where('phone_dialed', $phone)->count())->toBe(2);

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => $phone,
    ]);
    expect(LeadInteraction::where('phone_dialed', $phone)->count())->toBe(2);
});

it('respects a lower compliance.max_calls_per_day setting than the default', function () {
    Setting::set('compliance.max_calls_per_day', 1);
    Carbon::setTestNow(aWeekdayWithinWindow());
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest();
    $phone = '11977776666';

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => $phone,
        'confirmed' => true,
    ]);
    expect(LeadInteraction::where('phone_dialed', $phone)->count())->toBe(1);

    $response = $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => $phone,
    ]);

    expect(LeadInteraction::where('phone_dialed', $phone)->count())->toBe(1);
    $response->assertSessionHas('compliance_warnings', fn ($warnings) => in_array(
        'Este número já recebeu 1 ligação(ões) hoje (limite recomendado: 1/dia).',
        $warnings,
        true,
    ));
});

it('increments contact_attempts_count and last_interaction_at only for outbound call/whatsapp', function () {
    Carbon::setTestNow(aWeekdayWithinWindow());
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $lead = createLeadForComplianceTest();

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'call',
        'phone_dialed' => '11955554444',
        'direction' => 'outbound',
        'confirmed' => true,
    ]);

    expect($lead->fresh()->contact_attempts_count)->toBe(1);
    expect($lead->fresh()->last_interaction_at)->not->toBeNull();

    $this->actingAs($user)->post(route('interactions.store', $lead), [
        'type' => 'note',
        'description' => 'Sem incremento aqui',
    ]);
    expect($lead->fresh()->contact_attempts_count)->toBe(1);
});

it('forbids a consultant outside the team from registering an interaction', function () {
    $team = Team::create(['name' => 'Equipe Compliance']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $lead = createLeadForComplianceTest();

    $this->actingAs($consultant)
        ->post(route('interactions.store', $lead), ['type' => 'note', 'description' => 'x'])
        ->assertForbidden();
});

describe('CallWindowValidator', function () {
    it('allows calls on weekdays between 9h and 21h', function () {
        $validator = app(CallWindowValidator::class);
        expect($validator->isAllowed(Carbon::create(2026, 6, 17, 9, 0)))->toBeTrue();
        expect($validator->isAllowed(Carbon::create(2026, 6, 17, 20, 59)))->toBeTrue();
        expect($validator->isAllowed(Carbon::create(2026, 6, 17, 8, 59)))->toBeFalse();
        expect($validator->isAllowed(Carbon::create(2026, 6, 17, 21, 0)))->toBeFalse();
    });

    it('allows calls on saturday only between 10h and 16h', function () {
        $validator = app(CallWindowValidator::class);
        expect($validator->isAllowed(Carbon::create(2026, 6, 20, 10, 0)))->toBeTrue();
        expect($validator->isAllowed(Carbon::create(2026, 6, 20, 15, 59)))->toBeTrue();
        expect($validator->isAllowed(Carbon::create(2026, 6, 20, 16, 0)))->toBeFalse();
        expect($validator->isAllowed(Carbon::create(2026, 6, 20, 9, 59)))->toBeFalse();
    });

    it('never allows calls on sunday', function () {
        $validator = app(CallWindowValidator::class);
        expect($validator->isAllowed(Carbon::create(2026, 6, 21, 14, 0)))->toBeFalse();
    });

    it('never allows calls on a national holiday even within window hours', function () {
        NationalHoliday::create(['date' => '2026-06-18', 'name' => 'Feriado de Teste']);
        $validator = app(CallWindowValidator::class);
        expect($validator->isAllowed(Carbon::create(2026, 6, 18, 10, 0)))->toBeFalse();
    });
});
