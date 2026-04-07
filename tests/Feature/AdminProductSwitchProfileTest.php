<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductSwitchProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_featured_switch_with_valid_asset_profile(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $switchCategory = Category::create([
            'name' => 'Switches',
            'slug' => 'switches',
            'description' => 'Switch category',
        ]);

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $switchCategory->id,
            'name' => 'Crimson Linear Switch',
            'description' => 'Linear switch pack',
            'price' => 450000,
            'stock' => 10,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_asset_profile' => 'red',
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.switch_asset_profile', 'red')
            ->assertJsonPath('product.is_homepage_featured', true);
    }

    public function test_featured_switch_requires_asset_profile(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $switchCategory = Category::create([
            'name' => 'Switches',
            'slug' => 'switches',
            'description' => 'Switch category',
        ]);

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $switchCategory->id,
            'name' => 'Profileless Switch',
            'description' => 'Missing profile',
            'price' => 420000,
            'stock' => 5,
            'is_active' => true,
            'is_homepage_featured' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('switch_asset_profile');
    }

    public function test_admin_can_update_switch_asset_profile(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $switchCategory = Category::create([
            'name' => 'Switches',
            'slug' => 'switches',
            'description' => 'Switch category',
        ]);

        $product = Product::create([
            'category_id' => $switchCategory->id,
            'name' => 'Update Me Switch',
            'slug' => 'update-me-switch',
            'description' => 'Original switch',
            'price' => 420000,
            'stock' => 8,
            'is_active' => true,
            'is_homepage_featured' => false,
            'switch_asset_profile' => 'red',
        ]);

        $response = $this->putJson("/api/admin/products/{$product->id}", [
            'category_id' => $switchCategory->id,
            'name' => 'Updated Switch',
            'description' => 'Updated switch profile',
            'price' => 430000,
            'stock' => 12,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_asset_profile' => 'blue',
        ]);

        $response->assertOk()
            ->assertJsonPath('product.switch_asset_profile', 'blue')
            ->assertJsonPath('product.is_homepage_featured', true);
    }

    public function test_non_switch_product_cannot_be_featured_for_craft_your_click(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $keyboardCategory = Category::create([
            'name' => 'Keyboards',
            'slug' => 'keyboards',
            'description' => 'Keyboard category',
        ]);

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $keyboardCategory->id,
            'name' => 'Featured Keyboard',
            'description' => 'Should not be allowed',
            'price' => 2500000,
            'stock' => 4,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_asset_profile' => 'red',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('is_homepage_featured');
    }

    public function test_only_four_switch_products_can_be_featured_on_homepage(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $switchCategory = Category::create([
            'name' => 'Switches',
            'slug' => 'switches',
            'description' => 'Switch category',
        ]);

        foreach ([
            ['name' => 'Red Switch', 'slug' => 'red-switch', 'profile' => 'red'],
            ['name' => 'Brown Switch', 'slug' => 'brown-switch', 'profile' => 'brown'],
            ['name' => 'Blue Switch', 'slug' => 'blue-switch', 'profile' => 'blue'],
            ['name' => 'Black Switch', 'slug' => 'black-switch', 'profile' => 'black'],
        ] as $switch) {
            Product::create([
                'category_id' => $switchCategory->id,
                'name' => $switch['name'],
                'slug' => $switch['slug'],
                'description' => 'Existing featured switch',
                'price' => 400000,
                'stock' => 8,
                'is_active' => true,
                'is_homepage_featured' => true,
                'switch_asset_profile' => $switch['profile'],
            ]);
        }

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $switchCategory->id,
            'name' => 'Fifth Featured Switch',
            'description' => 'Should exceed featured limit',
            'price' => 410000,
            'stock' => 7,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_asset_profile' => 'red',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('is_homepage_featured');
    }

    public function test_public_featured_switch_products_include_asset_profile(): void
    {
        $switchCategory = Category::create([
            'name' => 'Switches',
            'slug' => 'switches',
            'description' => 'Switch category',
        ]);

        Product::create([
            'category_id' => $switchCategory->id,
            'name' => 'Featured Blue Switch',
            'slug' => 'featured-blue-switch',
            'description' => 'Homepage item',
            'price' => 430000,
            'stock' => 12,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_asset_profile' => 'blue',
        ]);

        Product::create([
            'category_id' => $switchCategory->id,
            'name' => 'Broken Featured Switch',
            'slug' => 'broken-featured-switch',
            'description' => 'Should be filtered out',
            'price' => 430000,
            'stock' => 12,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_asset_profile' => null,
        ]);

        $response = $this->getJson('/api/products?category=switches&featured_homepage=1&per_page=4');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.switch_asset_profile', 'blue');
    }

    protected function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }
}
