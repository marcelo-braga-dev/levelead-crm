<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadStageHistory;
use App\Models\Team;
use App\Models\User;

it('lets admin and manager view any lead, but restricts consultant to own/team leads', function () {
    $team = Team::create(['name' => 'Equipe A']);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $owner = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $outsider = User::factory()->create(['role' => UserRole::Consultant]);

    $company = Company::create(['cnpj' => '11222333000185', 'razao_social' => 'Empresa RBAC']);
    $lead = Lead::create([
        'company_id' => $company->id,
        'stage' => LeadStage::New->value,
        'assigned_to' => $owner->id,
        'team_id' => $team->id,
    ]);

    expect($admin->can('view', $lead))->toBeTrue();
    expect($manager->can('view', $lead))->toBeTrue();
    expect($owner->can('view', $lead))->toBeTrue();
    expect($outsider->can('view', $lead))->toBeFalse();
});

it('restricts audit log access to admin and manager only', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);

    expect($admin->can('viewAny', AuditLog::class))->toBeTrue();
    expect($manager->can('viewAny', AuditLog::class))->toBeTrue();
    expect($consultant->can('viewAny', AuditLog::class))->toBeFalse();
});

it('blocks update and delete on append-only tables for any role, including admin', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $company = Company::create(['cnpj' => '11222333000186', 'razao_social' => 'Empresa Append Only']);
    $lead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);
    $history = LeadStageHistory::create(['lead_id' => $lead->id, 'to_stage' => LeadStage::New->value]);

    expect($admin->can('update', $history))->toBeFalse();
    expect($admin->can('delete', $history))->toBeFalse();
});
