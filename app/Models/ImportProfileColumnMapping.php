<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_profile_id', 'source_column_label', 'target_field'])]
class ImportProfileColumnMapping extends Model
{
    public function importProfile(): BelongsTo
    {
        return $this->belongsTo(ImportProfile::class);
    }
}
