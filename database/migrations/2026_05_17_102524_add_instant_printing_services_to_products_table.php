<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Category;
use App\Models\Product;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get or create Jasa Cetak category
        $category = Category::where('type', 'jasa')->first();
        if (!$category) {
            $category = Category::create([
                'name' => 'Jasa Cetak',
                'slug' => 'jasa-cetak',
                'type' => 'jasa'
            ]);
        }

        $services = [
            [
                'sku' => 'JSA-FTK-01',
                'name' => 'Fotokopi Hitam Putih',
                'selling_price' => 200,
                'base_price' => 100,
                'unit' => 'lembar'
            ],
            [
                'sku' => 'JSA-FTK-02',
                'name' => 'Fotokopi Warna',
                'selling_price' => 1500,
                'base_price' => 500,
                'unit' => 'lembar'
            ],
            [
                'sku' => 'JSA-PRN-01',
                'name' => 'Print Hitam Putih',
                'selling_price' => 500,
                'base_price' => 250,
                'unit' => 'lembar'
            ],
            [
                'sku' => 'JSA-PRN-02',
                'name' => 'Print Warna',
                'selling_price' => 2000,
                'base_price' => 800,
                'unit' => 'lembar'
            ]
        ];

        foreach ($services as $service) {
            Product::updateOrCreate(
                ['sku' => $service['sku']],
                [
                    'category_id' => $category->id,
                    'name' => $service['name'],
                    'selling_price' => $service['selling_price'],
                    'base_price' => $service['base_price'],
                    'unit' => $service['unit'],
                    'is_active' => true
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Deactivate or delete the added products if needed
        Product::whereIn('sku', ['JSA-FTK-02', 'JSA-PRN-01', 'JSA-PRN-02'])->delete();
        
        // Restore generic fotokopi name
        Product::where('sku', 'JSA-FTK-01')->update(['name' => 'Fotokopi']);
    }
};
