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
                'name' => 'Vapor 75 Blue Switch',
                'slug' => 'vapor-75',
                'description' => 'A complete 75% keyboard build with a gasket mount, aluminum case, hot-swappable PCB, and rotary knob. This setup uses Azure Clicky (Blue) switches for a crisp tactile feel and a lively clicky sound. It is paired with navy Midnight Cherry PBT keycaps for a premium, high-contrast look.',
                'price' => 2500000,
                'stock' => 25,
                'is_active' => true,
                'keyboard_texture_uv' => '/goodwell_uv.png',
            ],
            [
                'category_id' => $keyboards->id,
                'name' => 'Phantom 65 Brown Switch',
                'slug' => 'phantom-65',
                'description' => 'A complete 65% keyboard build with Bluetooth 5.0 and 2.4GHz connectivity for flexible daily use. This setup features Nebula Tactile (Brown) switches with a satisfying tactile bump that is ideal for typing. It uses navy Midnight Cherry PBT keycaps to keep the overall look clean and elegant.',
                'price' => 1850000,
                'stock' => 30,
                'is_active' => true,
                'keyboard_texture_uv' => '/dreamboard_uv.png',
            ],
            [
                'category_id' => $keyboards->id,
                'name' => 'Eclipse TKL Black Switch',
                'slug' => 'eclipse-tkl',
                'description' => 'A complete TKL keyboard build with a polycarbonate bottom case, flex-cut PCB, and Poron foam layers for a more controlled typing acoustics profile. This setup uses Frost Silent Linear (Black) switches to deliver smooth keypresses with reduced noise during long sessions. Navy Midnight Cherry PBT keycaps complete the dark, minimal visual theme.',
                'price' => 3200000,
                'stock' => 15,
                'is_active' => true,
                'keyboard_texture_uv' => '/oldschool_uv.png',
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
                'is_homepage_featured' => true,
                'switch_color' => '#EF4444',
                'switch_type' => 'linear',
                'switch_sound_paths' => ['/sounds/red-1.mp3', '/sounds/red-2.mp3', '/sounds/red-3.mp3'],
            ],
            [
                'category_id' => $switches->id,
                'name' => 'Nebula Tactile Switch (70pcs)',
                'slug' => 'nebula-tactile-switch',
                'description' => 'A refined tactile switch with a satisfying bump at 55g. Long-pole stem for a sharp bottom-out sound. Factory lubed. Perfect for typists who want feedback without the click.',
                'price' => 520000,
                'stock' => 80,
                'is_active' => true,
                'is_homepage_featured' => true,
                'switch_color' => '#C08457',
                'switch_type' => 'tactile',
                'switch_sound_paths' => ['/sounds/brown-1.mp3', '/sounds/brown-2.mp3', '/sounds/brown-3.mp3'],
            ],
            [
                'category_id' => $switches->id,
                'name' => 'Frost Silent Linear (70pcs)',
                'slug' => 'frost-silent-linear',
                'description' => 'Silent linear switches designed for office and late-night use. Dampening pads reduce noise by 90% without sacrificing feel. 40g actuation force.',
                'price' => 480000,
                'stock' => 60,
                'is_active' => true,
                'is_homepage_featured' => true,
                'switch_color' => '#94A3B8',
                'switch_type' => 'silent',
                'switch_sound_paths' => ['/sounds/black-1.mp3', '/sounds/black-2.mp3', '/sounds/black-3.mp3'],
            ],
            [
                'category_id' => $switches->id,
                'name' => 'Azure Clicky Switch (70pcs)',
                'slug' => 'azure-clicky-switch',
                'description' => 'Clicky switches with a bright tactile snap and a crisp top-end sound. Built for typists who want every press to feel alive. 70 switches per pack.',
                'price' => 495000,
                'stock' => 55,
                'is_active' => true,
                'is_homepage_featured' => true,
                'switch_color' => '#38BDF8',
                'switch_type' => 'clicky',
                'switch_sound_paths' => ['/sounds/blue-1.mp3', '/sounds/blue-2.mp3', '/sounds/blue-3.mp3'],
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
                'keycap_texture_uv' => '/keycap_uv-1.png',
            ],
            [
                'category_id' => $keycaps->id,
                'name' => 'Sakura DSA Keycaps',
                'slug' => 'sakura-dsa-keycaps',
                'description' => 'Dye-sublimated PBT keycap set in DSA profile featuring a cherry blossom theme. Pastel pink and white colorway with Japanese sub-legends. 128 keys.',
                'price' => 550000,
                'stock' => 35,
                'is_active' => true,
                'keycap_texture_uv' => '/keycap_uv-2.png',
            ],
            [
                'category_id' => $keycaps->id,
                'name' => 'Carbon SA Keycaps',
                'slug' => 'carbon-sa-keycaps',
                'description' => 'ABS double-shot SA profile keycap set inspired by the iconic Carbon colorway. High-profile sculpted caps with orange and dark gray tones. 156 keys.',
                'price' => 750000,
                'stock' => 20,
                'is_active' => true,
                'keycap_texture_uv' => '/keycap_uv-4.png',
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['slug' => $product['slug']],
                $product
            );
        }
    }
}
