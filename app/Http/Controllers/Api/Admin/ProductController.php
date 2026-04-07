<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * List all products with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $products = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($products);
    }

    /**
     * Store a new product.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['boolean'],
            'is_homepage_featured' => ['nullable', 'boolean'],
            'switch_asset_profile' => ['nullable', 'string', Rule::in(Product::SWITCH_ASSET_PROFILES)],
        ]);
        $validated['is_homepage_featured'] = $request->boolean('is_homepage_featured');
        $validated['switch_asset_profile'] = $this->normalizeSwitchAssetProfile(
            (int) $validated['category_id'],
            $validated['switch_asset_profile'] ?? null,
        );
        $this->validateHomepageFeature(
            (int) $validated['category_id'],
            $validated['is_homepage_featured'],
            $validated['switch_asset_profile'],
        );

        $validated['slug'] = Str::slug($validated['name']);

        // Check slug uniqueness
        if (Product::where('slug', $validated['slug'])->exists()) {
            return response()->json([
                'message' => 'A product with a similar name already exists.',
                'errors' => ['name' => ['A product with a similar name already exists.']],
            ], 422);
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($validated);
        $product->load('category');

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
        ], 201);
    }

    /**
     * Show a single product.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load('category');

        return response()->json($product);
    }

    /**
     * Update a product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'is_active' => ['boolean'],
            'is_homepage_featured' => ['nullable', 'boolean'],
            'switch_asset_profile' => ['nullable', 'string', Rule::in(Product::SWITCH_ASSET_PROFILES)],
        ]);
        $validated['is_homepage_featured'] = $request->boolean('is_homepage_featured');
        $validated['switch_asset_profile'] = $this->normalizeSwitchAssetProfile(
            (int) $validated['category_id'],
            $validated['switch_asset_profile'] ?? null,
        );
        $this->validateHomepageFeature(
            (int) $validated['category_id'],
            $validated['is_homepage_featured'],
            $validated['switch_asset_profile'],
            $product->id,
        );

        $newSlug = Str::slug($validated['name']);

        if (Product::where('slug', $newSlug)->where('id', '!=', $product->id)->exists()) {
            return response()->json([
                'message' => 'A product with a similar name already exists.',
                'errors' => ['name' => ['A product with a similar name already exists.']],
            ], 422);
        }

        $validated['slug'] = $newSlug;

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);
        $product->load('category');

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh()->load('category'),
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check if product has order items
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product that has been ordered.',
            ], 422);
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Featured homepage slots are reserved for up to 4 switch products.
     */
    protected function validateHomepageFeature(
        int $categoryId,
        bool $isHomepageFeatured,
        ?string $switchAssetProfile,
        ?int $ignoreProductId = null,
    ): void
    {
        $isSwitchCategory = Category::query()
            ->whereKey($categoryId)
            ->where('slug', 'switches')
            ->exists();

        if (!$isSwitchCategory) {
            if (!$isHomepageFeatured) {
                return;
            }

            throw ValidationException::withMessages([
                'is_homepage_featured' => ['Only switch products can appear in Craft Your Click.'],
            ]);
        }

        if ($isHomepageFeatured && !$switchAssetProfile) {
            throw ValidationException::withMessages([
                'switch_asset_profile' => ['Choose a switch asset profile before featuring this product on the homepage.'],
            ]);
        }

        if (!$isHomepageFeatured) {
            return;
        }

        $featuredCount = Product::query()
            ->where('is_homepage_featured', true)
            ->whereHas('category', function ($query) {
                $query->where('slug', 'switches');
            })
            ->when($ignoreProductId, function ($query, $ignoreProductId) {
                $query->where('id', '!=', $ignoreProductId);
            })
            ->count();

        if ($featuredCount >= 4) {
            throw ValidationException::withMessages([
                'is_homepage_featured' => ['Only 4 switch products can be featured on the homepage at a time.'],
            ]);
        }
    }

    protected function normalizeSwitchAssetProfile(int $categoryId, ?string $switchAssetProfile): ?string
    {
        $isSwitchCategory = Category::query()
            ->whereKey($categoryId)
            ->where('slug', 'switches')
            ->exists();

        if (!$isSwitchCategory) {
            return null;
        }

        return $switchAssetProfile;
    }
}
