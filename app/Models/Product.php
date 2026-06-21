<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name'])]
class Product extends Model
{
    use SoftDeletes;

    public function commissionRules(): HasMany
    {
        return $this->hasMany(CommissionRule::class);
    }

    public function wonLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'won_product_id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
