<?php

namespace App\Http\Controllers\Companies;

use App\Actions\Companies\ResolveColumnMappingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmImportMappingRequest;
use App\Http\Requests\StoreImportUploadRequest;
use App\Jobs\ProcessCsvImportJob;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportProfile;
use App\Support\Csv\CsvFileReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wizard de importação de Companies via CSV em uma única página Inertia
 * (`Companies/Import.tsx`), avançando de etapa via redirect — o `mapping` confirmado fica em
 * sessão entre as etapas (sem coluna nova em `import_batches` só para isso).
 *
 * Os parâmetros de rota chamam-se `{importBatch}` (ver routes/web.php) — os métodos abaixo
 * precisam usar exatamente esse nome para o binding implícito do Eloquent funcionar.
 */
class ImportController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('create', Company::class);

        return Inertia::render('Companies/Import', ['step' => 'upload']);
    }

    public function store(StoreImportUploadRequest $request): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $importBatch = ImportBatch::create([
            'file_name' => $request->file('file')->getClientOriginalName(),
            'data_provider' => $request->validated('data_provider'),
            'license_reference' => $request->validated('license_reference'),
        ]);

        $request->file('file')->storeAs('imports', "{$importBatch->id}.csv", 'local');

        return redirect()->route('companies.import.mapping', $importBatch);
    }

    public function mapping(ImportBatch $importBatch): Response
    {
        Gate::authorize('create', Company::class);

        $reader = $this->readerFor($importBatch);
        $header = $reader->header();
        $suggested = (new ResolveColumnMappingAction)->resolve($header);

        return Inertia::render('Companies/Import', [
            'step' => 'mapping',
            'batch' => $importBatch,
            'header' => $header,
            'suggestedMapping' => $suggested,
            'targetFields' => (new ResolveColumnMappingAction)->targetFields(),
            'profiles' => ImportProfile::with('columnMappings')->get(),
            'totalRows' => iterator_count($reader->rows()),
        ]);
    }

    public function confirmMapping(ConfirmImportMappingRequest $request, ImportBatch $importBatch): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $mapping = $request->validated('mapping');

        session()->put($this->mappingSessionKey($importBatch), $mapping);

        if ($request->boolean('save_as_profile')) {
            $profile = ImportProfile::create([
                'name' => $request->validated('profile_name'),
                'created_by' => $request->user()->id,
            ]);

            foreach ($mapping as $sourceColumn => $targetField) {
                if ($targetField === null) {
                    continue;
                }

                $profile->columnMappings()->create([
                    'source_column_label' => $sourceColumn,
                    'target_field' => $targetField,
                ]);
            }

            $importBatch->update(['import_profile_id' => $profile->id]);
        }

        return redirect()->route('companies.import.confirm', $importBatch);
    }

    public function confirm(ImportBatch $importBatch): Response|RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $mapping = session($this->mappingSessionKey($importBatch));

        if ($mapping === null && $importBatch->status === 'pending') {
            return redirect()->route('companies.import.mapping', $importBatch);
        }

        $reader = $this->readerFor($importBatch);

        return Inertia::render('Companies/Import', [
            'step' => 'confirm',
            'batch' => $importBatch->fresh('errors'),
            'mapping' => $mapping,
            'totalRows' => iterator_count($reader->rows()),
        ]);
    }

    public function process(ImportBatch $importBatch): RedirectResponse
    {
        Gate::authorize('create', Company::class);

        $mapping = session($this->mappingSessionKey($importBatch));

        abort_if($mapping === null, 422, 'Mapeamento de colunas não confirmado.');

        ProcessCsvImportJob::dispatchSync($importBatch, $mapping);

        session()->forget($this->mappingSessionKey($importBatch));

        return redirect()->route('companies.import.confirm', $importBatch);
    }

    private function readerFor(ImportBatch $importBatch): CsvFileReader
    {
        return new CsvFileReader(Storage::disk('local')->path($importBatch->storedCsvPath()));
    }

    private function mappingSessionKey(ImportBatch $importBatch): string
    {
        return "import_mapping_{$importBatch->id}";
    }
}
