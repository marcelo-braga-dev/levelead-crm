<?php

use App\Actions\SlaAlerts\EvaluateSlaForLeadAction;
use App\DomainServices\LeadStageTransitionService;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Jobs\EvaluateLeadSlaJob;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\SlaAlert;
use App\Models\User;
use App\Notifications\SlaAlertRaised;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

afterEach(function () {
    Carbon::setTestNow();
});

function createLeadForSlaTest(array $attributes = []): Lead
{
    $company = Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa SLA Test',
    ]);

    return Lead::create([
        'company_id' => $company->id,
        'stage' => LeadStage::New->value,
        ...$attributes,
    ]);
}

it('raises no_interaction_24h when there is no interaction in the last 24h since creation', function () {
    $lead = createLeadForSlaTest();
    $lead->forceFill(['created_at' => now()->subHours(30)])->save();

    $created = app(EvaluateSlaForLeadAction::class)->execute($lead);

    expect(collect($created)->map(fn (SlaAlert $a) => $a->type->value))->toContain('no_interaction_24h');
});

it('respects a lower sla.no_interaction_hours setting than the default', function () {
    Setting::set('sla.no_interaction_hours', 1);
    $lead = createLeadForSlaTest();
    $lead->forceFill(['created_at' => now()->subHours(2)])->save();

    $created = app(EvaluateSlaForLeadAction::class)->execute($lead);

    expect(collect($created)->map(fn (SlaAlert $a) => $a->type->value))->toContain('no_interaction_24h');
});

it('does not raise no_interaction_24h when there was a recent interaction', function () {
    $lead = createLeadForSlaTest(['last_interaction_at' => now()->subHours(2)]);

    $created = app(EvaluateSlaForLeadAction::class)->execute($lead);

    expect($created)->toBe([]);
});

it('raises negotiation_15d when stuck in negotiation for over 15 days', function () {
    $lead = createLeadForSlaTest([
        'stage' => LeadStage::Negotiation->value,
        'stage_entered_at' => now()->subDays(20),
        'last_interaction_at' => now()->subDays(20),
    ]);

    $created = app(EvaluateSlaForLeadAction::class)->execute($lead);

    expect(collect($created)->map(fn (SlaAlert $a) => $a->type->value))->toContain('negotiation_15d');
});

it('raises no_return_7d when a proposal was sent and there is no return in 7 days', function () {
    $lead = createLeadForSlaTest([
        'stage' => LeadStage::ProposalSent->value,
        'stage_entered_at' => now()->subDays(10),
        'last_interaction_at' => now()->subDays(10),
    ]);

    $created = app(EvaluateSlaForLeadAction::class)->execute($lead);

    expect(collect($created)->map(fn (SlaAlert $a) => $a->type->value))->toContain('no_return_7d');
});

it('raises attempts_exhausted at 7 or more contact attempts', function () {
    $lead = createLeadForSlaTest(['contact_attempts_count' => 7, 'last_interaction_at' => now()]);

    $created = app(EvaluateSlaForLeadAction::class)->execute($lead);

    expect(collect($created)->map(fn (SlaAlert $a) => $a->type->value))->toContain('attempts_exhausted');
});

it('does not duplicate an unresolved alert of the same type on repeated evaluation', function () {
    $lead = createLeadForSlaTest();
    $lead->forceFill(['created_at' => now()->subHours(30)])->save();

    app(EvaluateSlaForLeadAction::class)->execute($lead);
    $secondRun = app(EvaluateSlaForLeadAction::class)->execute($lead);

    expect($secondRun)->toBe([]);
    expect(SlaAlert::where('lead_id', $lead->id)->where('type', 'no_interaction_24h')->count())->toBe(1);
});

it('auto-resolves an alert once the condition no longer applies', function () {
    $lead = createLeadForSlaTest();
    $lead->forceFill(['created_at' => now()->subHours(30)])->save();
    app(EvaluateSlaForLeadAction::class)->execute($lead);

    $lead->update(['last_interaction_at' => now()]);
    app(EvaluateSlaForLeadAction::class)->execute($lead);

    $alert = SlaAlert::where('lead_id', $lead->id)->where('type', 'no_interaction_24h')->first();
    expect($alert->resolved_at)->not->toBeNull();
});

it('does not evaluate terminal or archived leads', function () {
    $lostLead = createLeadForSlaTest(['stage' => LeadStage::Lost->value]);
    $lostLead->forceFill(['created_at' => now()->subHours(48)])->save();

    $archivedLead = createLeadForSlaTest(['archived_at' => now()]);
    $archivedLead->forceFill(['created_at' => now()->subHours(48)])->save();

    expect(app(EvaluateSlaForLeadAction::class)->execute($lostLead))->toBe([]);
    expect(app(EvaluateSlaForLeadAction::class)->execute($archivedLead))->toBe([]);
    expect(SlaAlert::query()->count())->toBe(0);
});

it('notifies admin and manager users when a new sla alert is raised, but not consultants', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    $lead = createLeadForSlaTest();
    $lead->forceFill(['created_at' => now()->subHours(30)])->save();

    app(EvaluateSlaForLeadAction::class)->execute($lead);

    Notification::assertSentTo($admin, SlaAlertRaised::class);
    Notification::assertSentTo($manager, SlaAlertRaised::class);
    Notification::assertNotSentTo($consultant, SlaAlertRaised::class);
});

it('evaluates sla for all eligible leads via the job and skips terminal ones', function () {
    $eligible = createLeadForSlaTest();
    $eligible->forceFill(['created_at' => now()->subHours(30)])->save();

    $terminal = createLeadForSlaTest(['stage' => LeadStage::Won->value]);
    $terminal->forceFill(['created_at' => now()->subHours(30)])->save();

    $raised = EvaluateLeadSlaJob::dispatchSync();

    expect($raised)->toBeGreaterThanOrEqual(1);
    expect(SlaAlert::where('lead_id', $eligible->id)->exists())->toBeTrue();
    expect(SlaAlert::where('lead_id', $terminal->id)->exists())->toBeFalse();
});

it('logs and continues past a lead that fails sla evaluation instead of aborting the rest', function () {
    Log::spy();

    $broken = createLeadForSlaTest();
    $broken->forceFill(['created_at' => now()->subHours(30)])->save();

    $healthy = createLeadForSlaTest();
    $healthy->forceFill(['created_at' => now()->subHours(30)])->save();

    SlaAlert::creating(function (SlaAlert $alert) use ($broken) {
        if ($alert->lead_id === $broken->id) {
            throw new RuntimeException('falha forçada para teste');
        }
    });

    EvaluateLeadSlaJob::dispatchSync();

    expect(SlaAlert::where('lead_id', $broken->id)->exists())->toBeFalse();
    expect(SlaAlert::where('lead_id', $healthy->id)->exists())->toBeTrue();
    Log::shouldHaveReceived('error')->once();
});

it('resolves open sla alerts when the lead transitions to a terminal stage', function () {
    $lead = createLeadForSlaTest(['stage' => LeadStage::Negotiation->value]);
    $alert = $lead->slaAlerts()->create(['type' => 'negotiation_15d']);

    app(LeadStageTransitionService::class)->transition($lead, LeadStage::Won, ['won_value' => '1000']);

    expect($alert->fresh()->resolved_at)->not->toBeNull();
});
