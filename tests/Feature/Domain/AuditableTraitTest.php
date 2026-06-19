<?php

use App\DomainServices\AuditContext;
use App\DomainServices\AuditLogReconstructor;
use App\Enums\AuditActorType;
use App\Enums\LeadStage;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\User;

it('records an audit log with actor user on create and update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $company = Company::create(['cnpj' => '11222333000181', 'razao_social' => 'Empresa Teste']);
    $lead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);

    $lead->update(['stage' => LeadStage::AttemptingContact->value, 'contact_name' => 'Fulano']);

    $logs = AuditLog::where('auditable_type', $lead->getMorphClass())
        ->where('auditable_id', $lead->id)
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2);
    expect($logs[0]->action->value)->toBe('created');
    expect($logs[0]->actor_type)->toBe(AuditActorType::User);
    expect($logs[0]->actor_id)->toBe($user->id);
    expect($logs[1]->action->value)->toBe('updated');
    expect($logs[1]->new_values)->toBe(['stage' => 'attempting_contact', 'contact_name' => 'Fulano']);
});

it('records an audit log with actor system when AuditContext::actingAs is used', function () {
    AuditContext::actingAs(AuditActorType::System, null, 'TestCommand');

    $company = Company::create(['cnpj' => '11222333000182', 'razao_social' => 'Empresa Teste 2']);

    AuditContext::reset();

    $log = AuditLog::where('auditable_type', $company->getMorphClass())
        ->where('auditable_id', $company->id)
        ->first();

    expect($log->actor_type)->toBe(AuditActorType::System);
    expect($log->actor_label)->toBe('TestCommand');
});

it('does not record an audit log when update has no actual changes', function () {
    $company = Company::create(['cnpj' => '11222333000183', 'razao_social' => 'Empresa Teste 3']);

    $countBefore = AuditLog::count();
    $company->update(['razao_social' => 'Empresa Teste 3']);

    expect(AuditLog::count())->toBe($countBefore);
});

it('reconstructs past state from audit log diffs', function () {
    // created_at de audit_logs tem precisão de segundo — viaja no tempo entre as escritas
    // para que a comparação "created_at <= pointInTime" não dependa da velocidade do teste.
    $this->travelTo(now());

    $company = Company::create(['cnpj' => '11222333000184', 'razao_social' => 'Empresa Teste 4']);
    $lead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);
    $lead->update(['stage' => LeadStage::AttemptingContact->value]);

    $midpoint = now();
    $this->travelTo(now()->addSeconds(2));

    $lead->update(['stage' => LeadStage::ContactMade->value]);

    $state = (new AuditLogReconstructor)->stateAt($lead->fresh(), $midpoint);

    expect($state['stage'])->toBe('attempting_contact');
});
