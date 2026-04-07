<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductKeycapTextureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_keycap_product_with_texture_uv(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $keycapCategory = Category::create([
            'name' => 'Keycaps',
            'slug' => 'keycaps',
            'description' => 'Keycap category',
        ]);

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $keycapCategory->id,
            'name' => 'Midnight Cherry PBT Keycaps',
            'description' => 'Keycap set with UV texture',
            'price' => 650000,
            'stock' => 20,
            'is_active' => true,
            'keycap_texture_uv' => '/cherrynavy_uv.png',
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.keycap_texture_uv', '/cherrynavy_uv.png');
    }

    public function test_non_keycap_product_clears_keycap_texture_uv(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $keyboardCategory = Category::create([
            'name' => 'Keyboards',
            'slug' => 'keyboards',
            'description' => 'Keyboard category',
        ]);

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $keyboardCategory->id,
            'name' => 'Textureless Keyboard',
            'description' => 'Keyboard should ignore keycap texture field',
            'price' => 2500000,
            'stock' => 5,
            'is_active' => true,
            'keycap_texture_uv' => '/dreamboard_uv.png',
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.keycap_texture_uv', null);
    }

    public function test_public_keycap_filter_returns_only_products_with_texture_uv(): void
    {
        $keycapCategory = Category::create([
            'name' => 'Keycaps',
            'slug' => 'keycaps',
            'description' => 'Keycap category',
        ]);

        Product::create([
            'category_id' => $keycapCategory->id,
            'name' => 'Visible Keycap Set',
            'slug' => 'visible-keycap-set',
            'description' => 'Ready for homepage customizer',
            'price' => 640000,
            'stock' => 15,
            'is_active' => true,
            'keycap_texture_uv' => '/goodwell_uv.png',
        ]);

        Product::create([
            'category_id' => $keycapCategory->id,
            'name' => 'Hidden Keycap Set',
            'slug' => 'hidden-keycap-set',
            'description' => 'Missing UV texture',
            'price' => 640000,
            'stock' => 15,
            'is_active' => true,
            'keycap_texture_uv' => null,
        ]);

        $response = $this->getJson('/api/products?category=keycaps&has_keycap_texture=1&per_page=6');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.keycap_texture_uv', '/goodwell_uv.png');
    }

    protected function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }
}
