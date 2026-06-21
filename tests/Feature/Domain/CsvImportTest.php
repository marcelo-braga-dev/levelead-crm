<?php

use App\Actions\Companies\ImportCompaniesFromCsvAction;
use App\Actions\Companies\ResolveColumnMappingAction;
use App\Actions\Leads\CreateLeadAction;
use App\Enums\AuditActorType;
use App\Enums\LeadStage;
use App\Jobs\ProcessCsvImportJob;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Lead;
use App\Support\Csv\CsvFileReader;
use Illuminate\Support\Facades\Storage;

function csvFixturePath(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $content);

    return $path;
}

it('auto-detects standard csv headers', function () {
    $mapping = (new ResolveColumnMappingAction)->resolve(['CNPJ', 'RAZAO SOCIAL', 'NOME FANTASIA']);

    expect($mapping)->toBe([
        'CNPJ' => 'cnpj',
        'RAZAO SOCIAL' => 'razao_social',
        'NOME FANTASIA' => 'nome_fantasia',
    ]);
});

it('auto-detects renamed and reordered headers ignoring accents and case', function () {
    $mapping = (new ResolveColumnMappingAction)->resolve(['Nome Fantasia', 'Razão Social', 'cnpj da empresa']);

    expect($mapping)->toBe([
        'Nome Fantasia' => 'nome_fantasia',
        'Razão Social' => 'razao_social',
        'cnpj da empresa' => 'cnpj',
    ]);
});

it('leaves unknown columns unmapped for manual resolution', function () {
    $mapping = (new ResolveColumnMappingAction)->resolve(['Coluna Desconhecida']);

    expect($mapping['Coluna Desconhecida'])->toBeNull();
});

it('creates a new company and a New lead from a csv row', function () {
    $batch = ImportBatch::create(['file_name' => 'lote.csv']);
    $path = csvFixturePath("CNPJ;RAZAO SOCIAL;NOME FANTASIA\n11222333000199;Empresa Um LTDA;Empresa Um\n");
    $mapping = ['CNPJ' => 'cnpj', 'RAZAO SOCIAL' => 'razao_social', 'NOME FANTASIA' => 'nome_fantasia'];

    app(ImportCompaniesFromCsvAction::class)->execute($batch, new CsvFileReader($path), $mapping);

    $company = Company::where('cnpj', '11222333000199')->first();

    expect($company)->not->toBeNull();
    expect($company->razao_social)->toBe('Empresa Um LTDA');
    expect($company->leads()->count())->toBe(1);
    expect($company->leads()->first()->stage)->toBe(LeadStage::New);
    expect($batch->fresh()->created_rows)->toBe(1);
});

it('dedupes the same cnpj appearing twice within a single csv batch', function () {
    $batch = ImportBatch::create(['file_name' => 'lote.csv']);
    $mapping = ['CNPJ' => 'cnpj', 'RAZAO SOCIAL' => 'razao_social'];

    $path = csvFixturePath(
        "CNPJ;RAZAO SOCIAL\n".
        "11222333000194;Empresa Duplicada Primeira Linha\n".
        "11222333000194;Empresa Duplicada Segunda Linha\n"
    );

    app(ImportCompaniesFromCsvAction::class)->execute($batch, new CsvFileReader($path), $mapping);

    expect(Company::where('cnpj', '11222333000194')->count())->toBe(1);

    $company = Company::where('cnpj', '11222333000194')->first();
    expect($company->razao_social)->toBe('Empresa Duplicada Segunda Linha');
    expect($company->leads()->count())->toBe(1);

    $batch->refresh();
    expect($batch->created_rows)->toBe(1);
    expect($batch->updated_rows)->toBe(1);
});

it('updates an existing company on reimport without duplicating the open lead', function () {
    $batch = ImportBatch::create(['file_name' => 'lote.csv']);
    $mapping = ['CNPJ' => 'cnpj', 'RAZAO SOCIAL' => 'razao_social', 'NOME FANTASIA' => 'nome_fantasia'];

    $first = csvFixturePath("CNPJ;RAZAO SOCIAL;NOME FANTASIA\n11222333000198;Empresa Dois LTDA;Empresa Dois\n");
    app(ImportCompaniesFromCsvAction::class)->execute($batch, new CsvFileReader($first), $mapping);

    $second = csvFixturePath("CNPJ;RAZAO SOCIAL;NOME FANTASIA\n11222333000198;Empresa Dois LTDA Atualizada;Empresa Dois\n");
    app(ImportCompaniesFromCsvAction::class)->execute($batch, new CsvFileReader($second), $mapping);

    $company = Company::where('cnpj', '11222333000198')->first();

    expect(Company::where('cnpj', '11222333000198')->count())->toBe(1);
    expect($company->razao_social)->toBe('Empresa Dois LTDA Atualizada');
    expect($company->leads()->count())->toBe(1);
});

it('does not create a lead when the company only has terminal leads, and lists it as without active lead', function () {
    $company = Company::create(['cnpj' => '11222333000197', 'razao_social' => 'Empresa Reciclagem']);
    Lead::create(['company_id' => $company->id, 'stage' => LeadStage::Lost->value]);

    $batch = ImportBatch::create(['file_name' => 'lote.csv']);
    $mapping = ['CNPJ' => 'cnpj', 'RAZAO SOCIAL' => 'razao_social'];
    $path = csvFixturePath("CNPJ;RAZAO SOCIAL\n11222333000197;Empresa Reciclagem Atualizada\n");

    app(ImportCompaniesFromCsvAction::class)->execute($batch, new CsvFileReader($path), $mapping);

    expect($company->fresh()->leads()->count())->toBe(1);
    expect(Company::withoutActiveLead()->whereKey($company->id)->exists())->toBeTrue();
});

it('records an error for a row missing cnpj instead of failing the whole batch', function () {
    $batch = ImportBatch::create(['file_name' => 'lote.csv']);
    $mapping = ['CNPJ' => 'cnpj', 'RAZAO SOCIAL' => 'razao_social'];
    $path = csvFixturePath("CNPJ;RAZAO SOCIAL\n;Empresa Sem Cnpj\n11222333000196;Empresa Valida\n");

    app(ImportCompaniesFromCsvAction::class)->execute($batch, new CsvFileReader($path), $mapping);

    $batch->refresh();

    expect($batch->skipped_rows)->toBe(1);
    expect($batch->created_rows)->toBe(1);
    expect($batch->errors()->count())->toBe(1);
    expect($batch->errors()->first()->row_number)->toBe(2);
});

it('tags companies changed during a csv import with actor_type import in the audit log', function () {
    Storage::fake('local');

    $batch = ImportBatch::create(['file_name' => 'lote.csv']);
    Storage::disk('local')->put($batch->storedCsvPath(), "CNPJ;RAZAO SOCIAL\n11222333000195;Empresa Auditada\n");

    $mapping = ['CNPJ' => 'cnpj', 'RAZAO SOCIAL' => 'razao_social'];

    (new ProcessCsvImportJob($batch, $mapping))->handle(app(ImportCompaniesFromCsvAction::class));

    $company = Company::where('cnpj', '11222333000195')->first();

    $log = AuditLog::where('auditable_type', $company->getMorphClass())
        ->where('auditable_id', $company->id)
        ->where('action', 'created')
        ->first();

    expect($log->actor_type)->toBe(AuditActorType::Import);
    expect($log->actor_id)->toBe($batch->id);
    expect($log->actor_label)->toContain((string) $batch->id);
    expect($batch->fresh()->status)->toBe('completed');
});

it('throws when creating a lead for a company that already has an open lead', function () {
    $company = Company::create(['cnpj' => '11222333000194', 'razao_social' => 'Empresa Com Lead Aberto']);
    Lead::create(['company_id' => $company->id, 'stage' => LeadStage::New->value]);

    expect(fn () => app(CreateLeadAction::class)->execute($company))
        ->toThrow(DomainException::class);
});
