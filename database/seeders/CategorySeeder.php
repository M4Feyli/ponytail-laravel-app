<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Jackor', 'description' => 'Tekniska jackor & ytterplagg'],
            ['name' => 'Kepsar', 'description' => 'Caps & huvudbonader'],
            ['name' => 'Skor', 'description' => 'Sneakers & skor'],
            ['name' => 'Tröjor', 'description' => 'Hoodies & tröjor'],
            ['name' => 'Väskor', 'description' => 'Väskor & ryggsäckar'],
            ['name' => 'Bälten', 'description' => 'Bälten'],
        ];

        foreach ($categories as $i => $cat) {
            Category::updateOrCreate(
                ['slug' => Str::slug($cat['name'])],
                [
                    'name' => $cat['name'],
                    'description' => $cat['description'],
                    'sort_order' => $i,
                ]
            );
        }
    }
}
