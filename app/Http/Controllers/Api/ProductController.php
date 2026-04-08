<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List products (public) with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category')->where('is_active', true);
        $isHomepageFeaturedRequest = $request->boolean('featured_homepage');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('category_slug')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category_slug);
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->boolean('has_keycap_texture')) {
            $query->whereNotNull('keycap_texture_uv');
        }

        if ($request->boolean('has_keyboard_texture')) {
            $query->whereNotNull('keyboard_texture_uv');
        }

        if ($isHomepageFeaturedRequest) {
            $query->where('is_homepage_featured', true)
                ->whereNotNull('switch_color')
                ->whereNotNull('switch_type')
                ->orderByDesc('updated_at');
        } else {
            $query->latest();
        }

        $defaultPerPage = $isHomepageFeaturedRequest ? 4 : 12;
        $perPage = max((int) $request->input('per_page', $defaultPerPage), 1);

        if ($isHomepageFeaturedRequest) {
            $perPage = min($perPage, 4);
        }

        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Show a single product by slug (public).
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json($product);
    }
}
