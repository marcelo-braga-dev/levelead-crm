<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'snapshot_date', 'revenue_value', 'employee_count', 'debt_value', 'import_batch_id'])]
class CompanyFinancialSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'revenue_value' => 'decimal:2',
            'debt_value' => 'decimal:2',
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
