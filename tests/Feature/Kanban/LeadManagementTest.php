<?php

use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('groups leads by stage on the kanban board for admin and manager', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $company = Company::create(['cnpj' => '11222333000193', 'razao_social' => 'Empresa Board']);
    Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);
    Lead::create(['company_id' => $company->id, 'stage' => LeadStage::Qualified->value]);

    $response = $this->actingAs($admin)->get(route('kanban.board'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Kanban/Board')
        ->where('columns.0.stage', LeadStage::New->value)
        ->where('columns.0.leads.0.stage', LeadStage::New->value));
});

it('restricts a consultant to leads from their own team or assignment on the board', function () {
    $team = Team::create(['name' => 'Equipe Board']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);

    $company = Company::create(['cnpj' => '11222333000192', 'razao_social' => 'Empresa Visivel']);
    $ownLead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value, 'team_id' => $team->id]);

    $otherCompany = Company::create(['cnpj' => '11222333000191', 'razao_social' => 'Empresa Invisivel']);
    Lead::create(['company_id' => $otherCompany->id, 'stage' => LeadStage::New->value]);

    $response = $this->actingAs($consultant)->get(route('kanban.board'));

    $response->assertInertia(fn ($page) => $page
        ->where('columns.0.stage', LeadStage::New->value)
        ->where('columns.0.leads', fn ($leads) => count($leads) === 1 && $leads[0]['id'] === $ownLead->id));
});

it('creates a lead for a company without an active lead, copying the primary contact', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $company = Company::create(['cnpj' => '11222333000190', 'razao_social' => 'Empresa Sem Lead']);
    $company->contacts()->create(['type' => 'phone', 'value' => '11988887777', 'is_primary' => true]);

    $response = $this->actingAs($manager)->post(route('leads.store'), ['company_id' => $company->id]);

    $response->assertRedirect();
    $lead = $company->leads()->first();
    expect($lead)->not->toBeNull();
    expect($lead->stage)->toBe(LeadStage::New);
    expect($lead->contact_phone)->toBe('11988887777');
});

it('rejects creating a lead for a company that already has an open lead', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $company = Company::create(['cnpj' => '11222333000189', 'razao_social' => 'Empresa Com Lead']);
    Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);

    $response = $this->actingAs($manager)->post(route('leads.store'), ['company_id' => $company->id]);

    $response->assertSessionHasErrors('company_id');
    expect($company->leads()->count())->toBe(1);
});

it('allows admin but not consultant to delete a lead', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $company = Company::create(['cnpj' => '11222333000188', 'razao_social' => 'Empresa Delete']);
    $lead = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);

    $this->actingAs($consultant)->delete(route('leads.destroy', $lead))->assertForbidden();
    expect($lead->fresh())->not->toBeNull();

    $this->actingAs($admin)->delete(route('leads.destroy', $lead))->assertRedirect();
    expect(Lead::find($lead->id))->toBeNull();
});

it('caps leads per column at the default limit and exposes the real total', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    for ($i = 0; $i < 35; $i++) {
        $company = Company::create(['cnpj' => (string) (97000000000100 + $i), 'razao_social' => 'Empresa Pagina '.$i]);
        Lead::create([
            'company_id' => $company->id,
            'stage' => LeadStage::New->value,
            'stage_entered_at' => now()->subMinutes($i),
        ]);
    }

    $response = $this->actingAs($admin)->get(route('kanban.board'));

    $response->assertInertia(fn ($page) => $page
        ->where('columns.0.stage', LeadStage::New->value)
        ->where('columns.0.total', 35)
        ->where('columns.0.leads', fn ($leads) => count($leads) === 30));
});

it('returns more leads for a column when a higher loaded count is requested', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    for ($i = 0; $i < 35; $i++) {
        $company = Company::create(['cnpj' => (string) (97000000000200 + $i), 'razao_social' => 'Empresa Pagina B '.$i]);
        Lead::create([
            'company_id' => $company->id,
            'stage' => LeadStage::New->value,
            'stage_entered_at' => now()->subMinutes($i),
        ]);
    }

    $response = $this->actingAs($admin)->get(route('kanban.board', ['loaded' => ['new' => 35]]));

    $response->assertInertia(fn ($page) => $page
        ->where('columns.0.total', 35)
        ->where('columns.0.leads', fn ($leads) => count($leads) === 35));
});

it('orders leads within a column by stage_entered_at descending', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $company = Company::create(['cnpj' => '11222333000180', 'razao_social' => 'Empresa Ordem']);

    $older = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value, 'stage_entered_at' => now()->subDays(2)]);
    $newer = Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value, 'stage_entered_at' => now()->subHour()]);

    $response = $this->actingAs($admin)->get(route('kanban.board'));

    $response->assertInertia(fn ($page) => $page
        ->where('columns.0.leads.0.id', $newer->id)
        ->where('columns.0.leads.1.id', $older->id));
});

it('keeps a constant query count on the board regardless of how many leads have related data', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $consultant = User::factory()->create(['role' => UserRole::Consultant]);
    $team = Team::create(['name' => 'Equipe N+1']);

    $makeLeadWithRelations = function (string $cnpj) use ($consultant, $team) {
        $company = Company::create(['cnpj' => $cnpj, 'razao_social' => 'Empresa N+1 '.$cnpj]);
        $lead = Lead::create([
            'company_id' => $company->id,
            'stage' => LeadStage::New->value,
            'assigned_to' => $consultant->id,
            'team_id' => $team->id,
        ]);
        $lead->proposals()->create(['version' => 1, 'status' => 'active', 'created_by' => $consultant->id]);
        $lead->followUps()->create(['scheduled_at' => now()->addDay(), 'created_by' => $consultant->id]);
        $lead->interactions()->create(['type' => 'note', 'occurred_at' => now(), 'user_id' => $consultant->id]);
    };

    $makeLeadWithRelations('11222333000187');
    $makeLeadWithRelations('11222333000186');

    // Aquece o cache de Setting (lido em HandleInertiaRequests::share() em toda requisição) antes de
    // medir — sem isso a 1ª chamada bate no banco para popular o cache e a 2ª não, quebrando a
    // contagem "constante" por um motivo alheio ao N+1 real do board() (mesma armadilha do H1: medir
    // efeito de setup, não do código sob teste).
    $this->actingAs($admin)->get(route('kanban.board'));

    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('kanban.board'))->assertOk();
    $queryCountForTwoLeads = count(DB::getQueryLog());
    DB::disableQueryLog();

    $makeLeadWithRelations('11222333000185');
    $makeLeadWithRelations('11222333000184');
    $makeLeadWithRelations('11222333000183');
    $makeLeadWithRelations('11222333000182');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->actingAs($admin)->get(route('kanban.board'))->assertOk();
    $queryCountForSixLeads = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCountForSixLeads)->toBe($queryCountForTwoLeads);
});
