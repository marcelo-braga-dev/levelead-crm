<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'import_profile_id', 'file_name', 'data_provider', 'legal_basis', 'license_reference',
    'total_rows', 'created_rows', 'updated_rows', 'skipped_rows', 'status', 'started_at', 'finished_at',
])]
class ImportBatch extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function importProfile(): BelongsTo
    {
        return $this->belongsTo(ImportProfile::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportBatchError::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'last_import_batch_id');
    }
}
