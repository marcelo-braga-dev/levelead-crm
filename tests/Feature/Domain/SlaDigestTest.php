<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Jobs\SendSlaDigestJob;
use App\Models\Company;
use App\Models\Lead;
use App\Models\SlaAlert;
use App\Models\User;
use App\Notifications\SlaAlertRaised;
use App\Notifications\SlaDigest;
use Illuminate\Support\Facades\Notification;

function createLeadForDigestTest(): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Digest Test',
    ]);

    return Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);
}

it('does not send a digest when there are no unresolved sla alerts', function () {
    Notification::fake();
    User::factory()->create(['role' => UserRole::Admin]);

    $count = SendSlaDigestJob::dispatchSync();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('sends one digest email per admin/manager grouping unresolved alerts', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    $leadA = createLeadForDigestTest();
    SlaAlert::create(['lead_id' => $leadA->id, 'type' => 'no_interaction_24h']);
    $leadB = createLeadForDigestTest();
    SlaAlert::create(['lead_id' => $leadB->id, 'type' => 'negotiation_15d']);

    $count = SendSlaDigestJob::dispatchSync();

    expect($count)->toBe(2);
    Notification::assertSentTo($admin, SlaDigest::class);
    Notification::assertSentTo($manager, SlaDigest::class);
    Notification::assertNotSentTo($consultant, SlaDigest::class);
});

it('does not include already-resolved alerts in the digest', function () {
    Notification::fake();
    User::factory()->create(['role' => UserRole::Admin]);

    $lead = createLeadForDigestTest();
    SlaAlert::create(['lead_id' => $lead->id, 'type' => 'no_interaction_24h', 'resolved_at' => now()]);

    $count = SendSlaDigestJob::dispatchSync();

    expect($count)->toBe(0);
    Notification::assertNothingSent();
});

it('sla alert raised no longer sends an individual e-mail, only the in-app database channel', function () {
    $alert = new SlaAlert;

    expect((new SlaAlertRaised($alert))->via(new User))->toBe(['database']);
});
