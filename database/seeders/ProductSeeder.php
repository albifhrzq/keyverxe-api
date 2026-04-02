<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $keyboards = Category::where('slug', 'keyboards')->first();
        $switches  = Category::where('slug', 'switches')->first();
        $keycaps   = Category::where('slug', 'keycaps')->first();

        $products = [
            // Keyboards (3)
            [
                'category_id' => $keyboards->id,
                'name' => 'Vapor 75',
                'slug' => 'vapor-75',
                'description' => 'A premium 75% gasket-mounted mechanical keyboard with aluminum case, hot-swappable PCB, south-facing RGB LEDs, and a rotary knob. The perfect balance between compact and functional.',
                'price' => 2500000,
                'stock' => 25,
                'is_active' => true,
            ],
            [
                'category_id' => $keyboards->id,
                'name' => 'Phantom 65',
                'slug' => 'phantom-65',
                'description' => 'A sleek 65% wireless mechanical keyboard with Bluetooth 5.0 and 2.4GHz connectivity. CNC aluminum frame with silicone dampening for a deep, thocky sound profile.',
                'price' => 1850000,
                'stock' => 30,
                'is_active' => true,
            ],
            [
                'category_id' => $keyboards->id,
                'name' => 'Eclipse TKL',
                'slug' => 'eclipse-tkl',
                'description' => 'Tenkeyless layout with a polycarbonate bottom case for stunning RGB underglow. Features a flex-cut PCB and Poron foam layers for an unmatched typing experience.',
                'price' => 3200000,
                'stock' => 15,
                'is_active' => true,
            ],

            // Switches (3)
            [
                'category_id' => $switches->id,
                'name' => 'Crimson Linear Switch (70pcs)',
                'slug' => 'crimson-linear-switch',
                'description' => 'Ultra-smooth linear switches with a 45g actuation force. Pre-lubed with Krytox 205g0 for a buttery keystroke. POM stem, nylon housing. 70 switches per pack.',
                'price' => 450000,
                'stock' => 100,
                'is_active' => true,
            ],
            [
                'category_id' => $switches->id,
                'name' => 'Nebula Tactile Switch (70pcs)',
                'slug' => 'nebula-tactile-switch',
                'description' => 'A refined tactile switch with a satisfying bump at 55g. Long-pole stem for a sharp bottom-out sound. Factory lubed. Perfect for typists who want feedback without the click.',
                'price' => 520000,
                'stock' => 80,
                'is_active' => true,
            ],
            [
                'category_id' => $switches->id,
                'name' => 'Frost Silent Linear (70pcs)',
                'slug' => 'frost-silent-linear',
                'description' => 'Silent linear switches designed for office and late-night use. Dampening pads reduce noise by 90% without sacrificing feel. 40g actuation force.',
                'price' => 480000,
                'stock' => 60,
                'is_active' => true,
            ],

            // Keycaps (3)
            [
                'category_id' => $keycaps->id,
                'name' => 'Midnight Cherry PBT Keycaps',
                'slug' => 'midnight-cherry-pbt',
                'description' => 'Double-shot PBT keycap set in Cherry profile. Deep navy blue legends on charcoal bases. 143 keys — compatible with most layouts including 65%, 75%, TKL, and full-size.',
                'price' => 650000,
                'stock' => 45,
                'is_active' => true,
            ],
            [
                'category_id' => $keycaps->id,
                'name' => 'Sakura DSA Keycaps',
                'slug' => 'sakura-dsa-keycaps',
                'description' => 'Dye-sublimated PBT keycap set in DSA profile featuring a cherry blossom theme. Pastel pink and white colorway with Japanese sub-legends. 128 keys.',
                'price' => 550000,
                'stock' => 35,
                'is_active' => true,
            ],
            [
                'category_id' => $keycaps->id,
                'name' => 'Carbon SA Keycaps',
                'slug' => 'carbon-sa-keycaps',
                'description' => 'ABS double-shot SA profile keycap set inspired by the iconic Carbon colorway. High-profile sculpted caps with orange and dark gray tones. 156 keys.',
                'price' => 750000,
                'stock' => 20,
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}
