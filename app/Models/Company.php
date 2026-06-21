<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\LeadStage;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'cnpj', 'razao_social', 'nome_fantasia',
    'address_type', 'logradouro', 'numero', 'complemento', 'bairro', 'city_id', 'state_id', 'cep',
    'matriz_filial', 'ente_federativo',
    'primary_cnae_id', 'legal_nature_id',
    'data_inicio_atividade', 'company_size',
    'share_capital', 'is_mei', 'mei_entry_date', 'mei_exit_date',
    'registration_status', 'registration_status_date',
    'tax_regime',
    'estimated_revenue_value', 'employee_count',
    'active_federal_debt', 'total_debt',
    'site', 'data_provider', 'license_reference', 'last_import_batch_id', 'last_enriched_at',
])]
class Company extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'data_inicio_atividade' => 'date',
            'is_mei' => 'boolean',
            'mei_entry_date' => 'date',
            'mei_exit_date' => 'date',
            'registration_status' => RegistrationStatus::class,
            'registration_status_date' => 'date',
            'share_capital' => 'decimal:2',
            'estimated_revenue_value' => 'decimal:2',
            'active_federal_debt' => 'decimal:2',
            'total_debt' => 'decimal:2',
            'last_enriched_at' => 'datetime',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function primaryCnae(): BelongsTo
    {
        return $this->belongsTo(Cnae::class, 'primary_cnae_id');
    }

    public function legalNature(): BelongsTo
    {
        return $this->belongsTo(LegalNature::class);
    }

    public function lastImportBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'last_import_batch_id');
    }

    public function secondaryActivities(): HasMany
    {
        return $this->hasMany(CompanySecondaryActivity::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(CompanyPartner::class);
    }

    public function specialPrograms(): HasMany
    {
        return $this->hasMany(CompanySpecialProgram::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CompanyContact::class);
    }

    public function financialSnapshots(): HasMany
    {
        return $this->hasMany(CompanyFinancialSnapshot::class);
    }

    public function registrationStatusHistory(): HasMany
    {
        return $this->hasMany(CompanyRegistrationStatusHistory::class);
    }

    public function taxRegimeHistory(): HasMany
    {
        return $this->hasMany(CompanyTaxRegimeHistory::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function optOuts(): HasMany
    {
        return $this->hasMany(OptOut::class);
    }

    public function placesProfile(): HasOne
    {
        return $this->hasOne(CompanyPlacesProfile::class);
    }

    /**
     * Companies sem nenhum lead, ou cujos leads existentes estão todos em estágio terminal
     * (Won/Lost) — candidatas a reciclagem. Reabertura é sempre uma ação manual.
     */
    public function scopeWithoutActiveLead(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->doesntHave('leads')
                ->orWhere(function (Builder $q2) {
                    $q2->has('leads')
                        ->whereDoesntHave('leads', function (Builder $q3) {
                            $q3->whereNotIn('stage', [LeadStage::Won->value, LeadStage::Lost->value]);
                        });
                });
        });
    }
}
