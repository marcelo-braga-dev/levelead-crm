<?php

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('forbids a consultant from accessing the audit index and show pages', function () {
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $company = Company::create(['cnpj' => '11222333000101', 'razao_social' => 'Empresa Audit RBAC']);

    $this->actingAs($consultant)->get(route('admin.audit.index'))->assertForbidden();
    $this->actingAs($consultant)->get(route('admin.audit.show', ['company', $company->id]))->assertForbidden();
});

it('lets a manager list audit logs filtered by entity type', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    Company::create(['cnpj' => '11222333000102', 'razao_social' => 'Empresa Audit Index']);

    $response = $this->actingAs($manager)->get(route('admin.audit.index', ['auditable_type' => 'company']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('logs.data.0.auditable_slug', 'company')
        ->where('logs.data.0.action', 'created'));
});

it('reconstructs an entity state at a chosen point in time', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    Carbon::setTestNow(Carbon::create(2026, 1, 1, 10, 0, 0));
    $company = Company::create([
        'cnpj' => '11222333000103',
        'razao_social' => 'Nome Original',
    ]);

    Carbon::setTestNow(Carbon::create(2026, 1, 10, 10, 0, 0));
    $company->update(['razao_social' => 'Nome Atualizado']);

    $url = route('admin.audit.show', ['company', $company->id]).'?at='.urlencode('2026-01-05 10:00:00');
    $response = $this->actingAs($admin)->get($url);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('reconstructedState.razao_social', 'Nome Original')
        ->where('currentState.razao_social', 'Nome Atualizado'));
});

it('shows the current state when no reconstruction datetime is given', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $company = Company::create(['cnpj' => '11222333000104', 'razao_social' => 'Empresa Atual']);

    $response = $this->actingAs($manager)->get(route('admin.audit.show', ['company', $company->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('reconstructedState', null)
        ->where('currentState.razao_social', 'Empresa Atual'));
});
