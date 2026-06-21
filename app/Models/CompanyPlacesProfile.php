<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'google_place_id', 'latitude', 'longitude', 'rating', 'user_rating_count',
    'primary_type', 'opening_hours', 'business_status', 'has_website', 'last_review_at', 'synced_at',
])]
class CompanyPlacesProfile extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'rating' => 'decimal:1',
            'opening_hours' => 'array',
            'has_website' => 'boolean',
            'last_review_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
