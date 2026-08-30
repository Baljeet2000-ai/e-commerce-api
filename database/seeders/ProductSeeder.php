<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::factory(20)->create();

        // Un producto fijo, util para probar el flujo de compra de forma predecible
        Product::factory()->create([
            'name' => 'Laptop Lenovo ThinkPad',
            'description' => 'Laptop para oficina, 16GB RAM, 512GB SSD',
            'price' => 899.99,
            'stock' => 15,
        ]);
    }
}
