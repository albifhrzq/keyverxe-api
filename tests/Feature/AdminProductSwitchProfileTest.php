<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminProductSwitchProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_featured_switch_with_color_type_and_sound(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $switchCategory = Category::create([
            'name' => 'Switches',
            'slug' => 'switches',
            'description' => 'Switch category',
        ]);

        $response = $this->post('/api/admin/products', [
            'category_id' => $switchCategory->id,
            'name' => 'Crimson Linear Switch',
            'description' => 'Linear switch pack',
            'price' => 450000,
            'stock' => 10,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_color' => '#EF4444',
            'switch_type' => 'linear',
            'switch_sounds' => [
                UploadedFile::fake()->create('red-1.mp3', 120, 'audio/mpeg'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('product.switch_color', '#EF4444')
            ->assertJsonPath('product.switch_type', 'linear')
            ->assertJsonPath('product.is_homepage_featured', true);

        $this->assertCount(1, $response->json('product.switch_sound_paths'));
    }

    public function test_switch_product_requires_sound_upload(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $switchCategory = Category::create([
            'name' => 'Switches',
            'slug' => 'switches',
            'description' => 'Switch category',
        ]);

        $response = $this->post('/api/admin/products', [
            'category_id' => $switchCategory->id,
            'name' => 'Profileless Switch',
            'description' => 'Missing profile',
            'price' => 420000,
            'stock' => 5,
            'is_active' => true,
            'is_homepage_featured' => false,
            'switch_color' => '#22D3EE',
            'switch_type' => 'clicky',
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('switch_sounds');
    }

    public function test_admin_can_update_switch_media(): void
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
            'switch_color' => '#EF4444',
            'switch_type' => 'linear',
            'switch_sound_paths' => ['/sounds/red-1.mp3'],
        ]);

        $response = $this->post("/api/admin/products/{$product->id}", [
            '_method' => 'PUT',
            'category_id' => $switchCategory->id,
            'name' => 'Updated Switch',
            'description' => 'Updated switch profile',
            'price' => 430000,
            'stock' => 12,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_color' => '#38BDF8',
            'switch_type' => 'clicky',
            'switch_sounds' => [
                UploadedFile::fake()->create('blue-1.mp3', 120, 'audio/mpeg'),
                UploadedFile::fake()->create('blue-2.mp3', 120, 'audio/mpeg'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('product.switch_color', '#38BDF8')
            ->assertJsonPath('product.switch_type', 'clicky')
            ->assertJsonPath('product.is_homepage_featured', true);

        $this->assertCount(2, $response->json('product.switch_sound_paths'));
    }

    public function test_non_switch_product_cannot_be_featured_for_craft_your_click(): void
    {
        Sanctum::actingAs($this->createAdminUser());

        $keyboardCategory = Category::create([
            'name' => 'Keyboards',
            'slug' => 'keyboards',
            'description' => 'Keyboard category',
        ]);

        $response = $this->post('/api/admin/products', [
            'category_id' => $keyboardCategory->id,
            'name' => 'Featured Keyboard',
            'description' => 'Should not be allowed',
            'price' => 2500000,
            'stock' => 4,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_color' => '#EF4444',
            'switch_type' => 'linear',
        ], [
            'Accept' => 'application/json',
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
            ['name' => 'Red Switch', 'slug' => 'red-switch', 'color' => '#EF4444', 'type' => 'linear'],
            ['name' => 'Brown Switch', 'slug' => 'brown-switch', 'color' => '#C08457', 'type' => 'tactile'],
            ['name' => 'Blue Switch', 'slug' => 'blue-switch', 'color' => '#38BDF8', 'type' => 'clicky'],
            ['name' => 'Black Switch', 'slug' => 'black-switch', 'color' => '#94A3B8', 'type' => 'silent'],
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
                'switch_color' => $switch['color'],
                'switch_type' => $switch['type'],
                'switch_sound_paths' => ['/sounds/red-1.mp3'],
            ]);
        }

        $response = $this->post('/api/admin/products', [
            'category_id' => $switchCategory->id,
            'name' => 'Fifth Featured Switch',
            'description' => 'Should exceed featured limit',
            'price' => 410000,
            'stock' => 7,
            'is_active' => true,
            'is_homepage_featured' => true,
            'switch_color' => '#EF4444',
            'switch_type' => 'linear',
            'switch_sounds' => [
                UploadedFile::fake()->create('red-1.mp3', 120, 'audio/mpeg'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('is_homepage_featured');
    }

    public function test_public_featured_switch_products_require_color_and_type(): void
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
            'switch_color' => '#38BDF8',
            'switch_type' => 'clicky',
            'switch_sound_paths' => ['/sounds/blue-1.mp3'],
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
            'switch_color' => '#38BDF8',
            'switch_type' => null,
            'switch_sound_paths' => ['/sounds/blue-1.mp3'],
        ]);

        $response = $this->getJson('/api/products?category=switches&featured_homepage=1&per_page=4');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.switch_color', '#38BDF8')
            ->assertJsonPath('data.0.switch_type', 'clicky');
    }

    protected function createAdminUser(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }
}
