<?php

namespace App\Jobs;

use App\Actions\Companies\ImportCompaniesFromCsvAction;
use App\DomainServices\AuditContext;
use App\Enums\AuditActorType;
use App\Models\ImportBatch;
use App\Support\Csv\CsvFileReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Disparado via dispatchSync() por enquanto (sem worker de fila configurado no Sail ainda) —
 * trocar para dispatch() assíncrono quando um container de queue worker for adicionado.
 *
 * @param  array<string, string|null>  $mapping  coluna de origem => campo-alvo
 */
class ProcessCsvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ImportBatch $batch,
        public array $mapping,
    ) {}

    public function handle(ImportCompaniesFromCsvAction $action): void
    {
        $this->batch->update(['status' => 'processing', 'started_at' => now()]);

        AuditContext::actingAs(
            AuditActorType::Import,
            $this->batch->id,
            "Importação CSV #{$this->batch->id} ({$this->batch->file_name})",
        );

        try {
            $reader = new CsvFileReader(Storage::disk('local')->path($this->batch->storedCsvPath()));
            $action->execute($this->batch, $reader, $this->mapping);

            $this->batch->update(['status' => 'completed', 'finished_at' => now()]);
        } catch (Throwable $e) {
            $this->batch->update(['status' => 'failed', 'finished_at' => now()]);

            throw $e;
        } finally {
            AuditContext::reset();
        }
    }
}
