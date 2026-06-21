<?php

namespace App\Actions\Companies;

use App\Actions\Leads\CreateLeadAction;
use App\Models\City;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\CompanyFinancialSnapshot;
use App\Models\CompanyRegistrationStatusHistory;
use App\Models\CompanyTaxRegimeHistory;
use App\Models\ImportBatch;
use App\Models\ImportBatchError;
use App\Models\LeadSource;
use App\Models\LegalNature;
use App\Models\State;
use App\Support\Csv\CsvFileReader;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Processa um CSV linha a linha: dedupe por CNPJ (upsert em `companies`), satélites de
 * histórico só quando o valor mudou, e a regra de Lead já fechada com o usuário (ver
 * CreateLeadAction). Sócios (`company_partners`) ficam fora — escopo de uma importação futura.
 */
class ImportCompaniesFromCsvAction
{
    private const COMPANY_SIZES = ['MEI', 'ME', 'EPP', 'Medio', 'Grande'];

    private const REGISTRATION_STATUSES = ['ativa', 'suspensa', 'inapta', 'baixada'];

    private const TAX_REGIMES = ['simples', 'mei', 'presumido', 'real'];

    private ?int $csvLeadSourceId = null;

    public function __construct(private readonly CreateLeadAction $createLeadAction) {}

    /** @param array<string, string|null> $mapping coluna de origem => campo-alvo */
    public function execute(ImportBatch $batch, CsvFileReader $reader, array $mapping): void
    {
        $rowNumber = 1; // linha 1 é o cabeçalho
        $totalRows = 0;

        foreach ($reader->rows() as $row) {
            $rowNumber++;
            $totalRows++;

            $data = $this->mapRow($row, $mapping);

            if (blank($data['cnpj'] ?? null) || blank($data['razao_social'] ?? null)) {
                $this->recordError($batch, $rowNumber, $row, 'CNPJ ou razão social ausente.');

                continue;
            }

            try {
                DB::transaction(fn () => $this->processRow($batch, $data));
            } catch (Throwable $e) {
                $this->recordError($batch, $rowNumber, $row, $e->getMessage());
            }
        }

        $batch->update(['total_rows' => $totalRows]);
    }

    /** @param array<string, string|null> $data */
    private function processRow(ImportBatch $batch, array $data): void
    {
        $cnpj = preg_replace('/\D/', '', (string) $data['cnpj']);
        $attributes = $this->buildCompanyAttributes($data, $batch, $cnpj);

        $existing = Company::query()->where('cnpj', $cnpj)->first();
        $previous = $existing?->only([
            'estimated_revenue_value', 'employee_count', 'active_federal_debt', 'total_debt',
            'registration_status', 'tax_regime',
        ]);

        $company = Company::query()->updateOrCreate(['cnpj' => $cnpj], $attributes);

        $this->recordHistorySnapshots($company, $batch, $previous);
        $this->upsertPrimaryContacts($company, $data);

        $batch->increment($existing === null ? 'created_rows' : 'updated_rows');

        $this->createLeadIfEligible($company);
    }

    /** @param array<string, string|null> $data */
    private function buildCompanyAttributes(array $data, ImportBatch $batch, string $cnpj): array
    {
        $state = isset($data['uf']) ? State::query()->where('uf', mb_strtoupper($data['uf']))->first() : null;
        $city = isset($data['city_name']) && $state
            ? City::query()->where('state_id', $state->id)->whereRaw('UPPER(name) = ?', [mb_strtoupper($data['city_name'])])->first()
            : null;
        $cnae = isset($data['cnae_code']) ? Cnae::query()->where('code', $data['cnae_code'])->first() : null;
        $legalNature = isset($data['legal_nature_code']) ? LegalNature::query()->where('code', $data['legal_nature_code'])->first() : null;

        return [
            'cnpj' => $cnpj,
            'razao_social' => $data['razao_social'],
            'nome_fantasia' => $data['nome_fantasia'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cep' => isset($data['cep']) ? preg_replace('/\D/', '', $data['cep']) : null,
            'city_id' => $city?->id,
            'state_id' => $state?->id,
            'matriz_filial' => $this->normalizeMatrizFilial($data['matriz_filial'] ?? null),
            'ente_federativo' => $data['ente_federativo'] ?? null,
            'primary_cnae_id' => $cnae?->id,
            'legal_nature_id' => $legalNature?->id,
            'data_inicio_atividade' => $this->parseDate($data['data_inicio_atividade'] ?? null),
            'company_size' => $this->normalizeFromList($data['company_size'] ?? null, self::COMPANY_SIZES),
            'share_capital' => $this->parseDecimal($data['share_capital'] ?? null),
            'is_mei' => $this->parseBool($data['is_mei'] ?? null),
            'mei_entry_date' => $this->parseDate($data['mei_entry_date'] ?? null),
            'mei_exit_date' => $this->parseDate($data['mei_exit_date'] ?? null),
            'registration_status' => $this->normalizeFromList($data['registration_status'] ?? null, self::REGISTRATION_STATUSES),
            'registration_status_date' => $this->parseDate($data['registration_status_date'] ?? null),
            'tax_regime' => $this->normalizeFromList($data['tax_regime'] ?? null, self::TAX_REGIMES),
            'estimated_revenue_value' => $this->parseDecimal($data['estimated_revenue_value'] ?? null),
            'employee_count' => $this->parseInt($data['employee_count'] ?? null),
            'active_federal_debt' => $this->parseDecimal($data['active_federal_debt'] ?? null),
            'total_debt' => $this->parseDecimal($data['total_debt'] ?? null),
            'site' => $data['site'] ?? null,
            'data_provider' => $batch->data_provider,
            'license_reference' => $batch->license_reference,
            'last_import_batch_id' => $batch->id,
            'last_enriched_at' => now(),
        ];
    }

    private function recordHistorySnapshots(Company $company, ImportBatch $batch, ?array $previous): void
    {
        $current = $company->only([
            'estimated_revenue_value', 'employee_count', 'active_federal_debt', 'total_debt',
            'registration_status', 'tax_regime',
        ]);

        $financialChanged = $previous === null
            ? ($current['estimated_revenue_value'] !== null || $current['employee_count'] !== null || $current['active_federal_debt'] !== null)
            : ((string) $previous['estimated_revenue_value'] !== (string) $current['estimated_revenue_value']
                || $previous['employee_count'] !== $current['employee_count']
                || (string) $previous['active_federal_debt'] !== (string) $current['active_federal_debt']
                || (string) $previous['total_debt'] !== (string) $current['total_debt']);

        if ($financialChanged) {
            CompanyFinancialSnapshot::create([
                'company_id' => $company->id,
                'snapshot_date' => now()->toDateString(),
                'revenue_value' => $current['estimated_revenue_value'],
                'employee_count' => $current['employee_count'],
                'debt_value' => $current['active_federal_debt'],
                'import_batch_id' => $batch->id,
            ]);
        }

        $statusChanged = $previous === null
            ? $current['registration_status'] !== null
            : $previous['registration_status'] !== $current['registration_status'];

        if ($statusChanged && $current['registration_status'] !== null) {
            CompanyRegistrationStatusHistory::create([
                'company_id' => $company->id,
                'status' => $current['registration_status'],
                'changed_at' => now(),
                'import_batch_id' => $batch->id,
            ]);
        }

        $regimeChanged = $previous === null
            ? $current['tax_regime'] !== null
            : $previous['tax_regime'] !== $current['tax_regime'];

        if ($regimeChanged && $current['tax_regime'] !== null) {
            CompanyTaxRegimeHistory::create([
                'company_id' => $company->id,
                'regime' => $current['tax_regime'],
                'changed_at' => now(),
                'import_batch_id' => $batch->id,
            ]);
        }
    }

    /** @param array<string, string|null> $data */
    private function upsertPrimaryContacts(Company $company, array $data): void
    {
        $contactFields = ['contact_phone' => 'phone', 'contact_whatsapp' => 'whatsapp', 'contact_email' => 'email'];

        foreach ($contactFields as $field => $type) {
            if (blank($data[$field] ?? null)) {
                continue;
            }

            $company->contacts()->updateOrCreate(
                ['type' => $type, 'is_primary' => true],
                ['value' => $data[$field]],
            );
        }
    }

    private function createLeadIfEligible(Company $company): void
    {
        if ($company->leads()->exists()) {
            return; // já tem lead (aberto ou só terminal) — nunca cria automaticamente
        }

        try {
            $this->csvLeadSourceId ??= LeadSource::where('name', 'CSV')->value('id');
            $this->createLeadAction->execute($company, [], $this->csvLeadSourceId);
        } catch (DomainException) {
            // condição de corrida improvável dentro da própria transação; ignora silenciosamente
        }
    }

    /** @param array<string, string|null> $row */
    private function recordError(ImportBatch $batch, int $rowNumber, array $row, string $message): void
    {
        ImportBatchError::create([
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'raw_data' => $row,
            'error_message' => $message,
        ]);

        $batch->increment('skipped_rows');
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, string|null>  $mapping
     * @return array<string, string|null>
     */
    private function mapRow(array $row, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $sourceColumn => $targetField) {
            if ($targetField === null) {
                continue;
            }

            $value = isset($row[$sourceColumn]) ? trim((string) $row[$sourceColumn]) : null;
            $data[$targetField] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function normalizeMatrizFilial(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim($value));

        return match (true) {
            in_array($normalized, ['1', 'MATRIZ'], true) => 'matriz',
            in_array($normalized, ['2', 'FILIAL'], true) => 'filial',
            default => null,
        };
    }

    /** @param array<int, string> $allowed */
    private function normalizeFromList(?string $value, array $allowed): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtoupper(trim($value));

        foreach ($allowed as $candidate) {
            if (mb_strtoupper($candidate) === $normalized) {
                return $candidate;
            }
        }

        return null;
    }

    private function parseDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        foreach (['Ymd', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function parseDecimal(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = str_replace('.', '', $value);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^0-9.\-]/', '', $normalized);

        return $normalized === '' || $normalized === null ? null : $normalized;
    }

    private function parseInt(?string $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        return $digits === '' || $digits === null ? null : (int) $digits;
    }

    private function parseBool(?string $value): bool
    {
        if (blank($value)) {
            return false;
        }

        return in_array(mb_strtoupper(trim($value)), ['1', 'S', 'SIM', 'TRUE', 'YES'], true);
    }
}
