<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'description'])]
class Cnae extends Model
{
    public function primaryCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'primary_cnae_id');
    }

    public function secondaryActivities(): HasMany
    {
        return $this->hasMany(CompanySecondaryActivity::class);
    }
}
