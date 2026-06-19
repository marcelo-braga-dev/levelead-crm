<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'description'])]
class PartnerQualification extends Model
{
    public function companyPartners(): HasMany
    {
        return $this->hasMany(CompanyPartner::class);
    }
}
