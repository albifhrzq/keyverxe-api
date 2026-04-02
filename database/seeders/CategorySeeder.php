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
            [
                'name' => 'Keyboards',
                'slug' => 'keyboards',
                'description' => 'Premium mechanical keyboards crafted for precision and performance. From compact 60% layouts to full-size workhorses.',
            ],
            [
                'name' => 'Switches',
                'slug' => 'switches',
                'description' => 'Mechanical key switches in linear, tactile, and clicky variants. Find your perfect typing feel.',
            ],
            [
                'name' => 'Keycaps',
                'slug' => 'keycaps',
                'description' => 'Custom keycap sets in PBT and ABS profiles. Cherry, SA, and DSA profiles available.',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
