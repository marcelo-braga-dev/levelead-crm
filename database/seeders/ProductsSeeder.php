<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Plano Essencial', 'Plano Profissional', 'Plano Enterprise'] as $name) {
            Product::query()->updateOrCreate(['name' => $name]);
        }
    }
}
