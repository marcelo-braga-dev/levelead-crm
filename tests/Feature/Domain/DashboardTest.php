<?php

use App\Actions\Companies\ImportCompaniesFromCsvAction;
use App\Actions\Leads\CreateLeadAction;
use App\Enums\LeadStage;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Team;
use App\Models\User;
use App\Support\Csv\CsvFileReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createCompanyForDashboardTest(array $attributes = []): Company
{
    return Company::create([
        'cnpj' => (string) random_int(10000000000000, 99999999999999),
        'razao_social' => 'Empresa Dashboard Test',
        ...$attributes,
    ]);
}

it('defaults a manually created lead to the Manual lead source', function () {
    LeadSource::create(['name' => 'Manual']);
    LeadSource::create(['name' => 'CSV']);
    $company = createCompanyForDashboardTest();

    $lead = app(CreateLeadAction::class)->execute($company);

    expect($lead->leadSource->name)->toBe('Manual');
});

it('tags leads created via CSV import with the CSV lead source', function () {
    LeadSource::create(['name' => 'Manual']);
    LeadSource::create(['name' => 'CSV']);
    Storage::fake('local');

    $batch = ImportBatch::create(['file_name' => 'lote.csv', 'status' => 'pending']);
    $csv = UploadedFile::fake()->createWithContent('lote.csv', "razao_social;cnpj\nEmpresa CSV Source LTDA;11333555000199\n");
    $reader = new CsvFileReader($csv->getRealPath());

    app(ImportCompaniesFromCsvAction::class)->execute($batch, $reader, ['razao_social' => 'razao_social', 'cnpj' => 'cnpj']);

    $lead = Lead::whereHas('company', fn ($q) => $q->where('cnpj', '11333555000199'))->first();
    expect($lead->leadSource->name)->toBe('CSV');
});

it('computes summary counts and the conversion rate / average ticket formulas', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::New->value]);
    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::Qualified->value]);
    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::Won->value, 'won_value' => 1000]);
    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::Won->value, 'won_value' => 2000]);
    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::Lost->value]);

    // total=5, new=1, won=2 => conversion = 2 / (5 - 1) = 0.5; ticket médio = 3000/2 = 1500
    $response = $this->actingAs($manager)->get(route('dashboard', ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('summary.total', 5)
        ->where('summary.new', 1)
        ->where('summary.won', 2)
        ->where('summary.conversion_rate', 0.5)
        ->where('summary.average_ticket', 1500));
});

it('filters the dashboard by team, consultant and lead source', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $team = Team::create(['name' => 'Equipe Dashboard']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $source = LeadSource::create(['name' => 'Meta Ads']);

    Lead::create([
        'company_id' => createCompanyForDashboardTest()->id,
        'stage' => LeadStage::New->value,
        'team_id' => $team->id,
        'assigned_to' => $consultant->id,
        'lead_source_id' => $source->id,
    ]);
    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::New->value]);

    $response = $this->actingAs($manager)->get(route('dashboard', ['team_id' => $team->id]));
    $response->assertInertia(fn ($page) => $page->where('summary.total', 1));

    $response = $this->actingAs($manager)->get(route('dashboard', ['assigned_to' => $consultant->id]));
    $response->assertInertia(fn ($page) => $page->where('summary.total', 1));

    $response = $this->actingAs($manager)->get(route('dashboard', ['lead_source_id' => $source->id]));
    $response->assertInertia(fn ($page) => $page->where('summary.total', 1));
});

it('restricts a consultant to their own and team leads on the dashboard', function () {
    $team = Team::create(['name' => 'Equipe Restrita']);
    $consultant = User::factory()->create(['role' => UserRole::Consultant, 'team_id' => $team->id]);
    $outsider = User::factory()->create(['role' => UserRole::Consultant]);

    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::New->value, 'assigned_to' => $consultant->id]);
    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::New->value, 'team_id' => $team->id]);
    Lead::create(['company_id' => createCompanyForDashboardTest()->id, 'stage' => LeadStage::New->value, 'assigned_to' => $outsider->id]);

    $response = $this->actingAs($consultant)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('summary.total', 2));
});

it('exports a csv of the leads matching the current filters', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $company = createCompanyForDashboardTest(['razao_social' => 'Empresa Export CSV']);
    Lead::create(['company_id' => $company->id, 'stage' => LeadStage::Won->value, 'won_value' => 5000]);

    $response = $this->actingAs($manager)->get(route('dashboard.export'));

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $content = $response->streamedContent();
    expect($content)->toContain('Empresa Export CSV');
    expect($content)->toContain('Ganho');
});
