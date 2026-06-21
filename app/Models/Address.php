<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Endereço de uma Company — tabela satélite (1:1, mesmo padrão de `company_places_profiles`),
 * extraída de `companies` para permitir edição própria a partir do Kanban (aba Mapa) sem misturar
 * com o restante do cadastro. RBAC de edição é o mesmo de `Company::update` (admin/manager) — ver
 * `CompanyPolicy`, reaplicado em `CompanyController::updateAddress()`.
 */
#[Fillable(['company_id', 'address_type', 'logradouro', 'numero', 'complemento', 'bairro', 'city_id', 'state_id', 'cep'])]
class Address extends Model
{
    use Auditable;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }
}
