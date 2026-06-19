<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uf', 'name', 'ibge_code'])]
class State extends Model
{
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
