<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'regime', 'changed_at', 'import_batch_id'])]
class CompanyTaxRegimeHistory extends Model
{
    // "History" pluraliza para "Histories" por padrão — a tabela usa o nome singular do plano.
    protected $table = 'company_tax_regime_history';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
