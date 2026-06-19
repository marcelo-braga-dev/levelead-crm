<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'status', 'changed_at', 'import_batch_id'])]
class CompanyRegistrationStatusHistory extends Model
{
    // "History" pluraliza para "Histories" por padrão — a tabela usa o nome singular do plano.
    protected $table = 'company_registration_status_history';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
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
