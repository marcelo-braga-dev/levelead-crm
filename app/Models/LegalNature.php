<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'description'])]
class LegalNature extends Model
{
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
