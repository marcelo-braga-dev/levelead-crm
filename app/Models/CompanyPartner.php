<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'identificador', 'nome', 'cpf', 'tipo_documento', 'faixa_etaria', 'partner_qualification_id', 'data_entrada'])]
class CompanyPartner extends Model
{
    protected function casts(): array
    {
        return [
            // CPF criptografado em repouso — acesso deve ser restrito a admin/manager
            // e cada leitura registrada em audit_logs (ver CompanyPolicy/AuditLogPolicy).
            'cpf' => 'encrypted',
            'data_entrada' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function partnerQualification(): BelongsTo
    {
        return $this->belongsTo(PartnerQualification::class);
    }
}
