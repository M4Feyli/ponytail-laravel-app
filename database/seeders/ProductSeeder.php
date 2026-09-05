<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * A handful of generic, unbranded example products per category so
     * the shop and admin panel aren't empty on first run. Deliberately
     * NOT modeled on any real brand -- an admin replaces these with real
     * inventory (and real photos) via /admin.
     */
    public function run(): void
    {
        $data = [
            'Jackor' => [
                ['Vadderad vinterjacka, svart', 899, 'Mycket bra skick', ['S', 'M', 'L', 'XL', 'XXL']],
                ['Skaljacka i tekniskt material', 1299, 'Nyskick', ['S', 'M', 'L', 'XL']],
            ],
            'Kepsar' => [
                ['Klassisk 6-panel keps, svart', 199, 'Bra skick', ['One size']],
                ['Bucket hat, beige canvas', 249, 'Nyskick', ['One size']],
            ],
            'Skor' => [
                ['Vita låga sneakers', 699, 'Mycket bra skick', ['40', '41', '42', '43', '44', '45']],
                ['Chunky sneakers, grå', 899, 'Bra skick', ['38', '39', '40', '41', '42', '43', '44']],
            ],
            'Tröjor' => [
                ['Oversize hoodie, tvättad svart', 499, 'Mycket bra skick', ['S', 'M', 'L', 'XL', 'XXL']],
                ['Ribbad stickad tröja', 399, 'Nyskick', ['S', 'M', 'L']],
            ],
            'Väskor' => [
                ['Ryggsäck i nylon, vattentät', 599, 'Bra skick', ['One size']],
                ['Axelväska i kanvas', 449, 'Mycket bra skick', ['One size']],
            ],
            'Bälten' => [
                ['Kanvasbälte med metallspänne', 249, 'Bra skick', ['85', '90', '95', '100', '105']],
                ['Läderbälte, brunt', 349, 'Mycket bra skick', ['85', '90', '95', '100']],
            ],
        ];

        foreach ($data as $categoryName => $products) {
            $category = Category::where('slug', Str::slug($categoryName))->first();
            if (! $category) {
                continue;
            }

            foreach ($products as $i => [$name, $price, $condition, $sizes]) {
                $sku = 'NSV-' . Str::upper(Str::substr($categoryName, 0, 2)) . '-' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);

                $product = Product::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'category_id' => $category->id,
                        'name' => $name,
                        'slug' => Str::slug($name . '-' . $sku),
                        'description' => 'Exempelprodukt -- ersätt med riktigt lager via admin-panelen.',
                        'price' => $price,
                        'condition' => $condition,
                        'is_new' => $i === 0,
                        'is_active' => true,
                    ]
                );

                foreach ($sizes as $size) {
                    $product->variants()->updateOrCreate(
                        ['size' => $size],
                        ['stock' => random_int(1, 5)]
                    );
                }
            }
        }
    }
}
